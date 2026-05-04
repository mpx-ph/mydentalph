<?php
/**
 * Admin Patient Records Page
 * Requires admin authentication
 */
$pageTitle = 'Patient Records - Admin';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // Require admin role
require_once __DIR__ . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" onload="window.jsPDFScriptLoaded = true;" onerror="console.error('Failed to load jsPDF library');"></script>
<script>
    // Ensure jsPDF is loaded before proceeding
    window.jsPDFReady = false;
    window.jsPDFScriptLoaded = false;
    
    function checkjsPDFLibrary() {
        // Check if script loaded
        if (window.jsPDFScriptLoaded || typeof window.jspdf !== 'undefined' || typeof window.jsPDF !== 'undefined') {
            // Verify it's actually usable
            if (window.jspdf && (window.jspdf.jsPDF || typeof window.jspdf === 'function')) {
                window.jsPDFReady = true;
                console.log('jsPDF library loaded successfully');
                return true;
            } else if (window.jsPDF && typeof window.jsPDF === 'function') {
                window.jsPDFReady = true;
                console.log('jsPDF library loaded successfully (global)');
                return true;
            }
        }
        return false;
    }
    
    // Check immediately
    if (checkjsPDFLibrary()) {
        // Already loaded
    } else {
        // Wait for library to load
        let attempts = 0;
        const maxAttempts = 50; // 5 seconds
        const checkjsPDF = setInterval(() => {
            attempts++;
            if (checkjsPDFLibrary()) {
                clearInterval(checkjsPDF);
            } else if (attempts >= maxAttempts) {
                clearInterval(checkjsPDF);
                console.error('jsPDF library failed to load after 5 seconds');
                console.log('Available window properties:', Object.keys(window).filter(k => k.toLowerCase().includes('pdf')));
            }
        }, 100);
    }
</script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-8 sticky top-0 z-10 shrink-0">
<div>
<h1 class="text-2xl font-bold">Patient Records</h1>
<p class="text-sm text-slate-500 dark:text-slate-400">Manage patient profiles, history, and medical records.</p>
</div>
<div class="flex items-center gap-6">
<div class="relative hidden md:block">
<span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input class="pl-10 pr-4 py-2 w-64 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all" placeholder="Search patient, ID..." type="text"/>
</div>
</header>
<div class="flex-1 overflow-y-auto p-8">
<div class="max-w-7xl mx-auto flex flex-col gap-6">
<div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<p class="text-gray-500 dark:text-gray-400 mt-1">Manage patient profiles, history, and medical records.</p>
</div>
<div class="flex gap-3">
<button class="flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-lg text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 shadow-sm transition-all">
<span class="material-symbols-outlined text-[20px]">download</span>
                            Export
                        </button>
<button id="addNewPatientBtn" class="flex items-center gap-2 px-4 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-semibold shadow-sm shadow-blue-200 dark:shadow-none transition-all">
<span class="material-symbols-outlined text-[20px]">add</span>
                            Add New Patient
                        </button>
</div>
</div>
<div class="flex flex-col sm:flex-row gap-4 p-4 bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark shadow-sm">
<div class="relative flex-1">
<span class="material-symbols-outlined absolute left-3 top-3 text-gray-400">search</span>
<input id="patientSearchInput" class="w-full bg-background-light dark:bg-background-dark border-none rounded-lg py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-500 focus:ring-2 focus:ring-primary/20" placeholder="Search by name, ID, or phone number..." value=""/>
</div>
<div class="flex gap-3 overflow-x-auto pb-1 sm:pb-0">
<button id="statusFilterBtn" class="flex items-center gap-2 px-3 py-2.5 bg-background-light dark:bg-background-dark rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 whitespace-nowrap">
<span class="material-symbols-outlined text-[20px]">filter_list</span>
                            <span id="statusFilterText">Status: All</span>
                        </button>
<button id="lastVisitFilterBtn" class="flex items-center gap-2 px-3 py-2.5 bg-background-light dark:bg-background-dark rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 whitespace-nowrap">
<span class="material-symbols-outlined text-[20px]">calendar_month</span>
                            <span id="lastVisitFilterText">Last Visit: Any</span>
                        </button>
</div>
</div>
<div class="bg-surface-light dark:bg-surface-dark border border-border-light dark:border-border-dark rounded-xl shadow-sm overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead class="bg-gray-50/50 dark:bg-[#1f2d3a]">
<tr>
<th class="py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Patient Name</th>
<th class="py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
<th class="py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
<th class="py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Visit</th>
<th class="py-3 px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
<th class="py-3 px-4 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-border-light dark:divide-border-dark" id="patientsTableBody">
<!-- Patients will be loaded dynamically via JavaScript -->
<tr class="loading-skeleton-row">
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex items-center gap-3">
<div class="size-10 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse"></div>
<div>
<div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-32 mb-2 animate-pulse"></div>
<div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-24 animate-pulse"></div>
</div>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-20 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="space-y-1">
<div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-32 animate-pulse"></div>
<div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-40 animate-pulse"></div>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-6 bg-gray-200 dark:bg-gray-700 rounded-full w-16 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap text-right"><div class="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse inline-block"></div></td>
</tr>
<tr class="loading-skeleton-row">
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex items-center gap-3">
<div class="size-10 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse"></div>
<div>
<div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-28 mb-2 animate-pulse"></div>
<div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-20 animate-pulse"></div>
</div>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-20 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="space-y-1">
<div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-28 animate-pulse"></div>
<div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-36 animate-pulse"></div>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-6 bg-gray-200 dark:bg-gray-700 rounded-full w-16 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap text-right"><div class="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse inline-block"></div></td>
</tr>
<tr class="loading-skeleton-row">
<td class="py-4 px-4 whitespace-nowrap">
<div class="flex items-center gap-3">
<div class="size-10 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse"></div>
<div>
<div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-36 mb-2 animate-pulse"></div>
<div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-28 animate-pulse"></div>
</div>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-20 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap">
<div class="space-y-1">
<div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-30 animate-pulse"></div>
<div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-44 animate-pulse"></div>
</div>
</td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-24 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap"><div class="h-6 bg-gray-200 dark:bg-gray-700 rounded-full w-16 animate-pulse"></div></td>
<td class="py-4 px-4 whitespace-nowrap text-right"><div class="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse inline-block"></div></td>
</tr>
</tbody>
</table>
</div>
<div class="flex items-center justify-between border-t border-border-light dark:border-border-dark bg-white dark:bg-surface-dark px-4 py-3 sm:px-6">
<div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
<div>
<p class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing <span class="font-medium">1</span> to <span class="font-medium">1</span> of <span class="font-medium">1</span> result
                                </p>
</div>
<div class="hidden">
<nav aria-label="Pagination" class="isolate inline-flex -space-x-px rounded-md shadow-sm">
<a class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 focus:z-20 focus:outline-offset-0" href="#">
<span class="sr-only">Previous</span>
<span class="material-symbols-outlined text-[20px]">chevron_left</span>
</a>
<a aria-current="page" class="relative z-10 inline-flex items-center bg-primary px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600" href="#">1</a>
<a class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 focus:z-20 focus:outline-offset-0" href="#">2</a>
<a class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 focus:z-20 focus:outline-offset-0" href="#">3</a>
<a class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 focus:z-20 focus:outline-offset-0" href="#">
<span class="sr-only">Next</span>
<span class="material-symbols-outlined text-[20px]">chevron_right</span>
</a>
</nav>
</div>
</div>
</div>
</div>
</div>
</div>
<div id="addPatientModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
<div class="bg-surface-light dark:bg-surface-dark rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col border border-border-light dark:border-border-dark">
<div class="flex items-center justify-between p-6 border-b border-border-light dark:border-border-dark">
<h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
<span class="material-symbols-outlined text-primary">person_add</span>
                        Add New Patient
                    </h2>
<button id="closeAddPatientModal" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form id="addPatientForm" class="flex-1 overflow-y-auto p-6">
<div class="mb-6">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2 block">Patient Photo</label>
<div class="flex flex-col items-center gap-4">
<div class="relative">
<div id="addPatientPhotoPreview" class="size-32 rounded-full bg-gray-100 dark:bg-gray-800 border-4 border-gray-200 dark:border-gray-700 overflow-hidden flex items-center justify-center cursor-pointer hover:border-primary transition-colors group">
<span class="material-symbols-outlined text-5xl text-gray-400 group-hover:text-primary">person</span>
<img id="addPatientPhotoImg" src="" alt="Preview" class="hidden w-full h-full object-cover"/>
</div>
<input id="addPatientPhoto" type="file" accept="image/*" class="hidden"/>
</div>
<div class="flex flex-col items-center gap-2">
<button type="button" id="addPatientPhotoBtn" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">photo_camera</span>
Choose Photo
</button>
<button type="button" id="removePatientPhotoBtn" class="hidden px-3 py-1.5 text-xs text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all">
Remove Photo
</button>
<p class="text-xs text-gray-500">Recommended: Square image, max 5MB</p>
</div>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">First Name <span class="text-red-500">*</span></label>
<input id="addFirstName" type="text" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter first name"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Last Name <span class="text-red-500">*</span></label>
<input id="addLastName" type="text" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter last name"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Contact Number <span class="text-red-500">*</span></label>
<input id="addContact" type="tel" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="(555) 123-4567"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Email <span class="text-red-500">*</span></label>
<input id="addEmail" type="email" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="patient@email.com"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Date of Birth <span class="text-red-500">*</span></label>
<input id="addDob" type="date" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Gender <span class="text-red-500">*</span></label>
<select id="addGender" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary">
<option value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
<option value="Other">Other</option>
<option value="Prefer not to say">Prefer not to say</option>
</select>
</div>
</div>
<div class="mt-6 pt-6 border-t border-border-light dark:border-border-dark">
<h4 class="text-sm font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">home</span>
Address Information
</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="flex flex-col md:col-span-2">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">House No. & Street <span class="text-red-500">*</span></label>
<input id="addHouseStreet" type="text" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter house number and street"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Barangay <span class="text-red-500">*</span></label>
<input id="addBarangay" type="text" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter barangay"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">City/Municipality <span class="text-red-500">*</span></label>
<input id="addCity" type="text" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter city or municipality"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Province <span class="text-red-500">*</span></label>
<input id="addProvince" type="text" required class="text-sm text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter province"/>
</div>
</div>
</div>
<div class="flex items-center justify-end gap-3 mt-6 pt-6 border-t border-border-light dark:border-border-dark">
<button type="button" id="cancelAddPatientBtn" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-semibold transition-all">
Cancel
</button>
<button type="submit" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">save</span>
Save Patient
</button>
</div>
</form>
</div>
</div>
<div id="patientDetailBackdrop" class="absolute inset-0 bg-gray-900/40 backdrop-blur-[2px] z-30 transition-opacity hidden"></div>
<div id="patientDetailPanel" class="absolute inset-y-0 right-0 w-full md:w-[700px] lg:w-[850px] bg-surface-light dark:bg-surface-dark shadow-2xl z-40 transform transition-transform duration-300 flex flex-col h-full rounded-l-2xl border-l border-border-light dark:border-border-dark hidden translate-x-full">
<div class="flex-none bg-surface-light dark:bg-surface-dark border-b border-border-light dark:border-border-dark relative">
<div class="flex items-center justify-end gap-2 p-4 pb-0">
<button id="exportPatientBtn" class="p-2 text-gray-500 hover:text-primary hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider" title="Export to PDF">
<span class="material-symbols-outlined text-[18px]">download</span>
                        Export
                    </button>
<button id="editPatientBtn" class="p-2 text-gray-500 hover:text-primary hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider hidden">
<span class="material-symbols-outlined text-[18px]">edit_square</span>
                        Edit
                    </button>
<button id="closePatientDetailBtn" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" title="Close">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<div class="px-8 pb-6 flex flex-col gap-6">
<div class="flex flex-col sm:flex-row gap-6 items-start sm:items-center">
<div class="relative">
<div class="size-24 rounded-full bg-cover bg-center shadow-lg ring-4 ring-white dark:ring-surface-dark" data-alt="Patient Portrait" style='background-image: url("");'></div>
<div class="absolute bottom-1 right-1 size-5 bg-green-500 border-2 border-white dark:border-surface-dark rounded-full" title="Status: Active"></div>
</div>
<div class="flex-1 space-y-2">
<div class="flex flex-wrap items-center gap-3">
<h2 class="text-3xl font-bold text-gray-900 dark:text-white tracking-tight">Patient Name</h2>
<span class="px-2.5 py-1 rounded-md bg-green-100 text-green-700 text-xs font-bold uppercase tracking-wider dark:bg-green-900/30 dark:text-green-400 border border-green-200 dark:border-green-800">Active</span>
</div>
<div class="flex flex-wrap gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-400">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-gray-400">badge</span>
<span class="font-mono" id="patientIdDisplay">#---</span>
</div>
</div>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-4 border-t border-border-light dark:border-border-dark">
<div class="flex flex-col">
<span class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Created At</span>
<div class="flex items-center gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
<span class="material-symbols-outlined text-[16px] text-gray-400">event_available</span> <span id="createdAtDisplay">---</span>
                            </div>
</div>
<div class="flex flex-col">
<span class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-1">Last Visit</span>
<div class="flex items-center gap-1.5 text-sm font-medium text-gray-900 dark:text-white">
<span class="material-symbols-outlined text-[16px] text-gray-400">history</span> <span id="lastVisitDisplay">---</span>
                            </div>
</div>
</div>
</div>
<div class="px-8 flex gap-8 overflow-x-auto scrollbar-hide">
<button class="tab-btn pb-4 border-b-2 border-primary text-primary font-bold text-sm whitespace-nowrap" data-tab="basic-info">Basic Info</button>
<button class="tab-btn pb-4 border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 font-medium text-sm whitespace-nowrap transition-colors" data-tab="appointments">Appointments</button>
<button class="tab-btn pb-4 border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 font-medium text-sm whitespace-nowrap transition-colors" data-tab="active-treatment">Active Treatment</button>
<button class="tab-btn pb-4 border-b-2 border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 font-medium text-sm whitespace-nowrap transition-colors" data-tab="files">Files &amp; Documents</button>
</div>
</div>
<div class="flex-1 overflow-y-auto bg-gray-50/50 dark:bg-[#111827]">
<div class="max-w-4xl mx-auto p-6 md:p-8 space-y-8">
<!-- Basic Info Tab Content -->
<section id="basic-info-tab" class="tab-content">
<div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark p-6">
<h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6 flex items-center gap-2">
<span class="material-symbols-outlined text-primary">person</span> 
                                Basic Information
                            </h3>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">First Name</label>
<div id="firstNameDisplay" class="text-sm font-medium text-gray-900 dark:text-white">Jane</div>
<input id="firstNameInput" type="text" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" value="Jane"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Last Name</label>
<div id="lastNameDisplay" class="text-sm font-medium text-gray-900 dark:text-white">Doe</div>
<input id="lastNameInput" type="text" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" value="Doe"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Contact Number</label>
<div id="contactDisplay" class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-gray-400">call</span> <span>N/A</span>
</div>
<input id="contactInput" type="tel" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" value=""/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Email</label>
<div id="emailDisplay" class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-gray-400">mail</span> <span>N/A</span>
</div>
<input id="emailInput" type="email" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" value=""/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Date of Birth</label>
<div id="dobDisplay" class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-gray-400">cake</span> <span>Jan 15, 1995</span>
</div>
<input id="dobInput" type="date" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" value="1995-01-15"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Gender</label>
<div id="genderDisplay" class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-gray-400">wc</span> <span>Female</span>
</div>
<select id="genderInput" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary">
<option value="Male">Male</option>
<option value="Female" selected>Female</option>
<option value="Other">Other</option>
<option value="Prefer not to say">Prefer not to say</option>
</select>
</div>
</div>
<div class="mt-6 pt-6 border-t border-border-light dark:border-border-dark">
<h4 class="text-sm font-bold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">home</span>
Address Information
</h4>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="flex flex-col md:col-span-2">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">House No. & Street</label>
<div id="houseStreetDisplay" class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-gray-400">home</span> <span>N/A</span>
</div>
<input id="houseStreetInput" type="text" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter house number and street"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Barangay</label>
<div id="barangayDisplay" class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-gray-400">location_city</span> <span>N/A</span>
</div>
<input id="barangayInput" type="text" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter barangay"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">City/Municipality</label>
<div id="cityDisplay" class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-gray-400">apartment</span> <span>N/A</span>
</div>
<input id="cityInput" type="text" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter city or municipality"/>
</div>
<div class="flex flex-col">
<label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Province</label>
<div id="provinceDisplay" class="text-sm font-medium text-gray-900 dark:text-white flex items-center gap-1.5">
<span class="material-symbols-outlined text-[16px] text-gray-400">map</span> <span>N/A</span>
</div>
<input id="provinceInput" type="text" class="hidden text-sm font-medium text-gray-900 dark:text-white bg-background-light dark:bg-background-dark border border-border-light dark:border-border-dark rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Enter province"/>
</div>
</div>
</div>
<div id="editActions" class="hidden flex items-center justify-end gap-3 mt-6 pt-6 border-t border-border-light dark:border-border-dark">
<button id="cancelEditBtn" class="px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-semibold transition-all">
Cancel
</button>
<button id="saveEditBtn" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-semibold transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-[18px]">save</span>
Save Changes
</button>
</div>
</div>
</section>
<!-- Appointments Tab Content -->
<section id="appointments-tab" class="tab-content hidden">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
<span class="material-symbols-outlined text-primary">calendar_month</span> 
                                Appointments History
                            </h3>
<button class="text-primary text-sm font-semibold hover:underline flex items-center gap-1">
                                View Calendar <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
</button>
</div>
<div id="appointmentsContainer" class="grid gap-6">
<div class="text-center py-8 text-gray-500">
<span class="material-symbols-outlined text-4xl mb-2 opacity-50">calendar_month</span>
<p>Loading appointments...</p>
</div>
</div>
</section>
<!-- Active Treatment Tab Content -->
<section id="active-treatment-tab" class="tab-content hidden">
<div class="flex items-center gap-3 mb-4">
<div class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center">
<span class="material-symbols-outlined text-primary">medical_services</span>
</div>
<h3 class="text-lg font-bold text-gray-900 dark:text-white">Long-Term Treatment Plans</h3>
</div>
<div class="bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/10 rounded-xl border border-purple-200/50 dark:border-purple-800/50 p-6 shadow-sm">
<div id="activeTreatmentContainer" class="space-y-4">
<div class="text-center py-8 text-gray-500 dark:text-gray-400">
<span class="material-symbols-outlined text-4xl mb-2 opacity-50">medical_services</span>
<p>Loading treatment plans...</p>
</div>
</div>
</div>
</section>
<!-- Files & Documents Tab Content -->
<section id="files-tab" class="tab-content hidden">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
<span class="material-symbols-outlined text-gray-500">folder_open</span> 
                                Files &amp; Documents
                            </h3>
<a id="viewAllFilesLink" class="text-sm text-primary hover:underline font-medium" href="#">View All</a>
</div>
<div id="filesContainer" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
<div class="text-center py-8 text-gray-500 col-span-full">
<span class="material-symbols-outlined text-4xl mb-2 opacity-50">folder_open</span>
<p>Loading files...</p>
</div>
</div>
</section>
</div>
</div>
<div class="p-4 border-t border-border-light dark:border-border-dark bg-white dark:bg-surface-dark rounded-bl-2xl">
<button class="w-full py-3.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-bold text-sm shadow-md shadow-blue-200 dark:shadow-none transition-all flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-[20px]">calendar_add_on</span>
                    Schedule Next Appointment
                </button>
</div>
</div>
<!-- Treatment Progress Modal (Admin) -->
<div id="treatmentProgressModalAdmin" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 hidden items-center justify-center p-4">
<div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
<!-- Modal Header -->
<div class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-800">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
<span class="material-symbols-outlined text-primary">calendar_month</span>
</div>
<h3 class="text-xl font-bold text-[#0d141b] dark:text-white">Treatment Progress</h3>
</div>
<button onclick="closeTreatmentProgressModalAdmin()" class="p-2 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors">
<span class="material-symbols-outlined text-slate-500 dark:text-slate-400">close</span>
</button>
</div>
<!-- Modal Content -->
<div class="flex-1 overflow-y-auto p-6">
<!-- Total Payment Progress Section -->
<div class="bg-gradient-to-r from-primary to-purple-400 rounded-xl p-6 mb-6 text-white">
<p class="text-xs uppercase tracking-wide mb-2 opacity-90">TOTAL PAYMENT PROGRESS</p>
<div id="totalProgressAmountAdmin" class="flex items-baseline gap-2 mb-4">
<span class="text-3xl font-bold">₱0.00</span>
<span class="text-sm opacity-80">/ ₱0.00</span>
</div>
<div class="flex items-center gap-3">
<div class="flex-1 h-3 bg-white/20 rounded-full overflow-hidden">
<div id="totalProgressBarAdmin" class="h-full bg-white rounded-full" style="width: 0%"></div>
</div>
<span id="totalProgressTextAdmin" class="text-sm font-semibold">0% Paid</span>
</div>
</div>
<!-- Treatment Details Table -->
<div class="overflow-x-auto bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800">
<table id="treatmentProgressTableAdmin" class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-100 dark:border-slate-800">
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-[#4c739a]">TREATMENT</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-[#4c739a]">STATUS</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-[#4c739a]">DATE OF BOOKING</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-[#4c739a]">TIME</th>
<th class="px-6 py-3 text-xs font-bold uppercase tracking-wider text-[#4c739a]">AMOUNT DUE</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100 dark:divide-slate-800">
</tbody>
</table>
</div>
</div>
<!-- Modal Footer -->
<div class="flex justify-end p-6 border-t border-slate-200 dark:border-slate-800">
<button onclick="closeTreatmentProgressModalAdmin()" class="bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm transition-colors">Close</button>
</div>
</div>
</div>
</div>
</main>
</div>
<script>
    function loadUserData() {
        const userDataStr = sessionStorage.getItem('adminUser');
        if (userDataStr) {
            try {
                const userData = JSON.parse(userDataStr);
                const userNameEl = document.getElementById('userName');
                const userRoleEl = document.getElementById('userRole');
                const userPhotoEl = document.getElementById('userPhoto');
                if (userNameEl) userNameEl.textContent = userData.name || 'Marc';
                if (userRoleEl) userRoleEl.textContent = 'Admin';
                if (userPhotoEl && userData.photo) {
                    userPhotoEl.style.backgroundImage = `url("${userData.photo}")`;
                }
            } catch (e) {
                console.error('Error loading user data:', e);
            }
        }
    }
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            sessionStorage.removeItem('adminUser');
            window.location.href = '<?php echo BASE_URL; ?>api/logout.php';
        });
    }
    loadUserData();
    
    // Global variable to store all patients for filtering
    let allPatientsData = [];

    // Process and render patients data
    function renderPatients(patients) {
        const tbody = document.getElementById('patientsTableBody') || document.querySelector('tbody');
        if (!tbody) return;
        
        // Remove all skeleton loading rows
        const skeletonRows = tbody.querySelectorAll('.loading-skeleton-row');
        skeletonRows.forEach(row => row.remove());
        
        // Clear any existing content
        tbody.innerHTML = '';
        
        if (!patients || patients.length === 0) {
            // Show empty state
            tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-gray-500">No patients found. Add your first patient to get started.</td></tr>';
            updatePaginationInfo(0);
            return;
        }
        
        // Process and display each patient
        patients.forEach(patient => {
            // Convert API data format to format expected by createPatientRow
            // Database uses: contact_number, house_street, barangay, city_municipality
            const patientData = {
                id: patient.id,
                patientId: patient.patient_id || '', // Display ID for appointments API
                firstName: patient.first_name || '',
                lastName: patient.last_name || '',
                contact: patient.contact_number || '',
                email: patient.email || '',
                dob: patient.date_of_birth || '',
                gender: patient.gender || '',
                houseStreet: patient.house_street || '',
                barangay: patient.barangay || '',
                city: patient.city_municipality || '',
                province: patient.province || '',
                photo: (patient.profile_image || patient.photo) ? ('<?php echo BASE_URL; ?>' + (patient.profile_image || patient.photo).replace(/^\/+/, '')) : null,
                status: 'active', // Schema doesn't have status column, default to active
                createdAt: patient.created_at || '',
                lastVisit: patient.updated_at || patient.created_at || ''
            };
            
            // Save to localStorage for detail panel
            const patientDataKey = `patient_data_${patientData.id}`;
            localStorage.setItem(patientDataKey, JSON.stringify(patientData));
            
            // Create and add row to table
            const row = createPatientRow(patientData);
            tbody.appendChild(row);
        });
        
        // Update pagination info
        updatePaginationInfo(patients.length);
    }
    
    // Load patients from database on page load
    function loadPatientsFromDatabase() {
        const tbody = document.getElementById('patientsTableBody') || document.querySelector('tbody');
        if (!tbody) {
            setTimeout(loadPatientsFromDatabase, 50);
            return;
        }
        
        fetch('<?php echo BASE_URL; ?>api/patients.php', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include'
        })
            .then(function(response) {
                return response.text().then(function(text) {
                    return { ok: response.ok, status: response.status, text: text };
                });
            })
            .then(function(result) {
                var data;
                try {
                    data = JSON.parse(result.text);
                } catch (e) {
                    console.error('Patients API invalid JSON:', result.text ? result.text.substring(0, 500) : '(empty)');
                    showError('Server returned an invalid response. Please try again.');
                    return;
                }
                if (!result.ok) {
                    showError(data.message || 'Could not load patients (server error). Please try again.');
                    return;
                }
                if (!data.success || !data.data) {
                    showError(data.message || 'Could not load patients.');
                    return;
                }
                var patients = data.data.patients;
                if (!Array.isArray(patients)) {
                    showError(data.message || 'Invalid patients data.');
                    return;
                }
                // Store all patients for filtering
                allPatientsData = patients;
                applyFilters();
            })
            .catch(function(error) {
                console.error('Error fetching patients:', error);
                showError('Error loading patients. Please check your connection and refresh.');
            });
    }
    
    function showError(message) {
        const tbody = document.getElementById('patientsTableBody') || document.querySelector('tbody');
        if (tbody) {
            // Remove skeleton rows
            const skeletonRows = tbody.querySelectorAll('.loading-skeleton-row');
            skeletonRows.forEach(row => row.remove());
            tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-red-500">' + message + '</td></tr>';
        }
    }
    
    // Helper function to update pagination info
    function updatePaginationInfo(total) {
        const paginationText = document.querySelector('.text-sm.text-gray-700, .text-sm.text-gray-300');
        if (paginationText) {
            paginationText.innerHTML = `Showing <span class="font-medium">1</span> to <span class="font-medium">${total}</span> of <span class="font-medium">${total}</span> result${total !== 1 ? 's' : ''}`;
        }
    }
    
    // Filter state
    let currentStatusFilter = 'all';
    let currentLastVisitFilter = 'any';
    
    // Apply filters to patients
    function applyFilters() {
        if (!allPatientsData || allPatientsData.length === 0) {
            renderPatients([]);
            return;
        }
        
        const searchInput = document.getElementById('patientSearchInput');
        const searchTerm = (searchInput ? searchInput.value.trim().toLowerCase() : '');
        
        let filtered = allPatientsData.filter(patient => {
            // Search filter
            if (searchTerm) {
                const fullName = `${patient.first_name || ''} ${patient.last_name || ''}`.toLowerCase();
                const patientId = String(patient.id || '').toLowerCase();
                const contact = (patient.contact_number || '').toLowerCase();
                const email = (patient.email || '').toLowerCase();
                
                if (!fullName.includes(searchTerm) && 
                    !patientId.includes(searchTerm) && 
                    !contact.includes(searchTerm) &&
                    !email.includes(searchTerm)) {
                    return false;
                }
            }
            
            // Status filter (currently all patients are 'active', but we can filter if needed)
            if (currentStatusFilter !== 'all') {
                // For now, all patients are active, but this can be extended
                // if status field is added to the database
            }
            
            // Last visit filter
            if (currentLastVisitFilter !== 'any') {
                const lastVisit = patient.updated_at || patient.created_at;
                if (!lastVisit) return false;
                
                const visitDate = new Date(lastVisit);
                const now = new Date();
                const daysDiff = Math.floor((now - visitDate) / (1000 * 60 * 60 * 24));
                
                switch (currentLastVisitFilter) {
                    case 'today':
                        if (daysDiff !== 0) return false;
                        break;
                    case 'week':
                        if (daysDiff > 7) return false;
                        break;
                    case 'month':
                        if (daysDiff > 30) return false;
                        break;
                    case 'year':
                        if (daysDiff > 365) return false;
                        break;
                }
            }
            
            return true;
        });
        
        renderPatients(filtered);
    }
    
    // Initialize filter functionality
    function initializeFilters() {
        const searchInput = document.getElementById('patientSearchInput');
        const statusFilterBtn = document.getElementById('statusFilterBtn');
        const lastVisitFilterBtn = document.getElementById('lastVisitFilterBtn');
        
        // Search input event listener
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                applyFilters();
            });
        }
        
        // Status filter dropdown
        if (statusFilterBtn) {
            statusFilterBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const statusOptions = ['all', 'active', 'inactive'];
                const currentIndex = statusOptions.indexOf(currentStatusFilter);
                const nextIndex = (currentIndex + 1) % statusOptions.length;
                currentStatusFilter = statusOptions[nextIndex];
                
                const statusText = document.getElementById('statusFilterText');
                if (statusText) {
                    const labels = { 'all': 'All', 'active': 'Active', 'inactive': 'Inactive' };
                    statusText.textContent = `Status: ${labels[currentStatusFilter]}`;
                }
                applyFilters();
            });
        }
        
        // Last visit filter dropdown
        if (lastVisitFilterBtn) {
            lastVisitFilterBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                const visitOptions = ['any', 'today', 'week', 'month', 'year'];
                const currentIndex = visitOptions.indexOf(currentLastVisitFilter);
                const nextIndex = (currentIndex + 1) % visitOptions.length;
                currentLastVisitFilter = visitOptions[nextIndex];
                
                const visitText = document.getElementById('lastVisitFilterText');
                if (visitText) {
                    const labels = { 
                        'any': 'Any', 
                        'today': 'Today', 
                        'week': 'This Week', 
                        'month': 'This Month', 
                        'year': 'This Year' 
                    };
                    visitText.textContent = `Last Visit: ${labels[currentLastVisitFilter]}`;
                }
                applyFilters();
            });
        }
    }
    
    // Load patients immediately - don't wait for page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            loadPatientsFromDatabase();
            initializeFilters();
        });
    } else {
        // DOM already ready, load immediately
        loadPatientsFromDatabase();
        initializeFilters();
    }

    // Patient detail panel functionality
    const patientDetailBackdrop = document.getElementById('patientDetailBackdrop');
    const patientDetailPanel = document.getElementById('patientDetailPanel');
    const closePatientDetailBtn = document.getElementById('closePatientDetailBtn');
    const viewPatientBtns = document.querySelectorAll('.view-patient-btn');
    let currentPatientId = null; // This will be updated when viewing different patients

    function openPatientDetail() {
        patientDetailBackdrop.classList.remove('hidden');
        patientDetailPanel.classList.remove('hidden');
        // Trigger reflow to ensure transition works
        setTimeout(() => {
            patientDetailPanel.classList.remove('translate-x-full');
        }, 10);
        
        // Show Edit button since Basic Info tab is active by default
        const editPatientBtn = document.getElementById('editPatientBtn');
        if (editPatientBtn) {
            editPatientBtn.classList.remove('hidden');
        }
        
        // Ensure export button is enabled and ready
        setTimeout(() => {
            attachExportButtonListener();
        }, 50);
    }

    function closePatientDetail() {
        patientDetailPanel.classList.add('translate-x-full');
        setTimeout(() => {
            patientDetailPanel.classList.add('hidden');
            patientDetailBackdrop.classList.add('hidden');
        }, 300); // Match transition duration
    }

    // Load patient data into detail panel
    function loadPatientIntoDetailPanel(patientId) {
        const patientDataKey = `patient_data_${patientId}`;
        const savedData = localStorage.getItem(patientDataKey);
        
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                // Update all display elements in the detail panel
                const nameHeader = document.querySelector('#patientDetailPanel h2');
                if (nameHeader) {
                    nameHeader.textContent = `${data.firstName} ${data.lastName}`;
                }
                
                // Update basic info displays
                const firstNameDisplay = document.getElementById('firstNameDisplay');
                const lastNameDisplay = document.getElementById('lastNameDisplay');
                const contactDisplay = document.getElementById('contactDisplay');
                const emailDisplay = document.getElementById('emailDisplay');
                const dobDisplay = document.getElementById('dobDisplay');
                const genderDisplay = document.getElementById('genderDisplay');
                
                if (firstNameDisplay) firstNameDisplay.textContent = data.firstName || '';
                if (lastNameDisplay) lastNameDisplay.textContent = data.lastName || '';
                
                const contactSpan = contactDisplay?.querySelector('span:last-child');
                if (contactSpan && data.contact) contactSpan.textContent = data.contact;
                
                const emailSpan = emailDisplay?.querySelector('span:last-child');
                if (emailSpan && data.email) emailSpan.textContent = data.email;
                
                const dobSpan = dobDisplay?.querySelector('span:last-child');
                if (dobSpan && data.dob) dobSpan.textContent = formatDateForDisplay(data.dob);
                
                const genderSpan = genderDisplay?.querySelector('span:last-child');
                if (genderSpan && data.gender) genderSpan.textContent = data.gender;
                
                // Update address displays
                const houseStreetDisplay = document.getElementById('houseStreetDisplay');
                const barangayDisplay = document.getElementById('barangayDisplay');
                const cityDisplay = document.getElementById('cityDisplay');
                const provinceDisplay = document.getElementById('provinceDisplay');
                
                const houseStreetSpan = houseStreetDisplay?.querySelector('span:last-child');
                if (houseStreetSpan) houseStreetSpan.textContent = data.houseStreet || 'N/A';
                
                const barangaySpan = barangayDisplay?.querySelector('span:last-child');
                if (barangaySpan) barangaySpan.textContent = data.barangay || 'N/A';
                
                const citySpan = cityDisplay?.querySelector('span:last-child');
                if (citySpan) citySpan.textContent = data.city || 'N/A';
                
                const provinceSpan = provinceDisplay?.querySelector('span:last-child');
                if (provinceSpan) provinceSpan.textContent = data.province || 'N/A';
                
                // Update patient ID display
                const patientIdDisplay = document.getElementById('patientIdDisplay');
                if (patientIdDisplay) {
                    patientIdDisplay.textContent = `#${patientId}`;
                }
                
                // Update createdAt and lastVisit displays
                const createdAtDisplay = document.getElementById('createdAtDisplay');
                if (createdAtDisplay && data.createdAt) {
                    createdAtDisplay.textContent = formatDateForDisplay(data.createdAt);
                }
                
                const lastVisitDisplay = document.getElementById('lastVisitDisplay');
                if (lastVisitDisplay && data.lastVisit) {
                    lastVisitDisplay.textContent = formatDateForDisplay(data.lastVisit);
                }
                
                // Update avatar - use uploaded photo if available, otherwise generate avatar URL
                const avatarUrl = data.photo || `https://ui-avatars.com/api/?name=${encodeURIComponent((data.firstName + ' ' + data.lastName).trim() || 'Patient')}&background=2563eb&color=fff&size=128`;
                const safeAvatarUrl = (avatarUrl || '').replace(/"/g, '%22');
                const avatarElements = document.querySelectorAll('#patientDetailPanel [style*="background-image"]');
                avatarElements.forEach(el => {
                    el.style.backgroundImage = `url("${safeAvatarUrl}")`;
                });
                
                // Update currentPatientId
                currentPatientId = patientId;
                
            } catch (e) {
                console.error('Error loading patient data:', e);
            }
        } else {
            // If no saved data, try to get from table row
            const row = document.querySelector(`tr[data-patient-id="${patientId}"]`);
            if (row) {
                const nameCell = row.querySelector('td:first-child');
                const nameText = nameCell?.querySelector('p.font-bold')?.textContent || '';
                const nameParts = nameText.split(' ');
                const firstName = nameParts[0] || '';
                const lastName = nameParts.slice(1).join(' ') || '';
                
                const contactCell = row.querySelector('td:nth-child(3)');
                const contactText = contactCell?.querySelector('.text-sm')?.textContent?.trim() || '';
                const emailText = contactCell?.querySelector('.text-xs')?.textContent?.trim() || '';
                
                // Update displays with table data
                const firstNameDisplay = document.getElementById('firstNameDisplay');
                const lastNameDisplay = document.getElementById('lastNameDisplay');
                if (firstNameDisplay) firstNameDisplay.textContent = firstName;
                if (lastNameDisplay) lastNameDisplay.textContent = lastName;
                
                const contactDisplay = document.getElementById('contactDisplay');
                const emailDisplay = document.getElementById('emailDisplay');
                const contactSpan = contactDisplay?.querySelector('span:last-child');
                const emailSpan = emailDisplay?.querySelector('span:last-child');
                if (contactSpan && contactText) contactSpan.textContent = contactText;
                if (emailSpan && emailText) emailSpan.textContent = emailText;
                
                // Update name header
                const nameHeader = document.querySelector('#patientDetailPanel h2');
                if (nameHeader) {
                    nameHeader.textContent = nameText;
                }
                
                // Update patient ID display
                const patientIdDisplay = document.getElementById('patientIdDisplay');
                if (patientIdDisplay) {
                    patientIdDisplay.textContent = `#${patientId}`;
                }
                
                // Update avatar from row (reuse same image) or fallback to ui-avatars
                const rowAvatarEl = row.querySelector('[style*="background-image"]');
                let avatarUrl = `https://ui-avatars.com/api/?name=${encodeURIComponent(nameText || 'Patient')}&background=2563eb&color=fff&size=128`;
                if (rowAvatarEl) {
                    const bg = rowAvatarEl.style.backgroundImage || getComputedStyle(rowAvatarEl).backgroundImage;
                    const match = bg && bg.match(/url\(["']?([^"')]+)["']?\)/);
                    if (match && match[1]) avatarUrl = match[1];
                }
                const avatarElements = document.querySelectorAll('#patientDetailPanel [style*="background-image"]');
                avatarElements.forEach(el => { el.style.backgroundImage = `url("${avatarUrl.replace(/"/g, '%22')}")`; });
            }
        }
    }

    // Close button click
    if (closePatientDetailBtn) {
        closePatientDetailBtn.addEventListener('click', closePatientDetail);
    }

    // Backdrop click to close
    if (patientDetailBackdrop) {
        patientDetailBackdrop.addEventListener('click', closePatientDetail);
    }

    // View patient buttons
    viewPatientBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent row click
            const patientId = btn.getAttribute('data-patient-id');
            if (patientId) {
                loadPatientIntoDetailPanel(patientId);
            }
            openPatientDetail();
        });
    });

    // Prevent panel from closing when clicking inside it
    if (patientDetailPanel) {
        patientDetailPanel.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    function switchTab(tabName) {
        // Hide all tab contents
        tabContents.forEach(content => {
            content.classList.add('hidden');
        });

        // Remove active state from all buttons
        tabButtons.forEach(btn => {
            btn.classList.remove('border-primary', 'text-primary', 'font-bold');
            btn.classList.add('border-transparent', 'text-gray-500', 'font-medium');
        });

        // Show selected tab content
        const selectedTab = document.getElementById(`${tabName}-tab`);
        if (selectedTab) {
            selectedTab.classList.remove('hidden');
        }

        // Add active state to selected button
        const selectedBtn = document.querySelector(`[data-tab="${tabName}"]`);
        if (selectedBtn) {
            selectedBtn.classList.remove('border-transparent', 'text-gray-500', 'font-medium');
            selectedBtn.classList.add('border-primary', 'text-primary', 'font-bold');
        }

        // Show/hide Edit button based on active tab
        const editPatientBtn = document.getElementById('editPatientBtn');
        if (editPatientBtn) {
            if (tabName === 'basic-info') {
                editPatientBtn.classList.remove('hidden');
            } else {
                editPatientBtn.classList.add('hidden');
                // Exit edit mode if active when switching tabs
                if (editActions && !editActions.classList.contains('hidden')) {
                    exitEditMode();
                }
            }
        }

        // Load appointments when appointments tab is opened
        if (tabName === 'appointments') {
            loadAppointments();
        }

        // Load active treatments when active treatment tab is opened
        if (tabName === 'active-treatment') {
            loadActiveTreatments();
        }

        // Load files when files tab is opened
        if (tabName === 'files') {
            loadFiles();
        }
    }

    // Load appointments for current patient
    function loadAppointments() {
        const appointmentsContainer = document.getElementById('appointmentsContainer');
        if (!appointmentsContainer || !currentPatientId) {
            return;
        }

        // Get patient data to find the database ID
        const patientDataKey = `patient_data_${currentPatientId}`;
        const savedData = localStorage.getItem(patientDataKey);
        
        if (!savedData) {
            appointmentsContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">calendar_month</span><p>No patient data found</p></div>';
            return;
        }

        try {
            const patientData = JSON.parse(savedData);
            // Use patientId (display ID) if available, otherwise check if currentPatientId is a display ID
            let patientId = patientData.patientId;
            
            // If currentPatientId looks like a display ID (starts with "PT-"), use it
            if (!patientId && currentPatientId && currentPatientId.toString().startsWith('PT-')) {
                patientId = currentPatientId;
            }
            
            // Fallback: if still no patientId, we can't fetch appointments
            if (!patientId) {
                appointmentsContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">calendar_month</span><p>Patient ID not found</p></div>';
                return;
            }

            // Show loading state
            appointmentsContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><span class="material-symbols-outlined text-4xl mb-2 opacity-50 animate-spin">hourglass_empty</span><p>Loading appointments...</p></div>';

            // Fetch appointments from API
            fetch(`<?php echo BASE_URL; ?>api/appointments.php?patient_id=${encodeURIComponent(patientId)}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.appointments) {
                    renderAppointments(data.data.appointments);
                } else {
                    appointmentsContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">calendar_month</span><p>No appointments found</p></div>';
                }
            })
            .catch(error => {
                console.error('Error loading appointments:', error);
                appointmentsContainer.innerHTML = '<div class="text-center py-8 text-red-500"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">error</span><p>Error loading appointments. Please try again.</p></div>';
            });
        } catch (e) {
            console.error('Error parsing patient data:', e);
            appointmentsContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">calendar_month</span><p>Error loading patient data</p></div>';
        }
    }

    // Render appointments
    function renderAppointments(appointments) {
        const appointmentsContainer = document.getElementById('appointmentsContainer');
        if (!appointmentsContainer) return;

        if (!appointments || appointments.length === 0) {
            appointmentsContainer.innerHTML = '<div class="text-center py-8 text-gray-500"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">calendar_month</span><p>No appointments found</p></div>';
            return;
        }

        const now = new Date();
        const upcoming = [];
        const past = [];

        // Separate upcoming and past appointments
        appointments.forEach(apt => {
            const aptDate = new Date(apt.appointment_date + ' ' + (apt.appointment_time || '00:00:00'));
            if (aptDate >= now && (apt.final_status === 'PENDING' || apt.final_status === 'SCHEDULED')) {
                upcoming.push(apt);
            } else {
                past.push(apt);
            }
        });

        // Sort upcoming by date (ascending)
        upcoming.sort((a, b) => {
            const dateA = new Date(a.appointment_date + ' ' + (a.appointment_time || '00:00:00'));
            const dateB = new Date(b.appointment_date + ' ' + (b.appointment_time || '00:00:00'));
            return dateA - dateB;
        });

        // Sort past by date (descending)
        past.sort((a, b) => {
            const dateA = new Date(a.appointment_date + ' ' + (a.appointment_time || '00:00:00'));
            const dateB = new Date(b.appointment_date + ' ' + (b.appointment_time || '00:00:00'));
            return dateB - dateA;
        });

        let html = '';

        // Render upcoming appointments
        if (upcoming.length > 0) {
            upcoming.forEach(apt => {
                const aptDate = new Date(apt.appointment_date);
                const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                const day = aptDate.getDate();
                const month = months[aptDate.getMonth()];
                
                const timeStr = apt.appointment_time ? formatTime(apt.appointment_time) : 'TBD';
                const statusBadge = getStatusBadge(apt.final_status || apt.status);
                
                html += `
                    <div class="relative overflow-hidden bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark group transition-all hover:shadow-md">
                        <div class="absolute top-0 left-0 w-1.5 h-full bg-primary"></div>
                        <div class="p-5 flex flex-col sm:flex-row gap-5 items-start sm:items-center">
                            <div class="bg-blue-50 dark:bg-blue-900/20 text-primary rounded-xl p-3 min-w-[80px] text-center border border-blue-100 dark:border-blue-900/30">
                                <span class="block text-xs font-bold uppercase tracking-wider mb-0.5">${month}</span>
                                <span class="block text-2xl font-extrabold leading-none">${day}</span>
                                <span class="block text-sm font-medium">${aptDate.getFullYear()}</span>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-1">${escapeHtml(apt.service_type || 'Appointment')}</h4>
                                <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-gray-500 dark:text-gray-400">
                                    <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px]">schedule</span> ${timeStr}</span>
                                    ${apt.created_by_email ? `<span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px]">person</span> ${escapeHtml(apt.created_by_email)}</span>` : ''}
                                </div>
                                ${apt.service_description ? `<p class="text-xs text-gray-500 mt-2">${escapeHtml(apt.service_description)}</p>` : ''}
                            </div>
                            <div class="flex items-center gap-2">
                                ${statusBadge}
                            </div>
                        </div>
                    </div>
                `;
            });
        }

        // Render past appointments
        if (past.length > 0) {
            html += `
                <div class="bg-white dark:bg-surface-dark rounded-xl shadow-sm border border-border-light dark:border-border-dark overflow-hidden">
                    <div class="px-5 py-3 bg-gray-50 dark:bg-[#1f2d3a] border-b border-border-light dark:border-border-dark">
                        <h5 class="text-xs font-bold text-gray-500 uppercase tracking-wider">Past Appointments</h5>
                    </div>
                    <div class="divide-y divide-border-light dark:divide-border-dark">
            `;

            past.forEach(apt => {
                const aptDate = new Date(apt.appointment_date);
                const dateStr = formatDateForDisplay(apt.appointment_date);
                const timeStr = apt.appointment_time ? formatTime(apt.appointment_time) : '';
                const statusBadge = getStatusBadge(apt.final_status || apt.status);
                const doctorName = apt.created_by_email || 'N/A';

                html += `
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-gray-50 dark:hover:bg-[#1f2d3a] transition-colors group">
                        <div class="flex items-start gap-4">
                            <div class="mt-1 size-2 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white text-sm">${escapeHtml(apt.service_type || 'Appointment')}</p>
                                <p class="text-xs text-gray-500 mt-0.5">${dateStr}${timeStr ? ' • ' + timeStr : ''} • ${escapeHtml(doctorName)}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            ${statusBadge}
                        </div>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }

        if (upcoming.length === 0 && past.length === 0) {
            html = '<div class="text-center py-8 text-gray-500"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">calendar_month</span><p>No appointments found</p></div>';
        }

        appointmentsContainer.innerHTML = html;
    }

    // Format time from HH:MM:SS to readable format
    function formatTime(timeStr) {
        if (!timeStr) return '';
        const [hours, minutes] = timeStr.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${minutes} ${ampm}`;
    }

    // Get status badge HTML
    function getStatusBadge(status) {
        const statusLower = (status || '').toLowerCase();
        let bgClass, textClass, borderClass, text;

        switch (statusLower) {
            case 'completed':
                bgClass = 'bg-green-50 dark:bg-green-900/20';
                textClass = 'text-green-700 dark:text-green-400';
                borderClass = 'border-green-100 dark:border-green-900/30';
                text = 'Completed';
                break;
            case 'scheduled':
            case 'confirmed':
                bgClass = 'bg-blue-50 dark:bg-blue-900/20';
                textClass = 'text-blue-700 dark:text-blue-400';
                borderClass = 'border-blue-100 dark:border-blue-900/30';
                text = 'Scheduled';
                break;
            case 'cancelled':
                bgClass = 'bg-red-50 dark:bg-red-900/20';
                textClass = 'text-red-700 dark:text-red-400';
                borderClass = 'border-red-100 dark:border-red-900/30';
                text = 'Cancelled';
                break;
            case 'no_show':
                bgClass = 'bg-orange-50 dark:bg-orange-900/20';
                textClass = 'text-orange-700 dark:text-orange-400';
                borderClass = 'border-orange-100 dark:border-orange-900/30';
                text = 'No Show';
                break;
            default:
                bgClass = 'bg-gray-50 dark:bg-gray-900/20';
                textClass = 'text-gray-700 dark:text-gray-400';
                borderClass = 'border-gray-100 dark:border-gray-900/30';
                text = 'Pending';
        }

        return `<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${bgClass} ${textClass} border ${borderClass}">${text}</span>`;
    }

    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Load active treatments (long-term treatment plans) for current patient
    async function loadActiveTreatments() {
        const container = document.getElementById('activeTreatmentContainer');
        if (!container || !currentPatientId) {
            return;
        }

        // Get patient data to find the patient_id (display ID)
        const patientDataKey = `patient_data_${currentPatientId}`;
        const savedData = localStorage.getItem(patientDataKey);
        
        if (!savedData) {
            container.innerHTML = '<div class="bg-white dark:bg-slate-900 rounded-lg p-6 shadow-sm text-center text-slate-500 dark:text-slate-400">No patient data found</div>';
            return;
        }

        try {
            const patientData = JSON.parse(savedData);
            let patientId = patientData.patientId;
            
            // If currentPatientId looks like a display ID (starts with "PT-"), use it
            if (!patientId && currentPatientId && currentPatientId.toString().startsWith('PT-')) {
                patientId = currentPatientId;
            }
            
            if (!patientId) {
                container.innerHTML = '<div class="bg-white dark:bg-slate-900 rounded-lg p-6 shadow-sm text-center text-slate-500 dark:text-slate-400">Patient ID not found</div>';
                return;
            }

            // Show loading state
            container.innerHTML = '<div class="text-center py-8 text-gray-500 dark:text-gray-400"><span class="material-symbols-outlined text-4xl mb-2 opacity-50 animate-spin">hourglass_empty</span><p>Loading treatment plans...</p></div>';

            // Fetch appointments and payments
            const [appointmentsResponse, paymentsResponse] = await Promise.all([
                fetch(`<?php echo BASE_URL; ?>api/appointments.php?patient_id=${encodeURIComponent(patientId)}`, {
                    method: 'GET',
                    credentials: 'same-origin'
                }),
                fetch('<?php echo BASE_URL; ?>api/payments.php', {
                    method: 'GET',
                    credentials: 'same-origin'
                })
            ]);

            const appointmentsResult = await appointmentsResponse.json();
            const paymentsResult = await paymentsResponse.json();

            if (!appointmentsResult.success || !appointmentsResult.data || !appointmentsResult.data.appointments) {
                container.innerHTML = '<div class="bg-white dark:bg-slate-900 rounded-lg p-6 shadow-sm text-center text-slate-500 dark:text-slate-400">No appointments found</div>';
                return;
            }

            const appointments = appointmentsResult.data.appointments;
            
            // Get all booking_ids with payments
            const paidBookingIds = new Set();
            if (paymentsResult.success && paymentsResult.data && paymentsResult.data.payments) {
                paymentsResult.data.payments.forEach(payment => {
                    if (payment.booking_id) {
                        paidBookingIds.add(payment.booking_id);
                    }
                });
            }

            // Filter for long-term treatments
            const longTermAppointments = appointments.filter(apt => apt.treatment_type === 'long_term');
            
            // Filter long-term treatments: show if they have payments OR paid installments
            const paidLongTermAppointments = [];
            for (const apt of longTermAppointments) {
                const hasPayment = paidBookingIds.has(apt.booking_id);
                const installments = await loadInstallmentsForBooking(apt.booking_id);
                const hasPaidInstallment = installments.length > 0 && installments.some(inst => inst.status === 'paid' || inst.status === 'completed');
                
                if (hasPayment || hasPaidInstallment) {
                    paidLongTermAppointments.push(apt);
                }
            }

            // Display treatments
            await displayActiveTreatments(paidLongTermAppointments, paidBookingIds);
        } catch (error) {
            console.error('Error loading active treatments:', error);
            container.innerHTML = '<div class="bg-white dark:bg-slate-900 rounded-lg p-6 shadow-sm text-center text-red-500 dark:text-red-400">Error loading treatment plans. Please try again.</div>';
        }
    }

    // Load installments for a booking
    async function loadInstallmentsForBooking(bookingId) {
        try {
            const response = await fetch(`<?php echo BASE_URL; ?>api/installments.php?booking_id=${encodeURIComponent(bookingId)}`, {
                method: 'GET',
                credentials: 'same-origin'
            });

            const result = await response.json();
            return result.success && result.data ? result.data.installments : [];
        } catch (error) {
            console.error('Error loading installments:', error);
            return [];
        }
    }

    // Display active treatments
    async function displayActiveTreatments(appointments, paidBookingIds) {
        const container = document.getElementById('activeTreatmentContainer');
        if (!container) return;

        if (appointments.length === 0) {
            container.innerHTML = '<div class="bg-white dark:bg-slate-900 rounded-lg p-6 shadow-sm text-center text-slate-500 dark:text-slate-400">No long-term treatment plans found.</div>';
            return;
        }

        // Load installment data for each appointment
        const treatmentsHtml = await Promise.all(appointments.map(async (apt) => {
            const installments = await loadInstallmentsForBooking(apt.booking_id);
            
            // Calculate progress: for installment-based treatments, use installments
            // For full payment treatments (no installments), check if payment exists
            let progress;
            if (installments.length > 0) {
                // Installment-based treatment (downpayment)
                progress = calculateTreatmentProgress(installments);
            } else {
                // Full payment treatment - check if payment exists
                const hasPayment = paidBookingIds.has(apt.booking_id);
                const totalCost = parseFloat(apt.total_treatment_cost || 0);
                if (hasPayment && totalCost > 0) {
                    progress = {
                        paid: totalCost,
                        total: totalCost,
                        percentage: 100
                    };
                } else {
                    progress = {
                        paid: 0,
                        total: totalCost,
                        percentage: 0
                    };
                }
            }
            
            const startDate = apt.start_date ? new Date(apt.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
            const targetDate = apt.target_completion_date ? new Date(apt.target_completion_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : 'N/A';
            const duration = apt.duration_months ? `${apt.duration_months} Months` : 'N/A';
            const progressPercent = Math.round(progress.percentage);

            return `
                <div class="bg-white dark:bg-slate-900 rounded-lg p-6 shadow-sm">
                    <div class="flex items-start justify-between mb-4">
                        <span class="bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300 px-3 py-1 rounded-full text-xs font-semibold">${escapeHtml(apt.service_type || 'Long-Term Treatment')}</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">${escapeHtml(apt.booking_id)}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">TARGET COMPLETION</p>
                            <p class="text-lg font-bold text-[#0d141b] dark:text-white">${targetDate}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xs text-slate-500 dark:text-slate-400 uppercase tracking-wide mb-1">DURATION</p>
                            <p class="text-lg font-bold text-primary">${duration}</p>
                        </div>
                    </div>
                    <div class="mb-4">
                        <p class="text-sm text-[#0d141b] dark:text-white mb-2">Progress</p>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-2 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
                                <div class="h-full bg-primary rounded-full" style="width: ${progressPercent}%"></div>
                            </div>
                            <span class="text-xs text-slate-500 dark:text-slate-400">${progressPercent}%</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm text-[#0d141b] dark:text-white">Started: ${startDate}</span>
                        <span class="text-sm text-slate-500 dark:text-slate-400">${getAppointmentStatusText(apt.status)}</span>
                    </div>
                    <button onclick="openTreatmentProgressModalAdmin('${escapeHtml(apt.booking_id)}')" class="w-full bg-purple-50 dark:bg-purple-900/30 text-primary px-4 py-2.5 rounded-lg font-semibold text-sm flex items-center justify-center gap-2 hover:bg-purple-100 dark:hover:bg-purple-900/50 transition-all">
                        <span class="material-symbols-outlined text-sm">calendar_month</span>
                        View Treatment Progress
                    </button>
                </div>
            `;
        }));

        container.innerHTML = treatmentsHtml.join('');
    }

    // Calculate progress from installments
    function calculateTreatmentProgress(installments) {
        if (!installments || installments.length === 0) {
            return { paid: 0, total: 0, percentage: 0 };
        }

        let totalPaid = 0;
        let totalDue = 0;

        installments.forEach(inst => {
            totalDue += parseFloat(inst.amount_due || 0);
            if (inst.status === 'paid' || inst.status === 'completed') {
                totalPaid += parseFloat(inst.amount_due || 0);
            }
        });

        const percentage = totalDue > 0 ? (totalPaid / totalDue) * 100 : 0;

        return {
            paid: totalPaid,
            total: totalDue,
            percentage: percentage
        };
    }

    // Get appointment status text
    function getAppointmentStatusText(status) {
        const texts = {
            'pending': 'Pending',
            'confirmed': 'Ongoing',
            'completed': 'Completed',
            'cancelled': 'Cancelled',
            'no_show': 'No Show'
        };
        return texts[status] || 'Pending';
    }

    // Treatment Progress Modal (Admin) - Store booking ID
    let treatmentProgressBookingIdAdmin = null;

    // Open treatment progress modal (admin version - no book action)
    async function openTreatmentProgressModalAdmin(bookingId) {
        treatmentProgressBookingIdAdmin = bookingId;
        const modal = document.getElementById('treatmentProgressModalAdmin');
        if (modal) {
            // Clear previous data before loading new
            const tbody = document.querySelector('#treatmentProgressTableAdmin tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">Loading...</td></tr>';
            }
            
            // Reset progress bar
            const progressBar = document.querySelector('#totalProgressBarAdmin');
            const progressText = document.querySelector('#totalProgressTextAdmin');
            const progressAmount = document.querySelector('#totalProgressAmountAdmin');
            if (progressBar) progressBar.style.width = '0%';
            if (progressText) progressText.textContent = '0% Paid';
            if (progressAmount) {
                progressAmount.innerHTML = '<span class="text-3xl font-bold">₱0.00</span><span class="text-sm opacity-80">/ ₱0.00</span>';
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.style.overflow = 'hidden';
            
            // Load treatment progress data
            if (bookingId) {
                await loadTreatmentProgressAdmin(bookingId);
            }
        }
    }

    function closeTreatmentProgressModalAdmin() {
        const modal = document.getElementById('treatmentProgressModalAdmin');
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.style.overflow = '';
        }
        treatmentProgressBookingIdAdmin = null;
    }

    // Load treatment progress data (admin version)
    async function loadTreatmentProgressAdmin(bookingId) {
        try {
            // Load installments
            const installmentsResponse = await fetch(`<?php echo BASE_URL; ?>api/installments.php?booking_id=${encodeURIComponent(bookingId)}`, {
                method: 'GET',
                credentials: 'same-origin'
            });

            const installmentsResult = await installmentsResponse.json();
            const installments = installmentsResult.success && installmentsResult.data ? installmentsResult.data.installments : [];
            
            // Load appointment to get total cost, duration, appointment_date, appointment_time, and service_type
            const appointmentResponse = await fetch(`<?php echo BASE_URL; ?>api/appointments.php?booking_id=${encodeURIComponent(bookingId)}`, {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            const appointmentResult = await appointmentResponse.json();
            let totalCost = 0;
            let durationMonths = 18; // Default 18 months for long-term treatments
            let appointment = null;
            let appointmentDate = null;
            let appointmentTime = null;
            let serviceType = null;
            
            if (appointmentResult.success && appointmentResult.data && appointmentResult.data.appointments && appointmentResult.data.appointments.length > 0) {
                appointment = appointmentResult.data.appointments[0];
                totalCost = parseFloat(appointment.total_treatment_cost || 0);
                durationMonths = parseInt(appointment.duration_months || 18);
                appointmentDate = appointment.appointment_date || null;
                appointmentTime = appointment.appointment_time || null;
                serviceType = appointment.service_type || null;
            }
            
            // Check if this booking has payments (for full payment treatments)
            let hasPayment = false;
            try {
                const paymentsResponse = await fetch(`<?php echo BASE_URL; ?>api/payments.php?booking_id=${encodeURIComponent(bookingId)}`, {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                const paymentsResult = await paymentsResponse.json();
                if (paymentsResult.success && paymentsResult.data && paymentsResult.data.payments) {
                    hasPayment = paymentsResult.data.payments.length > 0;
                }
            } catch (error) {
                console.error('Error checking payments:', error);
            }
            
            // If no installments but has total cost, this is a full payment treatment
            if (installments.length === 0 && totalCost > 0) {
                // For full payment treatments, create installment entries for all months (18 months)
                const fullPaymentInstallments = [];
                
                for (let i = 1; i <= durationMonths; i++) {
                    let status = 'pending';
                    if (i === 1) {
                        status = hasPayment ? 'paid' : 'pending';
                    } else if (i === 2 && hasPayment) {
                        status = 'book_visit';
                    }
                    
                    fullPaymentInstallments.push({
                        installment_number: i,
                        amount_due: i === 1 ? totalCost : 0,
                        status: status,
                        scheduled_date: i === 1 ? appointmentDate : null,
                        scheduled_time: i === 1 ? appointmentTime : null
                    });
                }
                
                displayTreatmentProgressAdmin(fullPaymentInstallments, totalCost, appointmentDate, appointmentTime, serviceType, bookingId);
            } else if (installments.length > 0) {
                // Installment-based treatment - use actual installments from database
                displayTreatmentProgressAdmin(installments, totalCost, appointmentDate, appointmentTime, serviceType, bookingId);
            } else {
                // Show error message in modal
                const tbody = document.querySelector('#treatmentProgressTableAdmin tbody');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">No treatment data available for this booking.</td></tr>';
                }
            }
        } catch (error) {
            console.error('Error loading treatment progress:', error);
            // Show error message in modal
            const tbody = document.querySelector('#treatmentProgressTableAdmin tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-red-500 dark:text-red-400">Error loading treatment progress. Please try again.</td></tr>';
            }
        }
    }

    // Display treatment progress in modal (admin version - no book action)
    function displayTreatmentProgressAdmin(installments, totalCost, appointmentDate, appointmentTime, serviceType, bookingId) {
        const tbody = document.querySelector('#treatmentProgressTableAdmin tbody');
        if (!tbody) {
            console.error('Treatment progress table body not found');
            return;
        }
        
        if (!installments || installments.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">No installments found.</td></tr>';
            return;
        }

        // Calculate total paid
        let totalPaid = 0;
        installments.forEach(inst => {
            if (inst.status === 'paid' || inst.status === 'completed') {
                totalPaid += parseFloat(inst.amount_due || 0);
            }
        });

        const progressPercent = totalCost > 0 ? (totalPaid / totalCost) * 100 : 0;

        // Update progress bar
        const progressBar = document.querySelector('#totalProgressBarAdmin');
        const progressText = document.querySelector('#totalProgressTextAdmin');
        const progressAmount = document.querySelector('#totalProgressAmountAdmin');
        
        if (progressBar) {
            progressBar.style.width = `${Math.round(progressPercent)}%`;
        }
        if (progressText) {
            progressText.textContent = `${Math.round(progressPercent)}% Paid`;
        }
        if (progressAmount) {
            progressAmount.innerHTML = `
                <span class="text-3xl font-bold">₱${totalPaid.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                <span class="text-sm opacity-80">/ ₱${parseFloat(totalCost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
            `;
        }

        // Display installments (admin version - no action column)
        tbody.innerHTML = installments.map((inst, index) => {
            const statusClass = getInstallmentStatusClassAdmin(inst.status);
            const statusText = getInstallmentStatusTextAdmin(inst.status);
            
            // For Treatment 1, use appointment_date and appointment_time from appointments table
            let displayDate = '-';
            let displayTime = '-';
            if (inst.installment_number === 1) {
                if (appointmentDate) {
                    displayDate = new Date(appointmentDate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                }
                if (appointmentTime) {
                    displayTime = appointmentTime.substring(0, 5);
                }
            } else {
                // Treatment 2+: Use scheduled_date and scheduled_time from installments table
                if (inst.scheduled_date) {
                    displayDate = new Date(inst.scheduled_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                }
                if (inst.scheduled_time) {
                    displayTime = inst.scheduled_time.substring(0, 5);
                }
            }
            
            const amount = parseFloat(inst.amount_due || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            return `
                <tr class="${index % 2 === 0 ? 'bg-white dark:bg-slate-900' : 'bg-slate-50/50 dark:bg-slate-800/30'} hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-6 py-4 text-sm font-medium text-[#0d141b] dark:text-white">Treatment ${inst.installment_number}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center gap-1.5 ${statusClass} px-3 py-1 rounded-full text-xs font-semibold">
                            ${getStatusIconAdmin(inst.status)}
                            ${statusText}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">${displayDate}</td>
                    <td class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">${displayTime}</td>
                    <td class="px-6 py-4 text-sm font-semibold ${inst.status === 'paid' || inst.status === 'completed' ? 'text-green-600 dark:text-green-400' : 'text-[#0d141b] dark:text-white'}">₱${amount}</td>
                </tr>
            `;
        }).join('');
    }

    function getInstallmentStatusClassAdmin(status) {
        const classes = {
            'pending': 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
            'paid': 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300',
            'book_visit': 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-300',
            'locked': 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300',
            'completed': 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300'
        };
        return classes[status] || classes['pending'];
    }

    function getInstallmentStatusTextAdmin(status) {
        const texts = {
            'pending': 'Pending',
            'paid': 'Paid',
            'book_visit': 'Book Visit',
            'locked': 'Locked',
            'completed': 'Completed'
        };
        return texts[status] || 'Pending';
    }

    function getStatusIconAdmin(status) {
        const icons = {
            'paid': '<span class="material-symbols-outlined text-sm">check_circle</span>',
            'book_visit': '<span class="material-symbols-outlined text-sm">event</span>',
            'locked': '<span class="material-symbols-outlined text-sm">lock</span>',
            'completed': '<span class="material-symbols-outlined text-sm">check_circle</span>'
        };
        return icons[status] || '';
    }

    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('treatmentProgressModalAdmin');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeTreatmentProgressModalAdmin();
                }
            });
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('treatmentProgressModalAdmin');
            if (modal && !modal.classList.contains('hidden')) {
                closeTreatmentProgressModalAdmin();
            }
        }
    });

    // Load files for current patient
    function loadFiles() {
        const filesContainer = document.getElementById('filesContainer');
        if (!filesContainer || !currentPatientId) {
            return;
        }

        // Get patient data to find the patient_id (display ID)
        const patientDataKey = `patient_data_${currentPatientId}`;
        const savedData = localStorage.getItem(patientDataKey);
        
        if (!savedData) {
            filesContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">folder_open</span><p>No patient data found</p></div>';
            return;
        }

        try {
            const patientData = JSON.parse(savedData);
            // Use patientId (display ID) if available, otherwise check if currentPatientId is a display ID
            let patientId = patientData.patientId;
            
            // If currentPatientId looks like a display ID (starts with "PT-"), use it
            if (!patientId && currentPatientId && currentPatientId.toString().startsWith('PT-')) {
                patientId = currentPatientId;
            }
            
            // Fallback: if still no patientId, we can't fetch files
            if (!patientId) {
                filesContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">folder_open</span><p>Patient ID not found</p></div>';
                return;
            }

            // Show loading state
            filesContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><span class="material-symbols-outlined text-4xl mb-2 opacity-50 animate-spin">hourglass_empty</span><p>Loading files...</p></div>';

            // Fetch files from API
            fetch(`<?php echo BASE_URL; ?>api/patient_files.php?patient_id=${encodeURIComponent(patientId)}`, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data && data.data.files) {
                    renderFiles(data.data.files);
                } else {
                    filesContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">folder_open</span><p>No files found</p></div>';
                }
            })
            .catch(error => {
                console.error('Error loading files:', error);
                filesContainer.innerHTML = '<div class="text-center py-8 text-red-500 col-span-full"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">error</span><p>Error loading files. Please try again.</p></div>';
            });
        } catch (e) {
            console.error('Error parsing patient data:', e);
            filesContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">folder_open</span><p>Error loading patient data</p></div>';
        }
    }

    // Render files
    function renderFiles(files) {
        const filesContainer = document.getElementById('filesContainer');
        const viewAllLink = document.getElementById('viewAllFilesLink');
        
        if (!filesContainer) return;

        if (!files || files.length === 0) {
            filesContainer.innerHTML = '<div class="text-center py-8 text-gray-500 col-span-full"><span class="material-symbols-outlined text-4xl mb-2 opacity-50">folder_open</span><p>No files found</p></div>';
            if (viewAllLink) viewAllLink.textContent = 'View All (0)';
            return;
        }

        // Update "View All" link
        if (viewAllLink) {
            viewAllLink.textContent = `View All (${files.length})`;
        }

        let html = '';

        // Render each file
        files.forEach(file => {
            const fileUrl = (file.file_url && String(file.file_url).trim() !== '')
                ? String(file.file_url)
                : `<?php echo BASE_URL; ?>${file.file_path}`;
            const fileSize = formatFileSize(file.file_size);
            // Format upload date (handle datetime format)
            const uploadDate = formatFileDate(file.created_at);
            const fileIcon = getFileIcon(file.file_type, file.file_name);
            const isImage = file.file_type && file.file_type.startsWith('image/');

            html += `
                <div class="group relative bg-white dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark overflow-hidden hover:shadow-md transition-all">
                    <div class="aspect-[4/3] bg-gray-100 dark:bg-[#1f2d3a] flex items-center justify-center border-b border-border-light dark:border-border-dark relative overflow-hidden">
                        ${isImage ? `
                            <img src="${fileUrl}" alt="${escapeHtml(file.file_name)}" class="file-preview w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
                            <div class="image-placeholder absolute inset-0 flex items-center justify-center">
                                <span class="material-symbols-outlined text-4xl text-gray-400 group-hover:text-primary transition-colors">${fileIcon}</span>
                            </div>
                        ` : `
                            <span class="material-symbols-outlined text-4xl text-gray-400 group-hover:text-primary transition-colors">${fileIcon}</span>
                        `}
                    </div>
                    <div class="p-3">
                        <p class="font-semibold text-sm text-gray-900 dark:text-white truncate" title="${escapeHtml(file.file_name)}">${escapeHtml(file.file_name)}</p>
                        <p class="text-xs text-gray-500 mt-0.5">${fileSize} • ${uploadDate}</p>
                    </div>
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2 backdrop-blur-[1px]">
                        <a href="${fileUrl}" target="_blank" class="size-8 flex items-center justify-center bg-white rounded-full text-gray-900 hover:text-primary shadow-sm" title="View"><span class="material-symbols-outlined text-[18px]">visibility</span></a>
                        <a href="${fileUrl}" download="${escapeHtml(file.file_name)}" class="size-8 flex items-center justify-center bg-white rounded-full text-gray-900 hover:text-primary shadow-sm" title="Download"><span class="material-symbols-outlined text-[18px]">download</span></a>
                    </div>
                </div>
            `;
        });

        filesContainer.innerHTML = html;
    }

    // Get file icon based on file type
    function getFileIcon(fileType, fileName) {
        if (!fileType) {
            const ext = fileName ? fileName.split('.').pop().toLowerCase() : '';
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) return 'image';
            if (ext === 'pdf') return 'picture_as_pdf';
            if (['doc', 'docx'].includes(ext)) return 'description';
            if (['xls', 'xlsx'].includes(ext)) return 'table_chart';
            return 'insert_drive_file';
        }

        if (fileType.startsWith('image/')) return 'image';
        if (fileType === 'application/pdf') return 'picture_as_pdf';
        if (fileType.includes('wordprocessingml') || fileType.includes('msword')) return 'description';
        if (fileType.includes('spreadsheetml') || fileType.includes('ms-excel')) return 'table_chart';
        return 'insert_drive_file';
    }

    // Format file size
    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    // Format file date (shorter format: "Oct 24" or "Jan 15")
    function formatFileDate(dateString) {
        if (!dateString) return '';
        try {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return '';
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${months[date.getMonth()]} ${date.getDate()}`;
        } catch (e) {
            return '';
        }
    }

    // Add click event listeners to tab buttons
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabName = btn.getAttribute('data-tab');
            switchTab(tabName);
        });
    });

    // Edit Basic Info functionality
    const editPatientBtn = document.getElementById('editPatientBtn');
    const cancelEditBtn = document.getElementById('cancelEditBtn');
    const saveEditBtn = document.getElementById('saveEditBtn');
    const editActions = document.getElementById('editActions');
    
    // Display elements
    const firstNameDisplay = document.getElementById('firstNameDisplay');
    const lastNameDisplay = document.getElementById('lastNameDisplay');
    const contactDisplay = document.getElementById('contactDisplay');
    const emailDisplay = document.getElementById('emailDisplay');
    const dobDisplay = document.getElementById('dobDisplay');
    const genderDisplay = document.getElementById('genderDisplay');
    const houseStreetDisplay = document.getElementById('houseStreetDisplay');
    const barangayDisplay = document.getElementById('barangayDisplay');
    const cityDisplay = document.getElementById('cityDisplay');
    const provinceDisplay = document.getElementById('provinceDisplay');
    
    // Input elements
    const firstNameInput = document.getElementById('firstNameInput');
    const lastNameInput = document.getElementById('lastNameInput');
    const contactInput = document.getElementById('contactInput');
    const emailInput = document.getElementById('emailInput');
    const dobInput = document.getElementById('dobInput');
    const genderInput = document.getElementById('genderInput');
    const houseStreetInput = document.getElementById('houseStreetInput');
    const barangayInput = document.getElementById('barangayInput');
    const cityInput = document.getElementById('cityInput');
    const provinceInput = document.getElementById('provinceInput');
    
    let originalValues = {};
    
    // Format date for display (from YYYY-MM-DD to "Jan 15, 1995")
    function formatDateForDisplay(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    }
    
    // Format date for input (from "Jan 15, 1995" to YYYY-MM-DD)
    function formatDateForInput(dateString) {
        if (!dateString) return '';
        try {
            // Parse "Jan 15, 1995" format
            const months = {
                'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04', 'May': '05', 'Jun': '06',
                'Jul': '07', 'Aug': '08', 'Sep': '09', 'Oct': '10', 'Nov': '11', 'Dec': '12'
            };
            const parts = dateString.trim().split(' ');
            if (parts.length === 3) {
                const month = months[parts[0]];
                const day = parts[1].replace(',', '').padStart(2, '0');
                const year = parts[2];
                return `${year}-${month}-${day}`;
            }
            // Fallback to Date parsing
            const date = new Date(dateString);
            if (!isNaN(date.getTime())) {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            }
            return '';
        } catch (e) {
            return '';
        }
    }
    
    // Helper function to extract text from display (removes icon text)
    function extractDisplayText(displayElement) {
        const spans = displayElement.querySelectorAll('span');
        if (spans.length > 1) {
            // Return text from the last span (the actual value)
            return spans[spans.length - 1].textContent.trim();
        }
        // Fallback: get all text and remove icon-related text
        let text = displayElement.textContent.trim();
        // Remove common icon text
        text = text.replace(/call|mail|cake|wc|home|location_city|apartment|map/gi, '').trim();
        return text;
    }
    
    // Enter edit mode
    function enterEditMode() {
        // Store original values
        originalValues = {
            firstName: firstNameDisplay.textContent.trim(),
            lastName: lastNameDisplay.textContent.trim(),
            contact: extractDisplayText(contactDisplay),
            email: extractDisplayText(emailDisplay),
            dob: formatDateForInput(extractDisplayText(dobDisplay)),
            gender: extractDisplayText(genderDisplay),
            houseStreet: extractDisplayText(houseStreetDisplay),
            barangay: extractDisplayText(barangayDisplay),
            city: extractDisplayText(cityDisplay),
            province: extractDisplayText(provinceDisplay)
        };
        
        // Set input values
        firstNameInput.value = originalValues.firstName;
        lastNameInput.value = originalValues.lastName;
        contactInput.value = originalValues.contact;
        emailInput.value = originalValues.email;
        dobInput.value = originalValues.dob;
        genderInput.value = originalValues.gender;
        houseStreetInput.value = originalValues.houseStreet;
        barangayInput.value = originalValues.barangay;
        cityInput.value = originalValues.city;
        provinceInput.value = originalValues.province;
        
        // Hide displays, show inputs
        firstNameDisplay.classList.add('hidden');
        lastNameDisplay.classList.add('hidden');
        contactDisplay.classList.add('hidden');
        emailDisplay.classList.add('hidden');
        dobDisplay.classList.add('hidden');
        genderDisplay.classList.add('hidden');
        houseStreetDisplay.classList.add('hidden');
        barangayDisplay.classList.add('hidden');
        cityDisplay.classList.add('hidden');
        provinceDisplay.classList.add('hidden');
        
        firstNameInput.classList.remove('hidden');
        lastNameInput.classList.remove('hidden');
        contactInput.classList.remove('hidden');
        emailInput.classList.remove('hidden');
        dobInput.classList.remove('hidden');
        genderInput.classList.remove('hidden');
        houseStreetInput.classList.remove('hidden');
        barangayInput.classList.remove('hidden');
        cityInput.classList.remove('hidden');
        provinceInput.classList.remove('hidden');
        
        // Show action buttons
        editActions.classList.remove('hidden');
        
        // Update edit button
        if (editPatientBtn) {
            editPatientBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">close</span> Cancel';
            editPatientBtn.classList.add('text-red-500', 'hover:text-red-600');
        }
        
        // Focus first input
        firstNameInput.focus();
    }
    
    // Exit edit mode
    function exitEditMode() {
        // Show displays, hide inputs
        firstNameDisplay.classList.remove('hidden');
        lastNameDisplay.classList.remove('hidden');
        contactDisplay.classList.remove('hidden');
        emailDisplay.classList.remove('hidden');
        dobDisplay.classList.remove('hidden');
        genderDisplay.classList.remove('hidden');
        houseStreetDisplay.classList.remove('hidden');
        barangayDisplay.classList.remove('hidden');
        cityDisplay.classList.remove('hidden');
        provinceDisplay.classList.remove('hidden');
        
        firstNameInput.classList.add('hidden');
        lastNameInput.classList.add('hidden');
        contactInput.classList.add('hidden');
        emailInput.classList.add('hidden');
        dobInput.classList.add('hidden');
        genderInput.classList.add('hidden');
        houseStreetInput.classList.add('hidden');
        barangayInput.classList.add('hidden');
        cityInput.classList.add('hidden');
        provinceInput.classList.add('hidden');
        
        // Hide action buttons
        editActions.classList.add('hidden');
        
        // Restore edit button
        if (editPatientBtn) {
            editPatientBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">edit_square</span> Edit';
            editPatientBtn.classList.remove('text-red-500', 'hover:text-red-600');
        }
    }
    
    // Save changes
    function saveChanges() {
        // Get new values
        const newValues = {
            firstName: firstNameInput.value.trim(),
            lastName: lastNameInput.value.trim(),
            contact: contactInput.value.trim(),
            email: emailInput.value.trim(),
            dob: dobInput.value,
            gender: genderInput.value,
            houseStreet: houseStreetInput.value.trim(),
            barangay: barangayInput.value.trim(),
            city: cityInput.value.trim(),
            province: provinceInput.value.trim()
        };
        
        // Validate required fields
        if (!newValues.firstName || !newValues.lastName || !newValues.contact || !newValues.email || !newValues.houseStreet || !newValues.barangay || !newValues.city || !newValues.province) {
            alert('Please fill in all required fields.');
            return;
        }
        
        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(newValues.email)) {
            alert('Please enter a valid email address.');
            return;
        }
        
        // Update display values
        firstNameDisplay.textContent = newValues.firstName;
        lastNameDisplay.textContent = newValues.lastName;
        const contactSpan = contactDisplay.querySelector('span:last-child');
        if (contactSpan) contactSpan.textContent = newValues.contact;
        const emailSpan = emailDisplay.querySelector('span:last-child');
        if (emailSpan) emailSpan.textContent = newValues.email;
        const dobSpan = dobDisplay.querySelector('span:last-child');
        if (dobSpan) dobSpan.textContent = formatDateForDisplay(newValues.dob);
        const genderSpan = genderDisplay.querySelector('span:last-child');
        if (genderSpan) genderSpan.textContent = newValues.gender;
        const houseStreetSpan = houseStreetDisplay.querySelector('span:last-child');
        if (houseStreetSpan) houseStreetSpan.textContent = newValues.houseStreet || 'N/A';
        const barangaySpan = barangayDisplay.querySelector('span:last-child');
        if (barangaySpan) barangaySpan.textContent = newValues.barangay || 'N/A';
        const citySpan = cityDisplay.querySelector('span:last-child');
        if (citySpan) citySpan.textContent = newValues.city || 'N/A';
        const provinceSpan = provinceDisplay.querySelector('span:last-child');
        if (provinceSpan) provinceSpan.textContent = newValues.province || 'N/A';
        
        // Save to localStorage (using patient ID)
        const patientId = currentPatientId;
        const patientDataKey = `patient_data_${patientId}`;
        const patientData = {
            firstName: newValues.firstName,
            lastName: newValues.lastName,
            contact: newValues.contact,
            email: newValues.email,
            dob: newValues.dob,
            gender: newValues.gender,
            houseStreet: newValues.houseStreet,
            barangay: newValues.barangay,
            city: newValues.city,
            province: newValues.province
        };
        localStorage.setItem(patientDataKey, JSON.stringify(patientData));
        
        // Exit edit mode
        exitEditMode();
        
        // Show success message (optional)
        const saveBtn = document.getElementById('saveEditBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check</span> Saved!';
        saveBtn.classList.add('bg-green-500', 'hover:bg-green-600');
        setTimeout(() => {
            saveBtn.innerHTML = originalText;
            saveBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
        }, 2000);
    }
    
    // Load saved data
    function loadPatientData() {
        const patientId = currentPatientId;
        const patientDataKey = `patient_data_${patientId}`;
        const savedData = localStorage.getItem(patientDataKey);
        
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                if (data.firstName) firstNameDisplay.textContent = data.firstName;
                if (data.lastName) lastNameDisplay.textContent = data.lastName;
                const contactSpan = contactDisplay.querySelector('span:last-child');
                if (data.contact && contactSpan) contactSpan.textContent = data.contact;
                const emailSpan = emailDisplay.querySelector('span:last-child');
                if (data.email && emailSpan) emailSpan.textContent = data.email;
                const dobSpan = dobDisplay.querySelector('span:last-child');
                if (data.dob && dobSpan) dobSpan.textContent = formatDateForDisplay(data.dob);
                const genderSpan = genderDisplay.querySelector('span:last-child');
                if (data.gender && genderSpan) genderSpan.textContent = data.gender;
                const houseStreetSpan = houseStreetDisplay?.querySelector('span:last-child');
                if (data.houseStreet && houseStreetSpan) houseStreetSpan.textContent = data.houseStreet;
                const barangaySpan = barangayDisplay?.querySelector('span:last-child');
                if (data.barangay && barangaySpan) barangaySpan.textContent = data.barangay;
                const citySpan = cityDisplay?.querySelector('span:last-child');
                if (data.city && citySpan) citySpan.textContent = data.city;
                const provinceSpan = provinceDisplay?.querySelector('span:last-child');
                if (data.province && provinceSpan) provinceSpan.textContent = data.province;
            } catch (e) {
                console.error('Error loading patient data:', e);
            }
        }
    }
    
    // Event listeners
    if (editPatientBtn) {
        editPatientBtn.addEventListener('click', () => {
            if (editActions.classList.contains('hidden')) {
                enterEditMode();
            } else {
                exitEditMode();
            }
        });
    }
    
    if (cancelEditBtn) {
        cancelEditBtn.addEventListener('click', () => {
            // Restore original values
            firstNameInput.value = originalValues.firstName;
            lastNameInput.value = originalValues.lastName;
            contactInput.value = originalValues.contact;
            emailInput.value = originalValues.email;
            dobInput.value = originalValues.dob;
            genderInput.value = originalValues.gender;
            houseStreetInput.value = originalValues.houseStreet || '';
            barangayInput.value = originalValues.barangay || '';
            cityInput.value = originalValues.city || '';
            provinceInput.value = originalValues.province || '';
            
            exitEditMode();
        });
    }
    
    if (saveEditBtn) {
        saveEditBtn.addEventListener('click', saveChanges);
    }
    
    // Load saved data on page load
    loadPatientData();

    // Add New Patient functionality
    const addNewPatientBtn = document.getElementById('addNewPatientBtn');
    const addPatientModal = document.getElementById('addPatientModal');
    const closeAddPatientModal = document.getElementById('closeAddPatientModal');
    const cancelAddPatientBtn = document.getElementById('cancelAddPatientBtn');
    const addPatientForm = document.getElementById('addPatientForm');
    const addPatientPhoto = document.getElementById('addPatientPhoto');
    const addPatientPhotoBtn = document.getElementById('addPatientPhotoBtn');
    const addPatientPhotoPreview = document.getElementById('addPatientPhotoPreview');
    const addPatientPhotoImg = document.getElementById('addPatientPhotoImg');
    const removePatientPhotoBtn = document.getElementById('removePatientPhotoBtn');
    let patientPhotoData = null; // Store base64 photo data
    
    // Photo upload handling
    if (addPatientPhotoBtn) {
        addPatientPhotoBtn.addEventListener('click', () => {
            if (addPatientPhoto) {
                addPatientPhoto.click();
            }
        });
    }
    
    if (addPatientPhotoPreview) {
        addPatientPhotoPreview.addEventListener('click', () => {
            if (addPatientPhoto) {
                addPatientPhoto.click();
            }
        });
    }
    
    if (addPatientPhoto) {
        addPatientPhoto.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file.');
                    addPatientPhoto.value = '';
                    return;
                }
                
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size must be less than 5MB.');
                    addPatientPhoto.value = '';
                    return;
                }
                
                // Read file as base64
                const reader = new FileReader();
                reader.onload = (event) => {
                    patientPhotoData = event.target.result;
                    // Show preview
                    if (addPatientPhotoImg) {
                        addPatientPhotoImg.src = patientPhotoData;
                        addPatientPhotoImg.classList.remove('hidden');
                        addPatientPhotoPreview.querySelector('.material-symbols-outlined')?.classList.add('hidden');
                    }
                    // Show remove button
                    if (removePatientPhotoBtn) {
                        removePatientPhotoBtn.classList.remove('hidden');
                    }
                };
                reader.onerror = () => {
                    alert('Error reading file. Please try again.');
                    addPatientPhoto.value = '';
                    patientPhotoData = null;
                };
                reader.readAsDataURL(file);
            }
        });
    }
    
    if (removePatientPhotoBtn) {
        removePatientPhotoBtn.addEventListener('click', () => {
            patientPhotoData = null;
            if (addPatientPhoto) {
                addPatientPhoto.value = '';
            }
            if (addPatientPhotoImg) {
                addPatientPhotoImg.src = '';
                addPatientPhotoImg.classList.add('hidden');
                addPatientPhotoPreview.querySelector('.material-symbols-outlined')?.classList.remove('hidden');
            }
            if (removePatientPhotoBtn) {
                removePatientPhotoBtn.classList.add('hidden');
            }
        });
    }
    
    // Generate unique patient ID
    function generatePatientId() {
        const existingIds = [];
        // Get all existing patient IDs from localStorage
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.startsWith('patient_data_')) {
                const patientId = key.replace('patient_data_', '');
                existingIds.push(patientId);
            }
        }
        
        // Also check table rows
        const tableRows = document.querySelectorAll('tbody tr');
        tableRows.forEach(row => {
            const idCell = row.querySelector('td:nth-child(2)');
            if (idCell) {
                const id = idCell.textContent.trim().replace('#', '');
                if (id && !existingIds.includes(id)) {
                    existingIds.push(id);
                }
            }
        });
        
        // Generate new ID
        let newId;
        let counter = 1000;
        do {
            const randomNum = Math.floor(Math.random() * 9000) + 1000;
            newId = `PT-${randomNum}`;
            counter--;
        } while (existingIds.includes(newId) && counter > 0);
        
        return newId;
    }
    
    // Format date for display
    function formatDateDisplay(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
    }
    
    // Calculate age from date of birth
    function calculateAge(dob) {
        if (!dob) return '';
        const today = new Date();
        const birthDate = new Date(dob);
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
    }
    
    // Get gender icon
    function getGenderIcon(gender) {
        if (gender === 'Male') return 'male';
        if (gender === 'Female') return 'female';
        return 'wc';
    }
    
    // Create table row for new patient
    function createPatientRow(patientData) {
        const row = document.createElement('tr');
        row.className = 'group hover:bg-primary/5 dark:hover:bg-primary/10 transition-colors cursor-pointer';
        row.setAttribute('data-patient-id', patientData.id);
        
        const age = calculateAge(patientData.dob);
        // Format gender and age - show gender if it exists, otherwise just show age
        const gender = patientData.gender ? String(patientData.gender).trim() : '';
        const genderText = gender ? `${gender}, ${age}` : `${age}`;
        
        // Use uploaded photo if available, otherwise generate avatar URL
        const fullName = `${patientData.firstName} ${patientData.lastName}`.trim() || 'Patient';
        const avatarUrl = patientData.photo || `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=2563eb&color=fff&size=80`;
        const safeUrl = (avatarUrl || '').replace(/"/g, '%22');
        
        row.innerHTML = `
            <td class="py-4 px-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                    <div class="size-10 rounded-full bg-cover bg-center ring-2 ring-primary/20 dark:ring-primary/40" data-alt="Portrait of ${fullName.replace(/"/g, '&quot;')}" style='background-image: url("${safeUrl}");'></div>
                    <div>
                        <p class="font-bold text-gray-900 dark:text-white">${patientData.firstName} ${patientData.lastName}</p>
                        <p class="text-xs text-gray-500">${genderText}</p>
                    </div>
                </div>
            </td>
            <td class="py-4 px-4 whitespace-nowrap text-sm font-medium text-gray-600 dark:text-gray-300 font-mono">#${patientData.id}</td>
            <td class="py-4 px-4 whitespace-nowrap">
                <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-1.5 text-sm text-gray-600 dark:text-gray-300">
                        <span class="material-symbols-outlined text-[16px] text-gray-400">call</span>
                        ${patientData.contact}
                    </div>
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="material-symbols-outlined text-[16px] text-gray-400">mail</span>
                        ${patientData.email}
                    </div>
                </div>
            </td>
            <td class="py-4 px-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">${formatDateDisplay(patientData.lastVisit || patientData.createdAt || new Date().toISOString())}</td>
            <td class="py-4 px-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300 border border-green-200 dark:border-green-800">
                    Active
                </span>
            </td>
            <td class="py-4 px-4 whitespace-nowrap text-right">
                <button class="view-patient-btn text-primary hover:text-primary-hover transition-colors p-2 bg-primary/10 rounded-full" data-patient-id="${patientData.id}">
                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                </button>
            </td>
        `;
        
        // Add click event to view button
        const viewBtn = row.querySelector('.view-patient-btn');
        if (viewBtn) {
            viewBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                // Load patient data and open detail panel
                loadPatientIntoDetailPanel(patientData.id);
                openPatientDetail();
            });
        }
        
        return row;
    }
    
    // Open add patient modal
    function openAddPatientModal() {
        if (addPatientModal) {
            addPatientModal.classList.remove('hidden');
            addPatientModal.classList.add('flex');
            // Focus first input
            setTimeout(() => {
                const firstInput = document.getElementById('addFirstName');
                if (firstInput) firstInput.focus();
            }, 100);
        }
    }
    
    // Close add patient modal
    function closeAddPatientModalFunc() {
        if (addPatientModal) {
            addPatientModal.classList.add('hidden');
            addPatientModal.classList.remove('flex');
            // Reset form
            if (addPatientForm) {
                addPatientForm.reset();
            }
            // Reset photo
            patientPhotoData = null;
            if (addPatientPhoto) {
                addPatientPhoto.value = '';
            }
            if (addPatientPhotoImg) {
                addPatientPhotoImg.src = '';
                addPatientPhotoImg.classList.add('hidden');
                if (addPatientPhotoPreview) {
                    const icon = addPatientPhotoPreview.querySelector('.material-symbols-outlined');
                    if (icon) icon.classList.remove('hidden');
                }
            }
            if (removePatientPhotoBtn) {
                removePatientPhotoBtn.classList.add('hidden');
            }
        }
    }
    
    // Handle form submission
    if (addPatientForm) {
        addPatientForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Get form values
            const firstName = document.getElementById('addFirstName').value.trim();
            const lastName = document.getElementById('addLastName').value.trim();
            const contact = document.getElementById('addContact').value.trim();
            const email = document.getElementById('addEmail').value.trim();
            const dob = document.getElementById('addDob').value;
            const gender = document.getElementById('addGender').value;
            const houseStreet = document.getElementById('addHouseStreet').value.trim();
            const barangay = document.getElementById('addBarangay').value.trim();
            const city = document.getElementById('addCity').value.trim();
            const province = document.getElementById('addProvince').value.trim();
            
            // Validate
            if (!firstName || !lastName || !contact || !email || !dob || !gender || !houseStreet || !barangay || !city || !province) {
                alert('Please fill in all required fields.');
                return;
            }
            
            // Validate email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            // Disable submit button and show loading
            const saveBtn = addPatientForm.querySelector('button[type="submit"]');
            const originalBtnHTML = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">sync</span> Saving...';
            
            // Prepare data for API (map form fields to database schema field names)
            const apiData = {
                first_name: firstName,
                last_name: lastName,
                mobile: contact,  // API will map this to contact_number
                email: email,
                date_of_birth: dob,
                gender: gender,
                house_street: houseStreet,  // Match database column name
                barangay: barangay,
                city_municipality: city,  // Match database column name
                province: province,
                photo: patientPhotoData || null
            };
            
            // Call API to save to database
            fetch('<?php echo BASE_URL; ?>api/patients.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'include',
                body: JSON.stringify(apiData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Get patient ID from API response
                    const patientId = data.data?.patient_id || data.patient_id || `PT-${Date.now()}`;
                    
                    // Create patient data object for localStorage and UI
                    const patientData = {
                        id: patientId,
                        firstName: firstName,
                        lastName: lastName,
                        contact: contact,
                        email: email,
                        dob: dob,
                        gender: gender,
                        houseStreet: houseStreet,
                        barangay: barangay,
                        city: city,
                        province: province,
                        status: 'Active',
                        createdAt: new Date().toISOString(),
                        lastVisit: new Date().toISOString(),
                        photo: patientPhotoData || null
                    };
                    
                    // Save to localStorage for UI
                    const patientDataKey = `patient_data_${patientId}`;
                    localStorage.setItem(patientDataKey, JSON.stringify(patientData));
                    
                    // Reload patients from database to show the new one
                    loadPatientsFromDatabase();
                    
                    // Close modal
                    closeAddPatientModalFunc();
                    
                    // Show success message
                    saveBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check</span> Patient Added!';
                    saveBtn.classList.add('bg-green-500', 'hover:bg-green-600');
                    
                    setTimeout(() => {
                        saveBtn.innerHTML = originalBtnHTML;
                        saveBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                        saveBtn.disabled = false;
                    }, 2000);
                    
                    // Optionally open patient detail panel
                    setTimeout(() => {
                        loadPatientIntoDetailPanel(patientId);
                        openPatientDetail();
                    }, 500);
                } else {
                    // API error
                    throw new Error(data.message || 'Failed to save patient to database.');
                }
            })
            .catch(error => {
                console.error('Error saving patient:', error);
                alert('Error saving patient: ' + (error.message || 'Please try again.'));
                saveBtn.innerHTML = originalBtnHTML;
                saveBtn.disabled = false;
            });
        });
    }
    
    // Event listeners
    if (addNewPatientBtn) {
        addNewPatientBtn.addEventListener('click', openAddPatientModal);
    }
    
    if (closeAddPatientModal) {
        closeAddPatientModal.addEventListener('click', closeAddPatientModalFunc);
    }
    
    if (cancelAddPatientBtn) {
        cancelAddPatientBtn.addEventListener('click', closeAddPatientModalFunc);
    }
    
    // Close modal on backdrop click
    if (addPatientModal) {
        addPatientModal.addEventListener('click', (e) => {
            if (e.target === addPatientModal) {
                closeAddPatientModalFunc();
            }
        });
    }

    // PDF Export functionality
    // Note: exportPatientBtn is declared later where event listener is attached
    
    // Convert image to base64
    function getImageAsBase64(url) {
        return new Promise((resolve) => {
            // Try fetch first (works for local files in some contexts)
            fetch(url)
                .then(res => {
                    if (res.ok) {
                        return res.blob();
                    }
                    throw new Error('Fetch failed');
                })
                .then(blob => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.onerror = () => {
                        // Fallback to canvas method
                        tryCanvasMethod();
                    };
                    reader.readAsDataURL(blob);
                })
                .catch(() => {
                    // Fallback to canvas method
                    tryCanvasMethod();
                });
            
            function tryCanvasMethod() {
                const img = new Image();
                img.onload = function() {
                    try {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.width;
                        canvas.height = img.height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        const dataURL = canvas.toDataURL('image/png');
                        resolve(dataURL);
                    } catch (e) {
                        console.warn('Canvas method failed:', e);
                        resolve(null);
                    }
                };
                img.onerror = function() {
                    console.warn('Could not load logo image:', url);
                    resolve(null);
                };
                // Try with crossOrigin for external images
                img.crossOrigin = 'Anonymous';
                img.src = url;
            }
        });
    }
    
    // Format date for PDF
    function formatDateForPDF(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                          'July', 'August', 'September', 'October', 'November', 'December'];
            return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        } catch (e) {
            return dateString;
        }
    }
    
    // Export patient records to PDF
    async function exportPatientToPDF() {
        // Get export button reference
        const exportBtn = document.getElementById('exportPatientBtn');
        
        // Prevent multiple clicks
        if (exportBtn && exportBtn.disabled) {
            return;
        }
        
        if (!currentPatientId) {
            alert('No patient selected.');
            return;
        }
        
        // Disable button and show loading state
        if (exportBtn) {
            exportBtn.disabled = true;
            exportBtn.style.cursor = 'not-allowed';
            exportBtn.style.opacity = '0.6';
            const originalHTML = exportBtn.innerHTML;
            exportBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">sync</span> Generating...';
        }
        
        try {
            // Wait for jsPDF to be ready if it's not yet loaded (with timeout)
            if (!window.jsPDFReady) {
                let attempts = 0;
                const maxAttempts = 50; // 5 seconds max wait
                while (!window.jsPDFReady && attempts < maxAttempts) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                    attempts++;
                }
                
                if (!window.jsPDFReady) {
                    throw new Error('PDF library failed to load. Please refresh the page.');
                }
            }
            
            // Check if jsPDF is loaded
            if (typeof window.jspdf === 'undefined' && typeof window.jsPDF === 'undefined') {
                throw new Error('PDF library is not loaded. Please refresh the page and try again.');
            }
            
            // Try different ways to access jsPDF depending on the version
            let jsPDF;
            console.log('Checking jsPDF availability...', {
                'window.jspdf': typeof window.jspdf,
                'window.jsPDF': typeof window.jsPDF,
                'window.jspdf.jsPDF': window.jspdf ? typeof window.jspdf.jsPDF : 'N/A'
            });
            
            // For jsPDF 2.5.1 UMD build, it should be window.jspdf.jsPDF
            if (window.jspdf) {
                if (window.jspdf.jsPDF) {
                    jsPDF = window.jspdf.jsPDF;
                    console.log('Found jsPDF via window.jspdf.jsPDF');
                } else if (typeof window.jspdf === 'function') {
                    jsPDF = window.jspdf;
                    console.log('Found jsPDF via window.jspdf (function)');
                } else {
                    // Try accessing the default export
                    jsPDF = window.jspdf.default || window.jspdf;
                    console.log('Found jsPDF via window.jspdf.default or window.jspdf');
                }
            } else if (window.jsPDF) {
                jsPDF = window.jsPDF;
                console.log('Found jsPDF via window.jsPDF');
            } else {
                console.error('jsPDF not found. Available window properties:', Object.keys(window).filter(k => k.toLowerCase().includes('pdf')));
                throw new Error('jsPDF library not available. Please refresh the page and ensure the library loads correctly.');
            }
            
            if (!jsPDF || typeof jsPDF !== 'function') {
                console.error('jsPDF is not a function:', jsPDF);
                throw new Error('jsPDF constructor is not available. Library may not have loaded correctly.');
            }
            
            console.log('Creating jsPDF instance...');
            const doc = new jsPDF();
            console.log('jsPDF instance created successfully');
            
            // Get patient data
            const patientDataKey = `patient_data_${currentPatientId}`;
            const savedData = localStorage.getItem(patientDataKey);
            let patientData = {};
            
            if (savedData) {
                try {
                    patientData = JSON.parse(savedData);
                } catch (e) {
                    console.error('Error parsing patient data:', e);
                }
            }
            
            // Get data from display if not in localStorage
            const firstNameDisplay = document.getElementById('firstNameDisplay');
            const lastNameDisplay = document.getElementById('lastNameDisplay');
            const contactDisplay = document.getElementById('contactDisplay');
            const emailDisplay = document.getElementById('emailDisplay');
            const dobDisplay = document.getElementById('dobDisplay');
            const genderDisplay = document.getElementById('genderDisplay');
            const patientIdDisplay = document.querySelector('#patientDetailPanel [class*="font-mono"]');
            
            if (!patientData.firstName && firstNameDisplay) {
                patientData.firstName = firstNameDisplay.textContent.trim();
            }
            if (!patientData.lastName && lastNameDisplay) {
                patientData.lastName = lastNameDisplay.textContent.trim();
            }
            if (!patientData.contact && contactDisplay) {
                const contactSpan = contactDisplay.querySelector('span:last-child');
                patientData.contact = contactSpan ? contactSpan.textContent.trim() : '';
            }
            if (!patientData.email && emailDisplay) {
                const emailSpan = emailDisplay.querySelector('span:last-child');
                patientData.email = emailSpan ? emailSpan.textContent.trim() : '';
            }
            if (!patientData.dob && dobDisplay) {
                const dobSpan = dobDisplay.querySelector('span:last-child');
                patientData.dob = dobSpan ? dobSpan.textContent.trim() : '';
            }
            if (!patientData.gender && genderDisplay) {
                const genderSpan = genderDisplay.querySelector('span:last-child');
                patientData.gender = genderSpan ? genderSpan.textContent.trim() : '';
            }
            
            const patientId = patientIdDisplay ? patientIdDisplay.textContent.trim().replace('#', '') : currentPatientId;
            const patientName = `${patientData.firstName || ''} ${patientData.lastName || ''}`.trim() || 'Unknown Patient';
            
            // Load logo - try multiple paths
            let logoData = null;
            const logoPaths = [
                'DRCGLogo2.png',
                './DRCGLogo2.png',
                '../DRCGLogo2.png'
            ];
            
            // First try to get from existing DOM image
            const existingImg = document.querySelector('img[src*="DRCGLogo2"], img[src*="DRCGLogo"]');
            if (existingImg && existingImg.complete && existingImg.naturalWidth > 0) {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = existingImg.naturalWidth;
                    canvas.height = existingImg.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(existingImg, 0, 0);
                    logoData = canvas.toDataURL('image/png');
                } catch (e) {
                    console.warn('Could not get logo from DOM:', e);
                }
            }
            
            // Try different paths if not found
            if (!logoData) {
                for (const logoPath of logoPaths) {
                    try {
                        logoData = await getImageAsBase64(logoPath);
                        if (logoData && logoData.startsWith('data:')) {
                            break;
                        }
                    } catch (e) {
                        console.warn('Could not load logo from', logoPath);
                    }
                }
            }
            
            // Set up PDF
            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 20;
            let yPos = margin;
            let logoWidth = 60;
            let logoHeight = 20;
            
            // Add logo if available
            if (logoData && logoData.startsWith('data:')) {
                try {
                    // Get image dimensions to maintain aspect ratio
                    const img = new Image();
                    await new Promise((resolve) => {
                        img.onload = () => {
                            const aspectRatio = img.naturalWidth / img.naturalHeight;
                            const maxLogoWidth = 70;
                            const maxLogoHeight = 25;
                            
                            if (aspectRatio > 2.5) {
                                logoWidth = maxLogoWidth;
                                logoHeight = maxLogoWidth / aspectRatio;
                            } else {
                                logoHeight = maxLogoHeight;
                                logoWidth = maxLogoHeight * aspectRatio;
                            }
                            resolve();
                        };
                        img.onerror = () => resolve();
                        img.src = logoData;
                        setTimeout(() => resolve(), 1000);
                    });
                    
                    doc.addImage(logoData, 'PNG', margin, yPos, logoWidth, logoHeight);
                    yPos = margin + logoHeight + 5;
                } catch (e) {
                    console.warn('Could not add logo:', e);
                    yPos = 25;
                }
            } else {
                yPos = 25;
            }
            
            // Header
            doc.setFontSize(20);
            doc.setFont(undefined, 'bold');
            doc.text('Patient Medical Record', pageWidth - margin, yPos, { align: 'right' });
            
            yPos += 10;
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 100, 100);
            doc.text(`Generated: ${new Date().toLocaleString()}`, pageWidth - margin, yPos, { align: 'right' });
            doc.setTextColor(0, 0, 0);
            
            // Patient Information Section
            yPos += 15;
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('Patient Information', margin, yPos);
            
            yPos += 8;
            doc.setDrawColor(200, 200, 200);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            
            yPos += 8;
            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            
            const infoLines = [
                ['Patient Name:', patientName],
                ['Patient ID:', `#${patientId}`],
                ['Contact Number:', patientData.contact || 'N/A'],
                ['Email:', patientData.email || 'N/A'],
                ['Date of Birth:', formatDateForPDF(patientData.dob) || 'N/A'],
                ['Gender:', patientData.gender || 'N/A']
            ];
            
            infoLines.forEach(([label, value]) => {
                doc.setFont(undefined, 'bold');
                doc.text(label, margin, yPos);
                doc.setFont(undefined, 'normal');
                const labelWidth = doc.getTextWidth(label);
                doc.text(value, margin + labelWidth + 5, yPos);
                yPos += 7;
            });
            
            // Appointments Section
            yPos += 5;
            if (yPos > 250) {
                doc.addPage();
                yPos = margin;
                // Add logo to new page
                if (logoData && logoData.startsWith('data:')) {
                    try {
                        doc.addImage(logoData, 'PNG', margin, margin, logoWidth, logoHeight);
                    } catch (e) {
                        console.warn('Could not add logo to new page:', e);
                    }
                }
            }
            
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('Appointment History', margin, yPos);
            
            yPos += 8;
            doc.setDrawColor(200, 200, 200);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            
            yPos += 8;
            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            
            // Get appointments from the appointments tab
            const appointmentsTab = document.getElementById('appointments-tab');
            let hasAppointments = false;
            let appointmentCount = 0;
            
            if (appointmentsTab) {
                // Get upcoming appointments
                const upcomingCards = appointmentsTab.querySelectorAll('.relative.overflow-hidden');
                upcomingCards.forEach((card) => {
                    if (yPos > 250) {
                        doc.addPage();
                        yPos = margin;
                        // Add logo to new page
                        if (logoData && logoData.startsWith('data:')) {
                            try {
                                doc.addImage(logoData, 'PNG', margin, margin, logoWidth, logoHeight);
                            } catch (e) {
                                console.warn('Could not add logo to new page:', e);
                            }
                        }
                    }
                    
                    const titleEl = card.querySelector('h4');
                    const dateBlock = card.querySelector('.bg-blue-50, .dark\\:bg-blue-900\\/20');
                    const timeEl = card.querySelector('span:has(.material-symbols-outlined[style*="schedule"])');
                    const doctorEl = card.querySelector('span:has(.material-symbols-outlined[style*="person"])');
                    const roomEl = card.querySelector('span:has(.material-symbols-outlined[style*="meeting_room"])');
                    
                    if (titleEl) {
                        hasAppointments = true;
                        appointmentCount++;
                        
                        const title = titleEl.textContent.trim();
                        let dateInfo = '';
                        if (dateBlock) {
                            const day = dateBlock.querySelector('.text-2xl')?.textContent.trim() || '';
                            const month = dateBlock.querySelector('.text-sm')?.textContent.trim() || '';
                            const type = dateBlock.querySelector('.text-xs')?.textContent.trim() || '';
                            dateInfo = `${type} - ${month} ${day}`;
                        }
                        const time = timeEl ? timeEl.textContent.replace('schedule', '').trim() : '';
                        const doctor = doctorEl ? doctorEl.textContent.replace('person', '').trim() : '';
                        const room = roomEl ? roomEl.textContent.replace('meeting_room', '').trim() : '';
                        
                        doc.setFont(undefined, 'bold');
                        doc.text(`${appointmentCount}. ${title}`, margin, yPos);
                        yPos += 6;
                        
                        doc.setFont(undefined, 'normal');
                        if (dateInfo) {
                            doc.text(`Date: ${dateInfo}`, margin + 5, yPos);
                            yPos += 6;
                        }
                        if (time) {
                            doc.text(`Time: ${time}`, margin + 5, yPos);
                            yPos += 6;
                        }
                        if (doctor) {
                            doc.text(`Doctor: ${doctor}`, margin + 5, yPos);
                            yPos += 6;
                        }
                        if (room) {
                            doc.text(`Room: ${room}`, margin + 5, yPos);
                            yPos += 6;
                        }
                        doc.text('Status: Upcoming', margin + 5, yPos);
                        yPos += 8;
                    }
                });
                
                // Get past appointments
                const pastAppointments = appointmentsTab.querySelectorAll('.divide-y > div');
                pastAppointments.forEach((appointment) => {
                    if (yPos > 250) {
                        doc.addPage();
                        yPos = margin;
                        // Add logo to new page
                        if (logoData && logoData.startsWith('data:')) {
                            try {
                                doc.addImage(logoData, 'PNG', margin, margin, logoWidth, logoHeight);
                            } catch (e) {
                                console.warn('Could not add logo to new page:', e);
                            }
                        }
                    }
                    
                    const titleEl = appointment.querySelector('p.font-semibold');
                    const dateEl = appointment.querySelector('p.text-xs.text-gray-500');
                    const statusEl = appointment.querySelector('span.inline-flex');
                    
                    if (titleEl) {
                        hasAppointments = true;
                        appointmentCount++;
                        
                        const title = titleEl.textContent.trim();
                        const dateInfo = dateEl ? dateEl.textContent.trim() : '';
                        const status = statusEl ? statusEl.textContent.trim() : 'Completed';
                        
                        doc.setFont(undefined, 'bold');
                        doc.text(`${appointmentCount}. ${title}`, margin, yPos);
                        yPos += 6;
                        
                        doc.setFont(undefined, 'normal');
                        if (dateInfo) {
                            doc.text(`Date: ${dateInfo}`, margin + 5, yPos);
                            yPos += 6;
                        }
                        if (status) {
                            doc.text(`Status: ${status}`, margin + 5, yPos);
                            yPos += 6;
                        }
                        yPos += 3;
                    }
                });
            }
            
            if (!hasAppointments) {
                doc.setFont(undefined, 'italic');
                doc.setTextColor(150, 150, 150);
                doc.text('No appointment history available.', margin, yPos);
                doc.setTextColor(0, 0, 0);
            }
            
            // Footer on all pages
            const totalPages = doc.internal.pages.length - 1;
            for (let i = 1; i <= totalPages; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text(
                    `Page ${i} of ${totalPages} - DR. ROMARICO C. GONZALES Dental Clinic`,
                    pageWidth / 2,
                    doc.internal.pageSize.getHeight() - 10,
                    { align: 'center' }
                );
                doc.setTextColor(0, 0, 0);
            }
            
            // Generate PDF blob and open in new window for preview
            const fileName = `Patient_Record_${patientId}_${patientName.replace(/\s+/g, '_')}.pdf`;
            const pdfBlob = doc.output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            
            // Open PDF in new window for preview
            const previewWindow = window.open(pdfUrl, '_blank');
            
            if (previewWindow) {
                // Update button to show previewing state
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.style.cursor = 'pointer';
                    exportBtn.style.opacity = '1';
                    const originalHTML = exportBtn.innerHTML;
                    exportBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">visibility</span> Previewing...';
                    exportBtn.classList.add('text-blue-500');
                    
                    // After PDF loads, show download option
                    setTimeout(() => {
                        const userWantsDownload = confirm('PDF opened in new window. Would you like to download it as well?');
                        
                        if (userWantsDownload) {
                            // Create download link and trigger download
                            const downloadLink = document.createElement('a');
                            downloadLink.href = pdfUrl;
                            downloadLink.download = fileName;
                            downloadLink.style.display = 'none';
                            document.body.appendChild(downloadLink);
                            downloadLink.click();
                            document.body.removeChild(downloadLink);
                            
                            exportBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check</span> Downloaded!';
                            exportBtn.classList.remove('text-blue-500');
                            exportBtn.classList.add('text-green-500');
                            
                            setTimeout(() => {
                                exportBtn.innerHTML = originalHTML;
                                exportBtn.classList.remove('text-green-500');
                            }, 2000);
                        } else {
                            exportBtn.innerHTML = originalHTML;
                            exportBtn.classList.remove('text-blue-500');
                        }
                        
                        // Clean up blob URL after a delay
                        setTimeout(() => {
                            URL.revokeObjectURL(pdfUrl);
                        }, 5000);
                    }, 1000);
                }
            } else {
                // Popup blocked, fall back to direct download
                doc.save(fileName);
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.style.cursor = 'pointer';
                    exportBtn.style.opacity = '1';
                    const originalHTML = exportBtn.innerHTML;
                    exportBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check</span> Downloaded!';
                    exportBtn.classList.add('text-green-500');
                    setTimeout(() => {
                        exportBtn.innerHTML = originalHTML;
                        exportBtn.classList.remove('text-green-500');
                    }, 2000);
                }
                URL.revokeObjectURL(pdfUrl);
            }
            
        } catch (error) {
            console.error('Error exporting PDF:', error);
            console.error('Error details:', {
                message: error.message,
                stack: error.stack,
                jsPDFAvailable: typeof window.jspdf !== 'undefined' || typeof window.jsPDF !== 'undefined',
                windowKeys: Object.keys(window).filter(k => k.toLowerCase().includes('pdf'))
            });
            alert('Error generating PDF: ' + (error.message || 'Unknown error') + '\n\nPlease check the browser console for more details.');
            const exportBtn = document.getElementById('exportPatientBtn');
            if (exportBtn) {
                const originalHTML = exportBtn.innerHTML;
                exportBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">error</span> Error';
                exportBtn.classList.add('text-red-500');
                exportBtn.disabled = false;
                exportBtn.style.cursor = 'pointer';
                exportBtn.style.opacity = '1';
                setTimeout(() => {
                    exportBtn.innerHTML = originalHTML;
                    exportBtn.classList.remove('text-red-500');
                }, 3000);
            }
        } finally {
            // Always re-enable button after operation completes
            const exportBtn = document.getElementById('exportPatientBtn');
            if (exportBtn && exportBtn.disabled) {
                exportBtn.disabled = false;
                exportBtn.style.cursor = 'pointer';
                exportBtn.style.opacity = '1';
            }
        }
    }
    
    // Event listener for export button
    function attachExportButtonListener() {
        const exportBtn = document.getElementById('exportPatientBtn');
        if (exportBtn) {
            // Remove old listener if exists
            if (exportBtn.hasAttribute('data-listener-attached')) {
                return exportBtn;
            }
            
            exportBtn.setAttribute('data-listener-attached', 'true');
            exportBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Export button clicked, currentPatientId:', currentPatientId);
                console.log('Button state:', {
                    disabled: this.disabled,
                    currentPatientId: currentPatientId
                });
                
                if (!this.disabled && currentPatientId) {
                    exportPatientToPDF().catch(error => {
                        console.error('Error in exportPatientToPDF:', error);
                        alert('Failed to export PDF: ' + (error.message || 'Unknown error'));
                        // Re-enable button on error
                        this.disabled = false;
                        this.style.cursor = 'pointer';
                        this.style.opacity = '1';
                    });
                } else {
                    if (this.disabled) {
                        console.warn('Export button is disabled');
                    }
                    if (!currentPatientId) {
                        alert('No patient selected. Please select a patient first.');
                    }
                }
            });
            
            // Ensure button is clickable
            exportBtn.style.pointerEvents = 'auto';
            exportBtn.style.cursor = 'pointer';
            exportBtn.disabled = false;
            
            console.log('Export button event listener attached');
            return exportBtn;
        } else {
            console.warn('Export button not found');
            return null;
        }
    }
    
    // Try to attach immediately
    attachExportButtonListener();
    
    // Also try when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachExportButtonListener);
    }
    
    // Expose test function for debugging
    window.testExport = function() {
        console.log('Testing export functionality...');
        console.log('currentPatientId:', currentPatientId);
        console.log('jsPDFReady:', window.jsPDFReady);
        console.log('window.jspdf:', typeof window.jspdf);
        console.log('window.jsPDF:', typeof window.jsPDF);
        
        if (currentPatientId) {
            exportPatientToPDF().catch(error => {
                console.error('Export test failed:', error);
                alert('Export test failed: ' + error.message);
            });
        } else {
            alert('No patient selected. Please open a patient detail panel first.');
        }
    };
    
    console.log('Export functionality initialized. Use window.testExport() to test.');

    // ==================== Mobile Sidebar Toggle ====================
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const mobileSidebarBackdrop = document.getElementById('mobileSidebarBackdrop');
    const mobileSidebarPanel = mobileSidebar?.querySelector('aside');

    function openMobileSidebar() {
        if (mobileSidebar && mobileSidebarPanel) {
            mobileSidebar.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                mobileSidebarPanel.classList.remove('-translate-x-full');
            }, 10);
        }
    }

    function closeMobileSidebar() {
        if (mobileSidebar && mobileSidebarPanel) {
            mobileSidebarPanel.classList.add('-translate-x-full');
            setTimeout(() => {
                mobileSidebar.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        }
    }

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            openMobileSidebar();
        });
    }

    if (mobileSidebarBackdrop) {
        mobileSidebarBackdrop.addEventListener('click', closeMobileSidebar);
    }

    // Close sidebar when clicking on a link
    if (mobileSidebar) {
        mobileSidebar.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', closeMobileSidebar);
        });
    }

    // Sync mobile user data
    function syncMobileUserData() {
        const userDataStr = sessionStorage.getItem('adminUser');
        if (userDataStr) {
            try {
                const userData = JSON.parse(userDataStr);
                const mobileUserNameEl = document.getElementById('mobileUserName');
                const mobileUserRoleEl = document.getElementById('mobileUserRole');
                const mobileUserPhotoEl = document.getElementById('mobileUserPhoto');
                if (mobileUserNameEl) mobileUserNameEl.textContent = userData.name || 'Marc';
                if (mobileUserRoleEl) mobileUserRoleEl.textContent = 'Admin';
                if (mobileUserPhotoEl && userData.photo) {
                    mobileUserPhotoEl.style.backgroundImage = `url("${userData.photo}")`;
                }
            } catch (e) {
                console.error('Error loading mobile user data:', e);
            }
        }
    }

    // Mobile logout handler
    const mobileLogoutBtn = document.getElementById('mobileLogoutBtn');
    if (mobileLogoutBtn) {
        mobileLogoutBtn.addEventListener('click', function() {
            sessionStorage.removeItem('adminUser');
            window.location.href = '<?php echo BASE_URL; ?>api/logout.php';
        });
    }

    syncMobileUserData();
</script>