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
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Patients Management - Aetheris Dental Systems</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<!-- Tailwind CSS -->
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
<!-- SideNavBar Component -->
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Canvas -->
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<!-- Content Area -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> CLINICAL PRECISION
            </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Patients <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Manage and view all registered patient records with architectural precision.
                    </p>
</div>
<button id="addNewPatientBtn" class="bg-primary hover:bg-primary/90 text-white px-8 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-primary/30 flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-sm">add</span>
    Add New Patient
</button></div>
</section>
<!-- Search & Filter Bar -->
<section class="elevated-card p-8 rounded-3xl space-y-6">
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Search Records</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person_search</span>
<input id="patientSearchInput" class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Name, Contact, Email, or ID" type="text"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Status</label>
<select id="statusFilterInput" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option value="">All Statuses</option>
<option value="active">Active</option>
<option value="inactive">Inactive</option>
</select>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Gender</label>
<select id="genderFilterInput" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option value="">All Genders</option>
<option value="male">Male</option>
<option value="female">Female</option>
<option value="other">Other</option>
<option value="prefer not to say">Prefer not to say</option>
</select>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Registration Date</label>
<div class="relative">
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_month</span>
<input id="registrationDateInput" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" type="month"/>
</div>
</div>
</div>
</section>
<!-- Patients Table -->
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Details</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Contact Number</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Email Address</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Gender</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Last Visit</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
</tr>
</thead>
<tbody id="patientsTableBody" class="divide-y divide-slate-100">
<tr>
<td class="px-8 py-8 text-center text-slate-500 font-semibold" colspan="7">Loading patients...</td>
</tr>
</tbody>
</table>
</div>
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
<p id="paginationInfo" class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing <span class="text-slate-900">0</span> of <span class="text-slate-900">0</span> patients</p>
</div>
</section>
</div>
</main>
<div id="addPatientModal" class="fixed inset-0 bg-black/45 hidden items-center justify-center z-50 p-4">
<div class="bg-white w-full max-w-2xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
<div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
<h3 class="text-lg font-extrabold text-slate-900">Add New Patient</h3>
<button id="closeAddPatientModal" class="w-9 h-9 rounded-lg hover:bg-slate-100 text-slate-500 flex items-center justify-center">
<span class="material-symbols-outlined text-lg">close</span>
</button>
</div>
<form id="addPatientForm" class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
<input id="addFirstName" type="text" required class="rounded-xl border-slate-200" placeholder="First Name"/>
<input id="addLastName" type="text" required class="rounded-xl border-slate-200" placeholder="Last Name"/>
<input id="addContact" type="text" required class="rounded-xl border-slate-200" placeholder="Contact Number"/>
<input id="addEmail" type="email" class="rounded-xl border-slate-200" placeholder="Email (optional)"/>
<input id="addDob" type="date" class="rounded-xl border-slate-200"/>
<select id="addGender" class="rounded-xl border-slate-200">
<option value="">Gender (optional)</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
<option value="Other">Other</option>
<option value="Prefer not to say">Prefer not to say</option>
</select>
<input id="addHouseStreet" type="text" class="rounded-xl border-slate-200 md:col-span-2" placeholder="House No. and Street"/>
<input id="addBarangay" type="text" class="rounded-xl border-slate-200" placeholder="Barangay"/>
<input id="addCity" type="text" class="rounded-xl border-slate-200" placeholder="City / Municipality"/>
<input id="addProvince" type="text" class="rounded-xl border-slate-200 md:col-span-2" placeholder="Province"/>
<div class="md:col-span-2 flex justify-end gap-3 pt-2">
<button id="cancelAddPatientBtn" type="button" class="px-4 py-2 rounded-xl border border-slate-200 text-slate-700 font-bold text-xs uppercase tracking-widest">Cancel</button>
<button id="savePatientBtn" type="submit" class="px-4 py-2 rounded-xl bg-primary text-white font-bold text-xs uppercase tracking-widest">Save Patient</button>
</div>
</form>
</div>
</div>

<div id="patientViewModal" class="fixed inset-0 bg-black/45 hidden items-center justify-center z-50 p-4">
<div class="bg-white w-full max-w-xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden">
<div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
<h3 class="text-lg font-extrabold text-slate-900">Patient Details</h3>
<button id="closePatientViewModal" class="w-9 h-9 rounded-lg hover:bg-slate-100 text-slate-500 flex items-center justify-center">
<span class="material-symbols-outlined text-lg">close</span>
</button>
</div>
<div id="patientViewContent" class="p-6 text-sm text-slate-700 space-y-2"></div>
</div>
</div>
<script>
    const loggedInUserId = <?php echo json_encode(isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : ''); ?>;
    let allPatientsData = [];

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    function formatDateDisplay(value) {
        if (!value) return 'N/A';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function getGenderIcon(gender) {
        const normalized = (gender || '').toLowerCase();
        if (normalized === 'male') return 'male';
        if (normalized === 'female') return 'female';
        return 'wc';
    }

    function renderPatients(patients) {
        const tbody = document.getElementById('patientsTableBody');
        const paginationInfo = document.getElementById('paginationInfo');
        if (!tbody) return;

        tbody.innerHTML = '';
        if (!patients.length) {
            tbody.innerHTML = '<tr><td class="px-8 py-8 text-center text-slate-500 font-semibold" colspan="7">No patients found.</td></tr>';
            if (paginationInfo) paginationInfo.innerHTML = 'Showing <span class="text-slate-900">0</span> of <span class="text-slate-900">0</span> patients';
            return;
        }

        patients.forEach((patient) => {
            const fullName = `${patient.first_name || ''} ${patient.last_name || ''}`.trim() || 'Patient';
            const avatar = patient.profile_image
                ? patient.profile_image.replace(/^\/+/, '../')
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=2b8beb&color=fff&size=80`;
            const gender = patient.gender || 'N/A';
            const normalizedStatus = (patient.status || 'active').toLowerCase();
            const statusLabel = normalizedStatus === 'inactive' ? 'Inactive' : 'Active';
            const statusClass = normalizedStatus === 'inactive'
                ? 'bg-slate-100 text-slate-600'
                : 'bg-emerald-50 text-emerald-600';

            const row = document.createElement('tr');
            row.className = 'hover:bg-slate-50/30 transition-colors group';
            row.innerHTML = `
                <td class="px-8 py-6">
                    <div class="flex items-center gap-4">
                        <img alt="Patient Avatar" class="w-10 h-10 rounded-xl object-cover ring-2 ring-primary/5" src="${escapeHtml(avatar)}"/>
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">${escapeHtml(fullName)}</span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5">ID: #${escapeHtml(patient.patient_id || patient.id)}</span>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-6 text-sm font-bold text-slate-700">${escapeHtml(patient.contact_number || 'N/A')}</td>
                <td class="px-6 py-6 text-sm font-medium text-slate-500">${escapeHtml(patient.email || 'N/A')}</td>
                <td class="px-6 py-6 text-center">
                    <span class="material-symbols-outlined text-slate-300 text-xl" title="${escapeHtml(gender)}">${getGenderIcon(gender)}</span>
                </td>
                <td class="px-6 py-6 text-sm font-bold text-slate-700">${escapeHtml(formatDateDisplay(patient.updated_at || patient.created_at))}</td>
                <td class="px-6 py-6">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 text-[10px] font-black rounded-full uppercase tracking-widest ${statusClass}">${statusLabel}</span>
                </td>
                <td class="px-8 py-6 text-right">
                    <button class="view-patient-btn text-primary hover:text-primary/80 transition-colors p-2 bg-primary/10 rounded-full" data-patient-id="${escapeHtml(patient.id)}">
                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });

        if (paginationInfo) {
            paginationInfo.innerHTML = `Showing <span class="text-slate-900">${patients.length}</span> of <span class="text-slate-900">${allPatientsData.length}</span> patients`;
        }
    }

    function applyFilters() {
        const searchValue = (document.getElementById('patientSearchInput')?.value || '').toLowerCase().trim();
        const statusValue = (document.getElementById('statusFilterInput')?.value || '').toLowerCase().trim();
        const genderValue = (document.getElementById('genderFilterInput')?.value || '').toLowerCase().trim();
        const registrationValue = document.getElementById('registrationDateInput')?.value || '';

        const filtered = allPatientsData.filter((patient) => {
            const fullName = `${patient.first_name || ''} ${patient.last_name || ''}`.toLowerCase();
            const searchable = `${fullName} ${(patient.patient_id || '')} ${(patient.contact_number || '')} ${(patient.email || '')}`.toLowerCase();
            if (searchValue && !searchable.includes(searchValue)) return false;

            const patientStatus = (patient.status || 'active').toLowerCase();
            if (statusValue && patientStatus !== statusValue) return false;

            const patientGender = (patient.gender || '').toLowerCase();
            if (genderValue && patientGender !== genderValue) return false;

            if (registrationValue) {
                const createdAt = patient.created_at ? String(patient.created_at).slice(0, 7) : '';
                if (createdAt !== registrationValue) return false;
            }
            return true;
        });

        renderPatients(filtered);
    }

    function loadPatients() {
        fetch('api/patients.php', {
            method: 'GET',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' }
        })
            .then((response) => response.json())
            .then((payload) => {
                if (!payload.success || !payload.data || !Array.isArray(payload.data.patients)) {
                    throw new Error(payload.message || 'Unable to load patients.');
                }
                allPatientsData = payload.data.patients;
                applyFilters();
            })
            .catch((error) => {
                const tbody = document.getElementById('patientsTableBody');
                if (tbody) {
                    tbody.innerHTML = `<tr><td class="px-8 py-8 text-center text-rose-500 font-semibold" colspan="7">${escapeHtml(error.message || 'Failed to load patients.')}</td></tr>`;
                }
            });
    }

    function openPatientView(patientId) {
        const modal = document.getElementById('patientViewModal');
        const content = document.getElementById('patientViewContent');
        const patient = allPatientsData.find((item) => String(item.id) === String(patientId));
        if (!modal || !content || !patient) return;

        const fullName = `${patient.first_name || ''} ${patient.last_name || ''}`.trim() || 'N/A';
        content.innerHTML = `
            <p><strong>Name:</strong> ${escapeHtml(fullName)}</p>
            <p><strong>Patient ID:</strong> #${escapeHtml(patient.patient_id || patient.id || 'N/A')}</p>
            <p><strong>Contact:</strong> ${escapeHtml(patient.contact_number || 'N/A')}</p>
            <p><strong>Email:</strong> ${escapeHtml(patient.email || 'N/A')}</p>
            <p><strong>DOB:</strong> ${escapeHtml(formatDateDisplay(patient.date_of_birth))}</p>
            <p><strong>Gender:</strong> ${escapeHtml(patient.gender || 'N/A')}</p>
            <p><strong>Address:</strong> ${escapeHtml([patient.house_street, patient.barangay, patient.city_municipality, patient.province].filter(Boolean).join(', ') || 'N/A')}</p>
            <p><strong>Created:</strong> ${escapeHtml(formatDateDisplay(patient.created_at))}</p>
            <p><strong>Last Update:</strong> ${escapeHtml(formatDateDisplay(patient.updated_at || patient.created_at))}</p>
        `;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closePatientView() {
        const modal = document.getElementById('patientViewModal');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function openAddPatientModal() {
        const modal = document.getElementById('addPatientModal');
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeAddPatientModal() {
        const modal = document.getElementById('addPatientModal');
        const form = document.getElementById('addPatientForm');
        if (form) form.reset();
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function handleAddPatientSubmit(event) {
        event.preventDefault();
        if (!loggedInUserId) {
            alert('Unable to detect logged in staff user. Please re-login and try again.');
            return;
        }

        const saveBtn = document.getElementById('savePatientBtn');
        const originalText = saveBtn ? saveBtn.innerHTML : '';
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = 'Saving...';
        }

        const payload = {
            first_name: document.getElementById('addFirstName')?.value.trim(),
            last_name: document.getElementById('addLastName')?.value.trim(),
            mobile: document.getElementById('addContact')?.value.trim(),
            email: document.getElementById('addEmail')?.value.trim(),
            date_of_birth: document.getElementById('addDob')?.value || null,
            gender: document.getElementById('addGender')?.value || null,
            house_street: document.getElementById('addHouseStreet')?.value.trim(),
            barangay: document.getElementById('addBarangay')?.value.trim(),
            city_municipality: document.getElementById('addCity')?.value.trim(),
            province: document.getElementById('addProvince')?.value.trim(),
            owner_user_id: loggedInUserId
        };

        fetch('api/patients.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
            .then((response) => response.json())
            .then((result) => {
                if (!result.success) throw new Error(result.message || 'Failed to add patient.');
                closeAddPatientModal();
                loadPatients();
            })
            .catch((error) => {
                alert(error.message || 'Failed to add patient.');
            })
            .finally(() => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalText;
                }
            });
    }

    document.addEventListener('click', (event) => {
        const viewBtn = event.target.closest('.view-patient-btn');
        if (viewBtn) {
            openPatientView(viewBtn.getAttribute('data-patient-id'));
        }
    });

    document.getElementById('patientSearchInput')?.addEventListener('input', applyFilters);
    document.getElementById('statusFilterInput')?.addEventListener('change', applyFilters);
    document.getElementById('genderFilterInput')?.addEventListener('change', applyFilters);
    document.getElementById('registrationDateInput')?.addEventListener('change', applyFilters);

    document.getElementById('addNewPatientBtn')?.addEventListener('click', openAddPatientModal);
    document.getElementById('closeAddPatientModal')?.addEventListener('click', closeAddPatientModal);
    document.getElementById('cancelAddPatientBtn')?.addEventListener('click', closeAddPatientModal);
    document.getElementById('addPatientForm')?.addEventListener('submit', handleAddPatientSubmit);
    document.getElementById('closePatientViewModal')?.addEventListener('click', closePatientView);
    document.getElementById('patientViewModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'patientViewModal') closePatientView();
    });
    document.getElementById('addPatientModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'addPatientModal') closeAddPatientModal();
    });

    loadPatients();
</script>
</body></html>