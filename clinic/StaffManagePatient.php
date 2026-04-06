<?php
$staff_nav_active = 'patients';
if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}
$currentStaffUserId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Patients Management - Staff Portal</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "primary": "#2b8beb",
                "background": "#f8fafc",
                "surface": "#ffffff",
                "on-background": "#101922",
                "on-surface-variant": "#404752"
            },
            fontFamily: {
                "headline": ["Manrope", "sans-serif"],
                "body": ["Manrope", "sans-serif"],
                "editorial": ["Playfair Display", "serif"]
            }
        }
    }
};
</script>
<style>
body { font-family: "Manrope", sans-serif; }
.material-symbols-outlined { vertical-align: middle; }
.mesh-bg {
    background-color: #f8fafc;
    background-image: radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%), radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
}
.elevated-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
}
.patient-tab-btn {
    border-bottom: 2px solid transparent;
}
.patient-tab-btn.active {
    color: #2b8beb;
    border-bottom-color: #2b8beb;
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
            <span class="w-12 h-[1.5px] bg-primary"></span> CLINICAL PRECISION
        </div>
        <div class="flex items-end justify-between gap-6">
            <div>
                <h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Patients <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
                </h2>
                <p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                    Manage and view all registered patient records.
                </p>
            </div>
            <button id="addNewPatientBtn" class="bg-primary hover:bg-primary/90 text-white px-8 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-primary/30 flex items-center gap-2 mb-2">
                <span class="material-symbols-outlined text-sm">add</span>
                Add New Patient
            </button>
        </div>
    </section>

    <section class="elevated-card p-8 rounded-3xl">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Search Records</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person_search</span>
                    <input id="searchInput" class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Name, Contact, ID, Email" type="text"/>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Status</label>
                <select id="statusFilter" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Gender</label>
                <select id="genderFilter" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
                    <option value="all">All Genders</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                    <option value="Prefer not to say">Prefer not to say</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Registration Date</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_month</span>
                    <input id="registrationDateFilter" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" type="date"/>
                </div>
            </div>
        </div>
    </section>

    <section class="elevated-card rounded-3xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Details</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Contact Number</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Email Address</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Gender</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Registered</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="patientsTableBody" class="divide-y divide-slate-100">
                    <tr><td colspan="7" class="px-6 py-10 text-center text-slate-500 font-medium">Loading patients...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="p-6 bg-slate-50/30 border-t border-slate-100">
            <p id="recordsSummary" class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing 0 of 0 patients</p>
        </div>
    </section>
</div>
</main>

<div id="addPatientModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden flex flex-col border border-slate-200">
        <div class="flex items-center justify-between p-6 border-b border-slate-200">
            <h2 id="patientModalTitle" class="text-2xl font-bold text-gray-900">Add New Patient</h2>
            <button id="closeAddPatientModal" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <form id="addPatientForm" class="flex-1 overflow-y-auto p-6">
            <input id="editingPatientId" type="hidden" value=""/>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">First Name <span class="text-red-500">*</span></label>
                    <input id="addFirstName" type="text" required class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Last Name <span class="text-red-500">*</span></label>
                    <input id="addLastName" type="text" required class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Contact Number <span class="text-red-500">*</span></label>
                    <input id="addContact" type="tel" required class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Email</label>
                    <input id="addEmail" type="email" class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Date of Birth</label>
                    <input id="addDob" type="date" class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Gender</label>
                    <select id="addGender" class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary">
                        <option value="">Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                        <option value="Prefer not to say">Prefer not to say</option>
                    </select>
                </div>
                <div class="flex flex-col md:col-span-2">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">House No. & Street</label>
                    <input id="addHouseStreet" type="text" class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Barangay</label>
                    <input id="addBarangay" type="text" class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">City/Municipality</label>
                    <input id="addCity" type="text" class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
                <div class="flex flex-col">
                    <label class="text-xs text-gray-500 uppercase tracking-wider font-semibold mb-2">Province</label>
                    <input id="addProvince" type="text" class="text-sm border border-slate-200 rounded-lg px-3 py-2.5 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 mt-6 pt-6 border-t border-slate-200">
                <button type="button" id="cancelAddPatientBtn" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold">Cancel</button>
                <button type="submit" id="savePatientBtn" class="px-4 py-2 bg-primary hover:bg-primary/90 text-white rounded-lg text-sm font-semibold flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Save Patient
                </button>
            </div>
        </form>
    </div>
</div>

<div id="viewPatientModal" class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm z-50 hidden items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl max-w-5xl w-full max-h-[92vh] overflow-hidden border border-slate-200 flex flex-col">
        <div class="flex items-center justify-between p-5 border-b border-slate-200">
            <h3 class="text-lg font-black tracking-tight text-slate-900">Patient Profile</h3>
            <button id="closeViewPatientModal" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div id="viewPatientContent" class="flex-1 overflow-y-auto p-6 text-sm text-slate-700"></div>
        <div class="p-4 border-t border-slate-200 bg-white">
            <button id="schedulePatientBtn" type="button" class="w-full bg-primary hover:bg-primary/90 text-white py-3 rounded-xl text-sm font-bold tracking-wide flex items-center justify-center gap-2 transition-all">
                <span class="material-symbols-outlined text-[18px]">calendar_add_on</span>
                Schedule Next Appointment
            </button>
        </div>
    </div>
</div>

<script>
const STAFF_OWNER_USER_ID = <?php echo json_encode($currentStaffUserId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const API_PATIENTS_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/patients.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

let allPatientsData = [];

const tableBody = document.getElementById('patientsTableBody');
const recordsSummary = document.getElementById('recordsSummary');
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const genderFilter = document.getElementById('genderFilter');
const registrationDateFilter = document.getElementById('registrationDateFilter');
const addPatientModal = document.getElementById('addPatientModal');
const viewPatientModal = document.getElementById('viewPatientModal');
const addPatientForm = document.getElementById('addPatientForm');

function escapeHtml(text) {
    return String(text || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
}

function normalizePatient(p) {
    return {
        id: p.id,
        patientId: p.patient_id || '',
        firstName: p.first_name || '',
        lastName: p.last_name || '',
        contact: p.contact_number || '',
        email: p.email || '',
        gender: p.gender || '',
        dob: p.date_of_birth || '',
        houseStreet: p.house_street || '',
        barangay: p.barangay || '',
        city: p.city_municipality || '',
        province: p.province || '',
        createdAt: p.created_at || '',
        updatedAt: p.updated_at || '',
        status: (p.status || 'active').toLowerCase()
    };
}

function renderPatients(patients) {
    if (!patients.length) {
        tableBody.innerHTML = '<tr><td colspan="7" class="px-6 py-10 text-center text-slate-500 font-medium">No patients found.</td></tr>';
        recordsSummary.textContent = `Showing 0 of ${allPatientsData.length} patients`;
        return;
    }

    tableBody.innerHTML = patients.map(patient => {
        const fullName = `${patient.firstName} ${patient.lastName}`.trim() || 'Patient';
        const initials = `${patient.firstName?.[0] || ''}${patient.lastName?.[0] || ''}`.toUpperCase() || 'PT';
        const statusActive = patient.status !== 'inactive';
        return `
            <tr class="hover:bg-slate-50/30 transition-colors group">
                <td class="px-8 py-6">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center font-black text-primary text-xs">${escapeHtml(initials)}</div>
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">${escapeHtml(fullName)}</span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">ID: #${escapeHtml(patient.patientId || patient.id)}</span>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-6 text-sm font-bold text-slate-700">${escapeHtml(patient.contact || 'N/A')}</td>
                <td class="px-6 py-6 text-sm font-medium text-slate-500">${escapeHtml(patient.email || 'N/A')}</td>
                <td class="px-6 py-6 text-center text-sm font-semibold text-slate-600">${escapeHtml(patient.gender || 'N/A')}</td>
                <td class="px-6 py-6 text-sm font-semibold text-slate-700">${escapeHtml(formatDate(patient.createdAt))}</td>
                <td class="px-6 py-6">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 ${statusActive ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'} text-[10px] font-black rounded-full uppercase tracking-widest">
                        <span class="w-1.5 h-1.5 rounded-full ${statusActive ? 'bg-emerald-500' : 'bg-slate-400'}"></span>
                        ${statusActive ? 'Active' : 'Inactive'}
                    </span>
                </td>
                <td class="px-8 py-6 text-right">
                    <div class="flex justify-end gap-2">
                        <button data-action="profile" data-id="${patient.id}" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-primary/20 text-primary hover:bg-primary/5 rounded-xl transition-all text-xs font-bold uppercase tracking-wider">
                            <span class="material-symbols-outlined text-[16px]">badge</span>
                            View Profile
                        </button>
                        <button data-action="edit" data-id="${patient.id}" class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-primary hover:border-primary/20 rounded-xl transition-all" title="Edit Patient">
                            <span class="material-symbols-outlined text-lg">edit_square</span>
                        </button>
                        <button data-action="delete" data-id="${patient.id}" class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-100 rounded-xl transition-all" title="Delete Patient">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    recordsSummary.textContent = `Showing ${patients.length} of ${allPatientsData.length} patients`;
}

function applyFilters() {
    const searchTerm = (searchInput.value || '').trim().toLowerCase();
    const selectedStatus = statusFilter.value;
    const selectedGender = genderFilter.value;
    const selectedDate = registrationDateFilter.value;

    const filtered = allPatientsData.filter(patient => {
        const fullName = `${patient.firstName} ${patient.lastName}`.toLowerCase();
        const haystack = [
            fullName,
            String(patient.id || '').toLowerCase(),
            String(patient.patientId || '').toLowerCase(),
            (patient.contact || '').toLowerCase(),
            (patient.email || '').toLowerCase()
        ];
        if (searchTerm && !haystack.some(v => v.includes(searchTerm))) return false;
        if (selectedStatus !== 'all' && patient.status !== selectedStatus) return false;
        if (selectedGender !== 'all' && patient.gender !== selectedGender) return false;
        if (selectedDate) {
            const createdAt = patient.createdAt ? patient.createdAt.slice(0, 10) : '';
            if (!createdAt || createdAt < selectedDate) return false;
        }
        return true;
    });

    renderPatients(filtered);
}

async function loadPatients() {
    try {
        const response = await fetch(API_PATIENTS_URL, { method: 'GET', credentials: 'include' });
        const data = await response.json();
        if (!response.ok || !data.success || !data.data || !Array.isArray(data.data.patients)) {
            throw new Error(data.message || 'Unable to load patients.');
        }
        allPatientsData = data.data.patients.map(normalizePatient);
        applyFilters();
    } catch (error) {
        tableBody.innerHTML = `<tr><td colspan="7" class="px-6 py-10 text-center text-red-500 font-medium">${escapeHtml(error.message || 'Unable to load patients.')}</td></tr>`;
        recordsSummary.textContent = 'Showing 0 of 0 patients';
    }
}

function openAddModal(patient) {
    document.getElementById('patientModalTitle').textContent = patient ? 'Edit Patient' : 'Add New Patient';
    document.getElementById('editingPatientId').value = patient ? String(patient.id) : '';
    document.getElementById('addFirstName').value = patient?.firstName || '';
    document.getElementById('addLastName').value = patient?.lastName || '';
    document.getElementById('addContact').value = patient?.contact || '';
    document.getElementById('addEmail').value = patient?.email || '';
    document.getElementById('addDob').value = patient?.dob ? String(patient.dob).slice(0, 10) : '';
    document.getElementById('addGender').value = patient?.gender || '';
    document.getElementById('addHouseStreet').value = patient?.houseStreet || '';
    document.getElementById('addBarangay').value = patient?.barangay || '';
    document.getElementById('addCity').value = patient?.city || '';
    document.getElementById('addProvince').value = patient?.province || '';
    addPatientModal.classList.remove('hidden');
    addPatientModal.classList.add('flex');
}

function closeAddModal() {
    addPatientForm.reset();
    document.getElementById('editingPatientId').value = '';
    addPatientModal.classList.add('hidden');
    addPatientModal.classList.remove('flex');
}

function openViewModal(patient) {
    const fullName = `${patient.firstName} ${patient.lastName}`.trim() || 'Patient';
    const patientStatus = patient.status === 'inactive' ? 'Inactive' : 'Active';
    const statusClasses = patient.status === 'inactive'
        ? 'bg-slate-100 text-slate-600'
        : 'bg-emerald-50 text-emerald-600';
    const address = [patient.houseStreet, patient.barangay, patient.city, patient.province].filter(Boolean).join(', ') || 'N/A';
    const treatmentText = patient.status === 'inactive' ? 'No active treatment plan found.' : 'No long-term treatment plan found.';
    document.getElementById('viewPatientContent').innerHTML = `
        <div class="space-y-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-5">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-full bg-primary/10 text-primary font-black text-xl flex items-center justify-center">
                        ${escapeHtml(`${patient.firstName?.[0] || ''}${patient.lastName?.[0] || ''}`.toUpperCase() || 'PT')}
                    </div>
                    <div>
                        <h4 class="text-3xl font-black tracking-tight text-slate-900">${escapeHtml(fullName)}</h4>
                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-slate-400 text-sm font-bold">#${escapeHtml(patient.patientId || patient.id)}</span>
                            <span class="px-2.5 py-1 rounded-md text-[11px] font-black uppercase tracking-wider ${statusClasses}">${patientStatus}</span>
                        </div>
                    </div>
                </div>
                <button data-action="edit" data-id="${patient.id}" class="self-start inline-flex items-center gap-1.5 px-3.5 py-2 border border-slate-200 text-slate-600 hover:text-primary hover:border-primary/20 rounded-xl transition-all text-xs font-bold uppercase tracking-wider">
                    <span class="material-symbols-outlined text-[16px]">edit_square</span>
                    Edit
                </button>
            </div>
            <div class="flex flex-wrap gap-8 text-sm border-t border-slate-200 pt-4">
                <div>
                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Created At</p>
                    <p class="text-xl font-bold text-slate-800 mt-1">${escapeHtml(formatDate(patient.createdAt))}</p>
                </div>
                <div>
                    <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest">Last Visit</p>
                    <p class="text-xl font-bold text-slate-800 mt-1">${escapeHtml(formatDate(patient.updatedAt || patient.createdAt))}</p>
                </div>
            </div>
            <div class="flex items-center gap-7 border-b border-slate-200 text-base font-semibold text-slate-500">
                <button type="button" class="patient-tab-btn active py-3" data-tab-target="basic">Basic Info</button>
                <button type="button" class="patient-tab-btn py-3" data-tab-target="appointments">Appointments</button>
                <button type="button" class="patient-tab-btn py-3" data-tab-target="treatment">Active Treatment</button>
                <button type="button" class="patient-tab-btn py-3" data-tab-target="files">Files & Documents</button>
            </div>
            <div id="patient-tab-basic" class="space-y-4">
                <div class="rounded-2xl border border-slate-200 p-5">
                    <h5 class="text-lg font-black mb-4">Basic Information</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-8">
                        <div><p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">First Name</p><p class="text-lg font-bold text-slate-800">${escapeHtml(patient.firstName || 'N/A')}</p></div>
                        <div><p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Last Name</p><p class="text-lg font-bold text-slate-800">${escapeHtml(patient.lastName || 'N/A')}</p></div>
                        <div><p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Contact Number</p><p class="text-lg font-bold text-slate-800">${escapeHtml(patient.contact || 'N/A')}</p></div>
                        <div><p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Email</p><p class="text-lg font-bold text-slate-800">${escapeHtml(patient.email || 'N/A')}</p></div>
                        <div><p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Date of Birth</p><p class="text-lg font-bold text-slate-800">${escapeHtml(formatDate(patient.dob))}</p></div>
                        <div><p class="text-[11px] font-black text-slate-400 uppercase tracking-wider">Gender</p><p class="text-lg font-bold text-slate-800">${escapeHtml(patient.gender || 'N/A')}</p></div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 p-5">
                    <h5 class="text-lg font-black mb-4">Address Information</h5>
                    <p class="text-lg font-semibold text-slate-700">${escapeHtml(address)}</p>
                </div>
            </div>
            <div id="patient-tab-appointments" class="hidden">
                <div class="rounded-2xl border border-slate-200 p-5">
                    <h5 class="text-lg font-black mb-3">Appointments History</h5>
                    <p class="text-slate-500 font-medium">Appointments list will appear here once linked to patient scheduling records.</p>
                </div>
            </div>
            <div id="patient-tab-treatment" class="hidden">
                <div class="rounded-2xl border border-primary/10 bg-primary/[0.03] p-5">
                    <h5 class="text-lg font-black mb-3">Long-Term Treatment Plans</h5>
                    <p class="text-slate-500 font-medium">${escapeHtml(treatmentText)}</p>
                </div>
            </div>
            <div id="patient-tab-files" class="hidden">
                <div class="rounded-2xl border border-slate-200 p-5">
                    <h5 class="text-lg font-black mb-3">Files & Documents</h5>
                    <p class="text-slate-500 font-medium">No uploaded files yet for this patient.</p>
                </div>
            </div>
        </div>
    `;
    setupPatientTabs();
    viewPatientModal.classList.remove('hidden');
    viewPatientModal.classList.add('flex');
}

function setupPatientTabs() {
    const tabButtons = Array.from(document.querySelectorAll('[data-tab-target]'));
    const tabSections = {
        basic: document.getElementById('patient-tab-basic'),
        appointments: document.getElementById('patient-tab-appointments'),
        treatment: document.getElementById('patient-tab-treatment'),
        files: document.getElementById('patient-tab-files')
    };

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-tab-target');
            tabButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            Object.entries(tabSections).forEach(([key, section]) => {
                if (!section) return;
                section.classList.toggle('hidden', key !== target);
            });
        });
    });
}

function closeViewModal() {
    viewPatientModal.classList.add('hidden');
    viewPatientModal.classList.remove('flex');
}

async function savePatient(event) {
    event.preventDefault();
    const editingId = document.getElementById('editingPatientId').value.trim();
    const payload = {
        first_name: document.getElementById('addFirstName').value.trim(),
        last_name: document.getElementById('addLastName').value.trim(),
        contact_number: document.getElementById('addContact').value.trim(),
        mobile: document.getElementById('addContact').value.trim(),
        email: document.getElementById('addEmail').value.trim(),
        date_of_birth: document.getElementById('addDob').value,
        gender: document.getElementById('addGender').value,
        house_street: document.getElementById('addHouseStreet').value.trim(),
        barangay: document.getElementById('addBarangay').value.trim(),
        city_municipality: document.getElementById('addCity').value.trim(),
        province: document.getElementById('addProvince').value.trim()
    };

    if (!payload.first_name || !payload.last_name) {
        alert('First name and last name are required.');
        return;
    }

    if (!editingId && !STAFF_OWNER_USER_ID) {
        alert('Unable to add patient: missing staff session user ID.');
        return;
    }

    const saveBtn = document.getElementById('savePatientBtn');
    const oldHtml = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">sync</span> Saving...';

    try {
        let method = 'POST';
        if (editingId) {
            method = 'PUT';
            payload.id = Number(editingId);
        } else {
            payload.owner_user_id = STAFF_OWNER_USER_ID;
        }

        const response = await fetch(API_PATIENTS_URL, {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to save patient.');
        }

        closeAddModal();
        await loadPatients();
    } catch (error) {
        alert(error.message || 'Failed to save patient.');
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldHtml;
    }
}

async function deletePatient(patientId) {
    if (!confirm('Delete this patient record?')) return;
    try {
        const response = await fetch(API_PATIENTS_URL, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id: Number(patientId) })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to delete patient.');
        }
        await loadPatients();
    } catch (error) {
        alert(error.message || 'Failed to delete patient.');
    }
}

document.getElementById('addNewPatientBtn').addEventListener('click', () => openAddModal(null));
document.getElementById('closeAddPatientModal').addEventListener('click', closeAddModal);
document.getElementById('cancelAddPatientBtn').addEventListener('click', closeAddModal);
document.getElementById('closeViewPatientModal').addEventListener('click', closeViewModal);
addPatientModal.addEventListener('click', e => { if (e.target === addPatientModal) closeAddModal(); });
viewPatientModal.addEventListener('click', e => { if (e.target === viewPatientModal) closeViewModal(); });
addPatientForm.addEventListener('submit', savePatient);

[searchInput, statusFilter, genderFilter, registrationDateFilter].forEach(el => {
    const eventName = el.tagName === 'INPUT' && el.type === 'text' ? 'input' : 'change';
    el.addEventListener(eventName, applyFilters);
});
searchInput.addEventListener('change', applyFilters);

tableBody.addEventListener('click', function (event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const action = button.getAttribute('data-action');
    const id = Number(button.getAttribute('data-id'));
    const patient = allPatientsData.find(p => Number(p.id) === id);
    if (!patient) return;

    if (action === 'profile') {
        openViewModal(patient);
    } else if (action === 'edit') {
        if (!viewPatientModal.classList.contains('hidden')) {
            closeViewModal();
        }
        openAddModal(patient);
    } else if (action === 'delete') {
        deletePatient(id);
    }
});

document.getElementById('schedulePatientBtn').addEventListener('click', () => {
    alert('Schedule workflow will be wired to appointments module.');
});

loadPatients();
</script>
</body>
</html>