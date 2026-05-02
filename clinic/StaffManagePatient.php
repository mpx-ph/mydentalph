<?php
require_once __DIR__ . '/config/config.php';
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
    transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
}
.elevated-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
}
.provider-page-enter {
    animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
@keyframes provider-page-in {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
}
.staff-modal-overlay:not(.hidden) {
    animation: staff-modal-fade-in 0.25s ease forwards;
}
.staff-modal-panel {
    animation: staff-modal-panel-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
@keyframes staff-modal-fade-in {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes staff-modal-panel-in {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.patient-tab-btn {
    border-bottom: 3px solid transparent;
    padding-bottom: 0.75rem;
    margin-bottom: -1px;
    font-size: 0.875rem;
    font-weight: 600;
    color: #94a3b8;
    transition: color 0.15s ease, border-color 0.15s ease;
}
.patient-tab-btn:hover {
    color: #64748b;
}
.patient-tab-btn.active {
    color: #2b8beb;
    border-bottom-color: #2b8beb;
}
.view-patient-card {
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #ffffff;
}
.field-error {
    border-color: #ef4444 !important;
    box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.15) !important;
}
.treatment-progress-gradient {
    background: linear-gradient(90deg, #1d6fd4 0%, #2b8beb 55%, #4da3f5 100%);
}
#treatmentProgressModal:not(.hidden) {
    animation: staff-modal-fade-in 0.25s ease forwards;
}
</style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
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

<div id="addPatientModal" class="staff-modal-overlay fixed inset-0 z-[75] hidden items-center justify-center bg-slate-900/50 backdrop-blur-[2px] p-4">
    <div class="staff-modal-panel bg-white rounded-3xl shadow-[0_24px_64px_-12px_rgba(15,23,42,0.25)] border border-slate-100 w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col">
        <div class="shrink-0 px-6 sm:px-8 pt-7 pb-5 border-b border-slate-100 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                <span class="material-symbols-outlined text-2xl text-primary">person_add</span>
            </div>
            <div class="min-w-0 flex-1 pr-2">
                <h2 id="patientModalTitle" class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight">Patient Registration</h2>
                <p class="text-sm text-slate-500 mt-1 leading-relaxed">Register a new patient to the clinic management system</p>
            </div>
            <button id="closeAddPatientModal" type="button" class="shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <form id="addPatientForm" class="flex-1 min-h-0 flex flex-col">
            <input id="editingPatientId" type="hidden" value=""/>
            <div class="px-6 sm:px-8 pt-3 pb-5 space-y-6 overflow-y-auto">
                <section>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-primary text-[22px]">badge</span>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Personal Information</h3>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">person</span>
                                    First Name <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addFirstName" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="Enter first name"/>
                                <p id="addFirstNameError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">badge</span>
                                    Last Name <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addLastName" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="Enter last name"/>
                                <p id="addLastNameError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                        <span class="material-symbols-outlined text-[18px] text-slate-500">event</span>
                                        Date of Birth <span class="text-red-500 font-bold">*</span>
                                    </label>
                                    <input id="addDob" type="date" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all"/>
                                    <p id="addDobError" class="mt-1 text-xs text-red-500 hidden"></p>
                                </div>
                                <div>
                                    <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                        <span class="material-symbols-outlined text-[18px] text-slate-500">cake</span>
                                        Age
                                    </label>
                                    <input id="addAge" type="text" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-600 text-[15px] shadow-inner" placeholder="0"/>
                                </div>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">wc</span>
                                    Gender <span class="text-red-500 font-bold">*</span>
                                </label>
                                <div class="flex items-center gap-6 h-[48px]">
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                        <input type="radio" name="addGender" value="Male" class="text-primary border-slate-300 accent-primary focus:ring-primary"/>
                                        Male
                                    </label>
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                                        <input type="radio" name="addGender" value="Female" class="text-primary border-slate-300 accent-primary focus:ring-primary"/>
                                        Female
                                    </label>
                                </div>
                                <p id="addGenderError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">bloodtype</span>
                                    Blood Type <span class="text-red-500 font-bold">*</span>
                                </label>
                                <select id="addBloodType" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer">
                                    <option value="">Select type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                                <p id="addBloodTypeError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">clinical_notes</span>
                                    Medical History &amp; Alerts <span class="ml-1 text-[11px] font-semibold text-slate-400">(Optional)</span>
                                </label>
                                <textarea id="addMedicalHistory" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all resize-y min-h-[100px]" placeholder="Allergies, chronic conditions, current medications..."></textarea>
                                <p id="addMedicalHistoryError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-primary text-[22px]">contact_page</span>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Contact Information</h3>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">call</span>
                                    Contact No. <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addContact" type="tel" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="09XX XXX XXXX"/>
                                <p id="addContactError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">mail</span>
                                    Email Address <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addEmail" type="email" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="patient@example.com"/>
                                <p id="addEmailError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                        </div>

                        <p class="text-[11px] font-black uppercase tracking-[0.2em] text-slate-500">Residential Address</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">map</span>
                                    Province <span class="text-red-500 font-bold">*</span>
                                </label>
                                <select id="addProvince" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer">
                                    <option value="">Select province</option>
                                </select>
                                <p id="addProvinceError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">location_city</span>
                                    City / Municipality <span class="text-red-500 font-bold">*</span>
                                </label>
                                <select id="addCity" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer" disabled>
                                    <option value="">Select city/municipality</option>
                                </select>
                                <p id="addCityError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">pin_drop</span>
                                    Barangay <span class="text-red-500 font-bold">*</span>
                                </label>
                                <select id="addBarangay" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer" disabled>
                                    <option value="">Select barangay</option>
                                </select>
                                <p id="addBarangayError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">home_pin</span>
                                    Street / House No. <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="addHouseStreet" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all" placeholder="Unit, building, street"/>
                                <p id="addHouseStreetError" class="mt-1 text-xs text-red-500 hidden"></p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="border-t border-slate-100 bg-slate-50/50 px-6 sm:px-8 py-4 flex flex-wrap items-center justify-end gap-3">
                <button type="button" id="cancelAddPatientBtn" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:bg-slate-50 transition-all shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                    Cancel
                </button>
                <button type="submit" id="savePatientBtn" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-primary hover:bg-primary/92 text-white text-sm font-bold shadow-lg shadow-primary/25 transition-all">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    Register Patient
                </button>
            </div>
        </form>
    </div>
</div>

<div id="viewPatientModal" class="staff-modal-overlay fixed inset-0 bg-slate-900/40 backdrop-blur-[2px] z-50 hidden flex justify-end">
    <div id="viewPatientPanel" class="relative ml-auto h-full w-full max-w-xl lg:max-w-2xl bg-white shadow-[0_0_48px_-12px_rgba(15,23,42,0.18)] overflow-hidden border-l border-slate-200/90 flex flex-col transform translate-x-full transition-transform duration-300 ease-out rounded-l-2xl">
        <button type="button" id="closeViewPatientModal" class="absolute top-5 right-5 z-20 p-2 rounded-lg text-primary hover:text-primary/80 hover:bg-primary/10 transition-colors" aria-label="Close profile panel">
            <span class="material-symbols-outlined text-[22px]">close</span>
        </button>
        <div id="viewPatientContent" class="flex-1 flex flex-col min-h-0 overflow-hidden text-slate-700"></div>
        <div class="shrink-0 px-5 pb-5 pt-2 border-t border-slate-100 bg-white">
            <button id="schedulePatientBtn" type="button" class="w-full bg-primary hover:bg-primary/90 text-white py-3.5 rounded-xl text-sm font-bold tracking-wide flex items-center justify-center gap-2 transition-all shadow-md shadow-primary/20">
                <span class="material-symbols-outlined text-[20px] text-white">calendar_add_on</span>
                Schedule Next Appointment
            </button>
        </div>
    </div>
</div>

<div id="treatmentProgressModal" class="fixed inset-0 z-[90] hidden items-center justify-center bg-slate-900/55 backdrop-blur-[2px] p-4">
    <div class="relative flex w-full max-w-[min(96vw,1280px)] max-h-[92vh] flex-col overflow-hidden rounded-2xl bg-white shadow-[0_24px_64px_-12px_rgba(15,23,42,0.28)] border border-slate-100">
        <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
            <div class="flex items-center gap-3 min-w-0">
                <span class="material-symbols-outlined shrink-0 text-primary text-[26px]">calendar_month</span>
                <h3 class="text-lg font-extrabold text-slate-900 tracking-tight">Treatment Progress</h3>
            </div>
            <button type="button" id="closeTreatmentProgressModalX" class="shrink-0 p-2 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                <span class="material-symbols-outlined text-[24px]">close</span>
            </button>
        </div>
        <div id="treatmentProgressModalBody" class="flex-1 min-h-0 overflow-y-auto px-6 py-6"></div>
        <div class="shrink-0 flex justify-end gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50/80">
            <button type="button" id="closeTreatmentProgressModalFooter" class="inline-flex items-center justify-center rounded-xl bg-slate-200/90 hover:bg-slate-200 px-8 py-2.5 text-sm font-semibold text-slate-700 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>js/staff-ui-dialogs.js"></script>
<script>
const STAFF_OWNER_USER_ID = <?php echo json_encode($currentStaffUserId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const API_PATIENTS_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/patients.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const API_ADDRESS_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/philippine_address.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const API_APPOINTMENTS_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/appointments.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const API_TREATMENT_CONTEXT_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/patient_treatment_context.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const API_TREATMENT_PROGRESS_MODAL_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/treatment_progress_modal.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const API_PATIENT_FILES_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/patient_files.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const CLINIC_STAFF_BASE = <?php echo json_encode(rtrim(BASE_URL, "/\\") . '/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

/** Matches StaffManageServices / services catalog category labels → badge colors */
const SERVICE_CATEGORY_BADGE_BY_LABEL = {
    'General Dentistry': 'bg-blue-100 text-blue-800 ring-1 ring-blue-200/90',
    'Restorative Dentistry': 'bg-green-100 text-green-800 ring-1 ring-green-200/90',
    'Oral Surgery': 'bg-rose-100 text-rose-800 ring-1 ring-rose-200/90',
    'Crowns and Bridges': 'bg-amber-100 text-amber-800 ring-1 ring-amber-200/90',
    'Cosmetic Dentistry': 'bg-violet-100 text-violet-800 ring-1 ring-violet-200/90',
    'Pediatric Dentistry': 'bg-pink-100 text-pink-800 ring-1 ring-pink-200/90',
    'Orthodontics': 'bg-orange-100 text-orange-800 ring-1 ring-orange-200/90',
    'Specialized and Others': 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/90'
};
/** StaffWalkIn-style slug keys → canonical label */
const SERVICE_CATEGORY_SLUG_TO_LABEL = {
    general_dentistry: 'General Dentistry',
    restorative_dentistry: 'Restorative Dentistry',
    oral_surgery: 'Oral Surgery',
    crowns_and_bridges: 'Crowns and Bridges',
    cosmetic_dentistry: 'Cosmetic Dentistry',
    pediatric_dentistry: 'Pediatric Dentistry',
    orthodontics: 'Orthodontics',
    specialized_and_others: 'Specialized and Others'
};

function resolveInstallmentServiceCategoryBadgeClasses(treatment) {
    const fallback = 'bg-slate-100 text-slate-800 ring-1 ring-slate-200/90';
    const raw = String(treatment?.primary_service?.category ?? '').trim();
    if (!raw) return fallback;
    if (SERVICE_CATEGORY_BADGE_BY_LABEL[raw]) return SERVICE_CATEGORY_BADGE_BY_LABEL[raw];
    const bySlug = SERVICE_CATEGORY_SLUG_TO_LABEL[raw] || SERVICE_CATEGORY_SLUG_TO_LABEL[raw.toLowerCase().replace(/-/g, '_')];
    if (bySlug && SERVICE_CATEGORY_BADGE_BY_LABEL[bySlug]) return SERVICE_CATEGORY_BADGE_BY_LABEL[bySlug];
    const lower = raw.toLowerCase();
    const hit = Object.keys(SERVICE_CATEGORY_BADGE_BY_LABEL).find(k => k.toLowerCase() === lower);
    return hit ? SERVICE_CATEGORY_BADGE_BY_LABEL[hit] : fallback;
}

function treatmentProgressPaymentBadgeClass(bucket) {
    return bucket === 'paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700';
}

function treatmentProgressVisitBadgeClass(bucket) {
    switch (bucket) {
        case 'completed':
            return 'bg-emerald-100 text-emerald-800';
        case 'scheduled':
            return 'bg-blue-100 text-blue-800';
        case 'book_visit':
            return 'bg-orange-100 text-orange-800';
        case 'locked':
            return 'bg-slate-200 text-slate-700';
        default:
            return 'bg-slate-100 text-slate-600';
    }
}

function buildTreatmentProgressModalInnerHtml(payload, patientId) {
    const t = payload.treatment || {};
    const bookingId = String(payload.booking_id || '');
    const steps = Array.isArray(payload.steps) ? payload.steps : [];
    const total = Number(t.total_cost || 0);
    const paid = Number(t.amount_paid || 0);
    const pct = Math.max(0, Math.min(100, Number(t.progress_percentage ?? 0)));
    const pctRounded = Math.round(pct);
    const paidLabel = formatPeso(paid);
    const totalLabel = formatPeso(total);
    const pidEsc = escapeHtml(String(patientId || ''));
    const bidEsc = escapeHtml(bookingId);
    const rows = steps.length ? steps.map((row) => {
        const schedule = row.schedule_display ? escapeHtml(row.schedule_display) : '—';
        const amt = formatPeso(row.amount_due);
        const payBucket = row.payment_bucket || 'unpaid';
        const visBucket = row.visit_bucket || 'pending';
        const payBadge = treatmentProgressPaymentBadgeClass(payBucket);
        const visBadge = treatmentProgressVisitBadgeClass(visBucket);
        const payLabel = escapeHtml(row.payment_status || 'Unpaid');
        const visLabel = escapeHtml(row.visit_status || 'Pending');
        const payDis = row.pay_disabled === true;
        const schDis = row.schedule_disabled === true;
        const payBtn = payDis
            ? '<button type="button" disabled class="rounded-lg border border-slate-200 bg-slate-200 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wide text-slate-500 cursor-not-allowed shadow-none">PAY</button>'
            : `<button type="button" data-treatment-progress-pay="1" data-patient-id="${pidEsc}" data-booking-id="${bidEsc}"
                class="rounded-lg bg-primary px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wide text-white shadow-sm hover:bg-primary/90 transition-colors">PAY</button>`;
        const schBtn = schDis
            ? '<button type="button" disabled class="rounded-lg border border-slate-200 bg-slate-200 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wide text-slate-500 cursor-not-allowed shadow-none">SCHEDULE</button>'
            : `<button type="button" data-treatment-progress-schedule="1" data-patient-id="${pidEsc}"
                class="rounded-lg bg-orange-500 px-3 py-1.5 text-[11px] font-extrabold uppercase tracking-wide text-white shadow-sm hover:bg-orange-600 transition-colors">SCHEDULE</button>`;
        const actionCell = `<div class="flex flex-wrap items-center justify-end gap-2">${payBtn}${schBtn}</div>`;
        return `
            <tr class="border-b border-[#eeeeee] last:border-0">
                <td class="px-3 py-3 text-sm font-bold text-slate-900">${escapeHtml(row.step_code || '')}</td>
                <td class="px-3 py-3">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${payBadge}">${payLabel}</span>
                </td>
                <td class="px-3 py-3">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ${visBadge}">${visLabel}</span>
                </td>
                <td class="px-3 py-3 text-sm text-slate-500">${schedule}</td>
                <td class="px-3 py-3 text-sm font-bold text-slate-900">${escapeHtml(amt)}</td>
                <td class="px-3 py-3 text-right">${actionCell}</td>
            </tr>`;
    }).join('') : `
        <tr>
            <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500 font-medium">No installment schedule found for this treatment.</td>
        </tr>`;

    return `
        <div class="space-y-6">
            <div class="treatment-progress-gradient rounded-xl px-5 py-5 text-white shadow-md">
                <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-white/90">Total payment progress</p>
                <p class="mt-2 text-2xl font-extrabold tracking-tight">${escapeHtml(paidLabel)} / ${escapeHtml(totalLabel)}</p>
                <div class="mt-4 h-2.5 w-full overflow-hidden rounded-full bg-white">
                    <div class="h-full rounded-full bg-sky-200" style="width:${pct}%"></div>
                </div>
                <p class="mt-2 text-right text-xs font-semibold text-white/95">${escapeHtml(String(pctRounded))}% Paid</p>
            </div>
            <div class="rounded-xl border border-[#eeeeee] overflow-hidden bg-white">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-[#eeeeee] bg-white">
                                <th class="px-3 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">Step</th>
                                <th class="px-3 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">Payment status</th>
                                <th class="px-3 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">Visit status</th>
                                <th class="px-3 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">Schedule</th>
                                <th class="px-3 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-500">Amount</th>
                                <th class="px-3 py-3 text-right text-[10px] font-bold uppercase tracking-wider text-slate-500">Action</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </div>
        </div>`;
}

function closeTreatmentProgressModal() {
    const modal = document.getElementById('treatmentProgressModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    const body = document.getElementById('treatmentProgressModalBody');
    if (body) body.innerHTML = '';
    syncModalBodyScrollLock();
}

async function openTreatmentProgressModal(treatmentId) {
    const modal = document.getElementById('treatmentProgressModal');
    const body = document.getElementById('treatmentProgressModalBody');
    if (!modal || !body || !activeProfilePatient) return;
    const tid = String(treatmentId || '').trim();
    const pid = String(activeProfilePatient.patientId || '').trim();
    if (!tid || !pid) {
        await staffUiAlert({ title: 'Treatment', message: 'Missing patient or treatment reference.', variant: 'warning' });
        return;
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    syncModalBodyScrollLock();
    body.innerHTML = '<div class="flex justify-center py-16 text-slate-500 text-sm font-medium">Loading treatment progress…</div>';
    try {
        const url = new URL(API_TREATMENT_PROGRESS_MODAL_URL, window.location.origin);
        url.searchParams.set('patient_id', pid);
        url.searchParams.set('treatment_id', tid);
        const response = await fetch(url.toString(), { credentials: 'include' });
        const data = await parseJsonResponse(response);
        if (!response.ok || !data.success || !data.data) {
            throw new Error(data.message || 'Failed to load treatment progress.');
        }
        body.innerHTML = buildTreatmentProgressModalInnerHtml(data.data, pid);
    } catch (err) {
        body.innerHTML = `<p class="text-center text-rose-600 text-sm font-medium py-10">${escapeHtml(err.message || 'Failed to load.')}</p>`;
    }
}

let allPatientsData = [];
let viewPatientCloseTimer = null;
let activeProfilePatient = null;

const tableBody = document.getElementById('patientsTableBody');
const recordsSummary = document.getElementById('recordsSummary');
const searchInput = document.getElementById('searchInput');
const statusFilter = document.getElementById('statusFilter');
const genderFilter = document.getElementById('genderFilter');
const registrationDateFilter = document.getElementById('registrationDateFilter');
const addPatientModal = document.getElementById('addPatientModal');
const viewPatientModal = document.getElementById('viewPatientModal');
const viewPatientPanel = document.getElementById('viewPatientPanel');
const addPatientForm = document.getElementById('addPatientForm');
const addDobInput = document.getElementById('addDob');
const addAgeInput = document.getElementById('addAge');
const addProvinceSelect = document.getElementById('addProvince');
const addCitySelect = document.getElementById('addCity');
const addBarangaySelect = document.getElementById('addBarangay');
const addGenderRadios = Array.from(document.querySelectorAll('input[name="addGender"]'));
const bloodTypeOptions = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const fieldValidators = {
    addFirstName: { required: true, pattern: /^[a-zA-Z\s.'-]{2,50}$/, message: 'Enter a valid first name (2-50 letters).' },
    addLastName: { required: true, pattern: /^[a-zA-Z\s.'-]{2,50}$/, message: 'Enter a valid last name (2-50 letters).' },
    addDob: { required: true, message: 'Date of birth is required.' },
    addGender: { required: true, message: 'Gender is required.' },
    addBloodType: { required: true, message: 'Blood type is required.' },
    addMedicalHistory: { required: false },
    addContact: { required: true, pattern: /^09\d{9}$/, message: 'Use PH mobile format: 09XXXXXXXXX.' },
    addEmail: { required: true, pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/, message: 'Enter a valid email address.' },
    addProvince: { required: true, message: 'Province is required.' },
    addCity: { required: true, message: 'City/Municipality is required.' },
    addBarangay: { required: true, message: 'Barangay is required.' },
    addHouseStreet: { required: true, minLength: 3, message: 'Street / House No. is required.' }
};

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

/** Uses API target_completion_date (tbl_treatments-derived); fallback mirrors server +months logic */
function formatTreatmentTargetCompletion(treatment) {
    if (!treatment) return 'Not available';
    const apiDate = String(treatment.target_completion_date || '').trim();
    if (apiDate) return formatDate(apiDate);
    const startedRaw = treatment.started_at;
    const months = Number(treatment.duration_months || 0);
    if (!startedRaw || months <= 0) return 'Not available';
    const started = new Date(startedRaw);
    if (Number.isNaN(started.getTime())) return 'Not available';
    const end = new Date(started.getTime());
    end.setMonth(end.getMonth() + months);
    return formatDate(end.toISOString());
}

function formatDateTime(dateValue, timeValue) {
    const dateText = formatDate(dateValue);
    if (!timeValue) return dateText;
    const parsed = new Date(`1970-01-01T${String(timeValue).slice(0, 8)}`);
    const timeText = Number.isNaN(parsed.getTime())
        ? String(timeValue)
        : parsed.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
    return `${dateText} ${timeText}`;
}

function formatPeso(amount) {
    return `P${Number(amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function formatStatusLabel(rawStatus) {
    const status = String(rawStatus || '').trim().toLowerCase();
    if (!status) return 'Pending';
    return status.replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function getStatusBadgeClasses(rawStatus) {
    const status = String(rawStatus || '').trim().toLowerCase();
    if (status === 'completed') return 'bg-emerald-50 text-emerald-700';
    if (status === 'cancelled') return 'bg-rose-50 text-rose-700';
    if (status === 'in_progress') return 'bg-blue-50 text-blue-700';
    if (status === 'no_show') return 'bg-amber-50 text-amber-700';
    return 'bg-slate-100 text-slate-700';
}

function resolveDentistLabel(appointment) {
    const rawDentist = String(appointment.dentist_name || '').trim();
    if (rawDentist) return rawDentist;
    const fallbackFullName = [appointment.dentist_first_name, appointment.dentist_last_name]
        .map(v => String(v || '').trim())
        .filter(Boolean)
        .join(' ')
        .trim();
    if (fallbackFullName) return fallbackFullName;
    const dentistId = String(appointment.dentist_id || '').trim();
    return dentistId ? `Dentist #${dentistId}` : 'Unassigned';
}

async function parseJsonResponse(response) {
    const rawText = await response.text();
    if (!rawText || !rawText.trim()) return { success: false, message: 'Empty response from server.' };
    try {
        return JSON.parse(rawText);
    } catch (error) {
        return { success: false, message: 'Unexpected server response.' };
    }
}

function renderAppointmentsTab(appointments) {
    const section = document.getElementById('patient-tab-appointments');
    if (!section) return;
    if (!appointments.length) {
        section.innerHTML = `
            <div class="flex items-center gap-2 mb-4">
                <span class="material-symbols-outlined text-primary text-[22px]">event_note</span>
                <h5 class="text-base font-bold text-slate-900">Appointments</h5>
            </div>
            <p class="text-slate-500 text-sm font-medium">No appointment history found for this patient.</p>
        `;
        return;
    }
    const sorted = appointments.slice().sort((a, b) => {
        const aKey = `${a.appointment_date || ''} ${a.appointment_time || ''}`;
        const bKey = `${b.appointment_date || ''} ${b.appointment_time || ''}`;
        return aKey < bKey ? 1 : -1;
    });
    section.innerHTML = `
        <div class="flex items-center gap-2 mb-5">
            <span class="material-symbols-outlined text-primary text-[22px]">event_note</span>
            <h5 class="text-base font-bold text-slate-900">Appointments History</h5>
        </div>
        <div class="space-y-3">
            ${sorted.map((appointment) => `
                <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold text-slate-900">${escapeHtml(formatDateTime(appointment.appointment_date, appointment.appointment_time))}</p>
                            <p class="text-xs font-semibold text-slate-600 mt-1">Dentist: ${escapeHtml(resolveDentistLabel(appointment))}</p>
                            <p class="text-xs font-medium text-slate-500 mt-1">Service: ${escapeHtml(appointment.service_type || appointment.service_description || 'N/A')}</p>
                        </div>
                        <span class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide ${getStatusBadgeClasses(appointment.final_status || appointment.status)}">
                            ${escapeHtml(formatStatusLabel(appointment.final_status || appointment.status))}
                        </span>
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

function renderTreatmentTab(context) {
    const section = document.getElementById('patient-tab-treatment');
    if (!section) return;
    const hasTreatment = Boolean(context && context.has_active_treatment && context.treatment);
    if (!hasTreatment) {
        section.innerHTML = `
            <div class="rounded-xl border border-violet-200/90 bg-violet-50/70 p-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-sm shadow-primary/25">
                        <span class="material-symbols-outlined text-[22px]">medical_services</span>
                    </div>
                    <h5 class="text-base font-bold text-slate-900">Active Treatment</h5>
                </div>
                <p class="mt-4 text-slate-500 text-sm font-medium">No active installment treatment plan found.</p>
            </div>
        `;
        return;
    }
    const treatment = context.treatment;
    const progress = Math.max(0, Math.min(100, Number(treatment.progress_percentage ?? 0)));
    const targetCompletion = formatTreatmentTargetCompletion(treatment);
    const snapshotService = String(treatment.primary_service_name || '').trim();
    const catalogService = treatment.primary_service && treatment.primary_service.service_name
        ? String(treatment.primary_service.service_name).trim()
        : '';
    const serviceName = snapshotService || catalogService || 'Installment treatment';
    const dm = Number(treatment.duration_months ?? 0);
    const durationLabel = dm > 0 ? `${dm} Month${dm === 1 ? '' : 's'}` : '—';
    const planRef = String(treatment.treatment_id || '').trim();
    const monthsPaid = Number(treatment.months_paid ?? 0);
    const monthsLeft = Number(treatment.months_left ?? 0);
    const startedLabel = treatment.started_at ? formatDate(treatment.started_at) : 'N/A';
    const statusLabel = formatStatusLabel(treatment.status || 'active');
    const categoryBadgeClass = resolveInstallmentServiceCategoryBadgeClasses(treatment);
    section.innerHTML = `
        <div class="rounded-xl border border-violet-200/90 bg-gradient-to-b from-violet-50/90 to-violet-50/40 p-4 shadow-sm">
            <div class="flex items-center gap-3 mb-4">
                <div class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white shadow-md shadow-primary/20">
                    <span class="material-symbols-outlined text-[22px]">work</span>
                    <span class="absolute -bottom-1 -right-1 flex h-[18px] w-[18px] items-center justify-center rounded-full bg-white text-primary ring-2 ring-violet-50">
                        <span class="material-symbols-outlined text-[13px] leading-none font-bold">add</span>
                    </span>
                </div>
                <h5 class="text-base font-bold text-slate-900">Long-Term Treatment Plans</h5>
            </div>
            <div class="rounded-xl border border-slate-100 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 gap-y-2">
                    <span class="inline-flex max-w-[min(100%,280px)] items-center rounded-full px-3 py-1.5 text-xs font-bold uppercase tracking-wide ${categoryBadgeClass}">
                        ${escapeHtml(serviceName)}
                    </span>
                    <span class="text-xs font-medium text-slate-500">${planRef ? escapeHtml('#' + planRef) : ''}</span>
                </div>
                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Target completion</p>
                        <p class="mt-1.5 text-lg font-bold text-slate-900">${escapeHtml(targetCompletion)}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Duration</p>
                        <p class="mt-1.5 text-lg font-bold text-primary">${escapeHtml(durationLabel)}</p>
                    </div>
                </div>
                <div class="mt-6 border-t border-slate-100 pt-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Total cost</p>
                            <p class="mt-1 text-[15px] font-bold text-slate-900">${escapeHtml(formatPeso(treatment.total_cost))}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Amount paid</p>
                            <p class="mt-1 text-[15px] font-bold text-slate-900">${escapeHtml(formatPeso(treatment.amount_paid))}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Remaining balance</p>
                            <p class="mt-1 text-[15px] font-bold text-slate-900">${escapeHtml(formatPeso(treatment.remaining_balance))}</p>
                        </div>
                    </div>
                </div>
                <div class="mt-6">
                    <div class="flex items-center justify-between text-sm font-semibold text-slate-800">
                        <span>Payment progress</span>
                        <span class="text-sm font-medium text-slate-500">${escapeHtml(`${progress.toFixed(1)}%`)}</span>
                    </div>
                    <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full bg-primary transition-all" style="width:${progress}%"></div>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-5 text-sm">
                    <span class="font-medium text-slate-900">Started: <span class="font-semibold text-slate-800">${escapeHtml(startedLabel)}</span></span>
                    <span class="font-medium text-slate-500">${escapeHtml(statusLabel)}</span>
                </div>
                <div class="mt-5 border-t border-slate-100 pt-5">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Months paid</p>
                            <p class="mt-1 text-[15px] font-bold text-slate-900">${escapeHtml(String(monthsPaid))}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Months remaining</p>
                            <p class="mt-1 text-[15px] font-bold text-slate-900">${escapeHtml(String(monthsLeft))}</p>
                        </div>
                    </div>
                </div>
                <button type="button" id="viewTreatmentProgressBtn" data-treatment-id="${escapeHtml(String(treatment.treatment_id || ''))}" class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl border border-violet-100 bg-violet-50/90 py-3 text-sm font-semibold text-primary transition-colors hover:bg-violet-100">
                    <span class="material-symbols-outlined text-[20px] text-primary">calendar_month</span>
                    View Treatment Progress
                </button>
            </div>
        </div>
    `;
    const progressBtn = section.querySelector('#viewTreatmentProgressBtn');
    if (progressBtn) {
        progressBtn.addEventListener('click', () => {
            openTreatmentProgressModal(progressBtn.getAttribute('data-treatment-id'));
        });
    }
}

function renderFilesTab(files) {
    const section = document.getElementById('patient-tab-files');
    if (!section || !activeProfilePatient) return;
    section.innerHTML = `
        <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[22px]">folder_open</span>
                <h5 class="text-base font-bold text-slate-900">Files & Documents</h5>
            </div>
            <form id="patientFileUploadForm" class="flex flex-wrap items-center gap-2">
                <input id="patientFileInput" type="file" class="block text-xs font-semibold text-slate-600 max-w-[200px] file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-primary/10 file:text-primary file:font-bold"/>
                <button type="submit" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-primary text-white text-xs font-bold uppercase tracking-wider hover:bg-primary/90 transition-all shadow-sm shadow-primary/20">
                    <span class="material-symbols-outlined text-[16px] text-white">upload_file</span> Upload File
                </button>
            </form>
        </div>
        <div id="patientFilesListContainer">
            ${files.length ? `
                <div class="space-y-3">
                    ${files.map((file) => `
                        <div class="rounded-lg border border-slate-100 bg-slate-50/80 p-4 flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-900">${escapeHtml(file.file_name || 'Unnamed file')}</p>
                                <p class="text-xs text-slate-600 mt-1">Type: ${escapeHtml(file.file_type || 'Unknown')} • Uploaded: ${escapeHtml(file.formatted_date || formatDate(file.created_at))}</p>
                            </div>
                            <a href="${escapeHtml(file.file_url || '#')}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-slate-200 text-slate-700 text-xs font-bold hover:bg-white transition-all shrink-0">
                                <span class="material-symbols-outlined text-[16px] text-primary">download</span> View / Download
                            </a>
                        </div>
                    `).join('')}
                </div>
            ` : `<p class="text-slate-500 text-sm font-medium">No uploaded files yet for this patient.</p>`}
        </div>
    `;
    const uploadForm = document.getElementById('patientFileUploadForm');
    if (uploadForm) {
        uploadForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            await uploadPatientFileForActiveProfile();
        });
    }
}

async function loadAppointmentsForPatient(patient) {
    const section = document.getElementById('patient-tab-appointments');
    if (section) {
        section.innerHTML = '<p class="text-slate-500 text-sm font-medium py-2">Loading appointments...</p>';
    }
    try {
        const url = new URL(API_APPOINTMENTS_URL, window.location.origin);
        url.searchParams.set('patient_id', String(patient.patientId || ''));
        const response = await fetch(url.toString(), { credentials: 'include' });
        const data = await parseJsonResponse(response);
        if (!response.ok || !data.success) throw new Error(data.message || 'Failed to load appointments.');
        const list = Array.isArray(data.data && data.data.appointments) ? data.data.appointments : [];
        renderAppointmentsTab(list);
    } catch (error) {
        if (section) {
            section.innerHTML = `<p class="text-rose-600 text-sm font-medium py-2">${escapeHtml(error.message || 'Failed to load appointments.')}</p>`;
        }
    }
}

async function loadTreatmentForPatient(patient) {
    const section = document.getElementById('patient-tab-treatment');
    if (section) {
        section.innerHTML = '<p class="text-slate-500 text-sm font-medium py-2">Loading treatment details...</p>';
    }
    try {
        const url = new URL(API_TREATMENT_CONTEXT_URL, window.location.origin);
        url.searchParams.set('patient_id', String(patient.patientId || ''));
        const response = await fetch(url.toString(), { credentials: 'include' });
        const data = await parseJsonResponse(response);
        if (!response.ok || !data.success) throw new Error(data.message || 'Failed to load treatment context.');
        renderTreatmentTab(data.data || null);
    } catch (error) {
        if (section) {
            section.innerHTML = `<p class="text-rose-600 text-sm font-medium py-2">${escapeHtml(error.message || 'Failed to load treatment context.')}</p>`;
        }
    }
}

async function loadFilesForPatient(patient) {
    const section = document.getElementById('patient-tab-files');
    if (section) {
        section.innerHTML = '<p class="text-slate-500 text-sm font-medium py-2">Loading files...</p>';
    }
    try {
        const url = new URL(API_PATIENT_FILES_URL, window.location.origin);
        url.searchParams.set('patient_id', String(patient.patientId || ''));
        const response = await fetch(url.toString(), { credentials: 'include' });
        const data = await parseJsonResponse(response);
        if (!response.ok || !data.success) throw new Error(data.message || 'Failed to load patient files.');
        const files = Array.isArray(data.data && data.data.files) ? data.data.files : [];
        renderFilesTab(files);
    } catch (error) {
        if (section) {
            section.innerHTML = `<p class="text-rose-600 text-sm font-medium py-2">${escapeHtml(error.message || 'Failed to load patient files.')}</p>`;
        }
    }
}

async function uploadPatientFileForActiveProfile() {
    if (!activeProfilePatient) return;
    const input = document.getElementById('patientFileInput');
    const file = input && input.files ? input.files[0] : null;
    if (!file) {
        await staffUiAlert({ title: 'File required', message: 'Please select a file to upload.', variant: 'warning' });
        return;
    }
    const formData = new FormData();
    formData.append('file', file);
    formData.append('patient_id', String(activeProfilePatient.patientId || ''));
    formData.append('file_category', 'General');
    try {
        const response = await fetch(API_PATIENT_FILES_URL, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });
        const data = await parseJsonResponse(response);
        if (!response.ok || !data.success) throw new Error(data.message || 'Failed to upload file.');
        if (input) input.value = '';
        await staffUiAlert({ title: 'Upload successful', message: 'Patient file uploaded successfully.', variant: 'success' });
        await loadFilesForPatient(activeProfilePatient);
    } catch (error) {
        await staffUiAlert({ title: 'Upload failed', message: error.message || 'Failed to upload file.', variant: 'error' });
    }
}

function normalizePatient(p) {
    return {
        id: p.id,
        patientId: p.patient_id || '',
        firstName: p.first_name || '',
        middleName: p.middle_name || '',
        lastName: p.last_name || '',
        contact: p.contact_number || '',
        email: p.email || '',
        gender: p.gender || '',
        bloodType: p.blood_type || '',
        medicalHistory: p.medical_history || '',
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

function calculateAge(dateValue) {
    if (!dateValue) return '';
    const birthDate = new Date(dateValue);
    if (Number.isNaN(birthDate.getTime())) return '';
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }
    return age >= 0 ? String(age) : '';
}

function getDobMaxDateForMinimumAge(minYears = 1) {
    const maxDate = new Date();
    maxDate.setFullYear(maxDate.getFullYear() - minYears);
    return maxDate.toISOString().slice(0, 10);
}

function isAtLeastOneYearOld(dateValue) {
    if (!dateValue) return false;
    const birthDate = new Date(dateValue);
    if (Number.isNaN(birthDate.getTime())) return false;
    const maxDob = new Date(getDobMaxDateForMinimumAge(1));
    return birthDate <= maxDob;
}

function applyDobConstraints() {
    if (!addDobInput) return;
    addDobInput.max = getDobMaxDateForMinimumAge(1);
}

function clearFieldError(fieldId) {
    const inputEl = document.getElementById(fieldId);
    const errorEl = document.getElementById(`${fieldId}Error`);
    if (inputEl) inputEl.classList.remove('field-error');
    if (errorEl) {
        errorEl.textContent = '';
        errorEl.classList.add('hidden');
    }
}

function showFieldError(fieldId, message) {
    const inputEl = document.getElementById(fieldId);
    const errorEl = document.getElementById(`${fieldId}Error`);
    if (inputEl) inputEl.classList.add('field-error');
    if (errorEl) {
        errorEl.textContent = message;
        errorEl.classList.remove('hidden');
    }
}

function resetFormValidation() {
    Object.keys(fieldValidators).forEach(clearFieldError);
}

function getSelectedGender() {
    const selected = addGenderRadios.find(r => r.checked);
    return selected ? selected.value : '';
}

async function fetchAddress(action, params = {}) {
    const url = new URL(API_ADDRESS_URL, window.location.origin);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([key, value]) => url.searchParams.set(key, value));
    const response = await fetch(url.toString(), { credentials: 'include' });
    const data = await response.json();
    if (!response.ok || !data.success || !Array.isArray(data.data)) {
        throw new Error(data.message || 'Failed to load address data.');
    }
    return data.data;
}

function fillSelect(selectEl, values, placeholder) {
    selectEl.innerHTML = `<option value="">${placeholder}</option>` + values
        .map(v => `<option value="${escapeHtml(v)}">${escapeHtml(v)}</option>`)
        .join('');
    selectEl.disabled = values.length === 0;
}

async function loadProvinces(selectedProvince = '') {
    try {
        const provinces = await fetchAddress('provinces');
        fillSelect(addProvinceSelect, provinces, 'Select province');
        addProvinceSelect.value = selectedProvince || '';
    } catch (error) {
        fillSelect(addProvinceSelect, [], 'Unable to load provinces');
    }
}

async function loadCities(province, selectedCity = '') {
    if (!province) {
        fillSelect(addCitySelect, [], 'Select city/municipality');
        fillSelect(addBarangaySelect, [], 'Select barangay');
        return;
    }
    try {
        const cities = await fetchAddress('cities', { province });
        fillSelect(addCitySelect, cities, 'Select city/municipality');
        addCitySelect.value = selectedCity || '';
    } catch (error) {
        fillSelect(addCitySelect, [], 'Unable to load cities');
    }
}

async function loadBarangays(province, city, selectedBarangay = '') {
    if (!province || !city) {
        fillSelect(addBarangaySelect, [], 'Select barangay');
        return;
    }
    try {
        const barangays = await fetchAddress('barangays', { province, city });
        fillSelect(addBarangaySelect, barangays, 'Select barangay');
        addBarangaySelect.value = selectedBarangay || '';
    } catch (error) {
        fillSelect(addBarangaySelect, [], 'Unable to load barangays');
    }
}

function validatePatientForm(payload) {
    resetFormValidation();
    let isValid = true;

    Object.entries(fieldValidators).forEach(([fieldId, rules]) => {
        const value = (fieldId === 'addGender' ? payload.gender : (document.getElementById(fieldId)?.value || '')).trim();
        if (rules.required && !value) {
            showFieldError(fieldId, rules.message);
            isValid = false;
            return;
        }
        if (value && rules.minLength && value.length < rules.minLength) {
            showFieldError(fieldId, rules.message);
            isValid = false;
            return;
        }
        if (value && rules.pattern && !rules.pattern.test(value)) {
            showFieldError(fieldId, rules.message);
            isValid = false;
        }
    });

    if (payload.date_of_birth) {
        const selectedDob = new Date(payload.date_of_birth);
        if (Number.isNaN(selectedDob.getTime())) {
            showFieldError('addDob', 'Enter a valid date of birth.');
            isValid = false;
        } else if (!isAtLeastOneYearOld(payload.date_of_birth)) {
            showFieldError('addDob', 'Patient must be at least 1 year old.');
            isValid = false;
        }
    }

    if (payload.blood_type && !bloodTypeOptions.includes(payload.blood_type)) {
        showFieldError('addBloodType', 'Select a valid blood type.');
        isValid = false;
    }

    return isValid;
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
    document.getElementById('patientModalTitle').textContent = patient ? 'Edit Patient Registration' : 'Patient Registration';
    document.getElementById('editingPatientId').value = patient ? String(patient.id) : '';
    document.getElementById('addFirstName').value = patient?.firstName || '';
    document.getElementById('addLastName').value = patient?.lastName || '';
    document.getElementById('addContact').value = patient?.contact || '';
    document.getElementById('addEmail').value = patient?.email || '';
    document.getElementById('addDob').value = patient?.dob ? String(patient.dob).slice(0, 10) : '';
    addAgeInput.value = calculateAge(document.getElementById('addDob').value);
    addGenderRadios.forEach(radio => {
        radio.checked = Boolean(patient?.gender) && radio.value === patient.gender;
    });
    document.getElementById('addBloodType').value = patient?.bloodType || '';
    document.getElementById('addMedicalHistory').value = patient?.medicalHistory || '';
    document.getElementById('addHouseStreet').value = patient?.houseStreet || '';
    loadProvinces(patient?.province || '').then(() => loadCities(patient?.province || '', patient?.city || ''))
        .then(() => loadBarangays(patient?.province || '', patient?.city || '', patient?.barangay || ''));
    resetFormValidation();
    document.getElementById('savePatientBtn').textContent = patient ? 'Save Patient' : 'Register Patient';
    addPatientModal.classList.remove('hidden');
    addPatientModal.classList.add('flex');
    syncModalBodyScrollLock();
}

function closeAddModal() {
    addPatientForm.reset();
    document.getElementById('editingPatientId').value = '';
    addAgeInput.value = '';
    addGenderRadios.forEach(radio => { radio.checked = false; });
    fillSelect(addCitySelect, [], 'Select city/municipality');
    fillSelect(addBarangaySelect, [], 'Select barangay');
    resetFormValidation();
    addPatientModal.classList.add('hidden');
    addPatientModal.classList.remove('flex');
    syncModalBodyScrollLock();
}

function openViewModal(patient) {
    if (viewPatientCloseTimer) {
        clearTimeout(viewPatientCloseTimer);
        viewPatientCloseTimer = null;
    }
    const fullName = `${patient.firstName} ${patient.lastName}`.trim() || 'Patient';
    const patientStatus = patient.status === 'inactive' ? 'Inactive' : 'Active';
    const statusBadgeClass = patient.status === 'inactive'
        ? 'bg-slate-100 text-slate-600 border border-slate-200/80'
        : 'bg-emerald-50 text-emerald-700 border border-emerald-100';
    const statusDotClass = patient.status === 'inactive' ? 'bg-slate-400' : 'bg-emerald-500';
    const genderLower = String(patient.gender || '').toLowerCase();
    const genderIcon = genderLower.startsWith('f') ? 'female' : (genderLower.startsWith('m') ? 'male' : 'wc');
    const houseStreet = patient.houseStreet || 'N/A';
    const barangayCity = [patient.barangay, patient.city].filter(Boolean).join(', ') || 'N/A';
    const province = patient.province || 'N/A';
    activeProfilePatient = patient;
    document.getElementById('viewPatientContent').innerHTML = `
        <div class="flex flex-col flex-1 min-h-0">
            <div class="shrink-0 px-6 pt-8 pb-0 pr-14 border-b border-slate-100 bg-white">
                <div class="flex gap-4 items-start">
                    <div class="relative h-[72px] w-[72px] shrink-0">
                        <div class="h-full w-full rounded-full bg-slate-100 text-slate-700 font-bold text-xl flex items-center justify-center ring-1 ring-slate-200/80">
                            ${escapeHtml(`${patient.firstName?.[0] || ''}${patient.lastName?.[0] || ''}`.toUpperCase() || 'PT')}
                        </div>
                        <span class="absolute bottom-0 right-0 z-[1] h-3.5 w-3.5 translate-x-0.5 translate-y-0.5 rounded-full ${statusDotClass} ring-[2.5px] ring-white shadow-sm" title="${escapeHtml(patientStatus)}" aria-hidden="true"></span>
                    </div>
                    <div class="min-w-0 flex-1 pt-0.5">
                        <div class="flex flex-wrap items-center gap-2 gap-y-1">
                            <h4 class="text-[1.35rem] font-extrabold tracking-tight text-slate-900 leading-snug">${escapeHtml(fullName)}</h4>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide ${statusBadgeClass}">${patientStatus}</span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-2 text-slate-500 text-sm">
                            <span class="material-symbols-outlined text-[18px] text-primary">badge</span>
                            <span class="font-medium">#${escapeHtml(String(patient.patientId || patient.id))}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mt-5">
                            <div>
                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Created at</p>
                                <div class="flex items-center gap-2 mt-1.5 text-slate-800 text-sm font-medium">
                                    <span class="material-symbols-outlined text-primary text-[18px]">calendar_today</span>
                                    <span>${escapeHtml(formatDate(patient.createdAt))}</span>
                                </div>
                            </div>
                            <div>
                                <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Last visit</p>
                                <div class="flex items-center gap-2 mt-1.5 text-slate-800 text-sm font-medium">
                                    <span class="material-symbols-outlined text-primary text-[18px]">history</span>
                                    <span>${escapeHtml(formatDate(patient.updatedAt || patient.createdAt))}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-6 mt-6 border-b border-slate-100">
                    <button type="button" class="patient-tab-btn active" data-tab-target="basic">Basic Info</button>
                    <button type="button" class="patient-tab-btn" data-tab-target="appointments">Appointments</button>
                    <button type="button" class="patient-tab-btn" data-tab-target="treatment">Active Treatment</button>
                    <button type="button" class="patient-tab-btn" data-tab-target="files">Files & Documents</button>
                </div>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto px-6 py-5">
                <div class="view-patient-card p-6 shadow-sm">
                    <div id="patient-tab-basic" class="space-y-8">
                        <section>
                            <div class="flex items-center gap-2 mb-5">
                                <span class="material-symbols-outlined text-primary text-[22px]">person</span>
                                <h5 class="text-base font-bold text-slate-900">Basic Information</h5>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">First name</p>
                                    <p class="mt-1.5 text-[15px] font-semibold text-slate-900">${escapeHtml(patient.firstName || 'N/A')}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Last name</p>
                                    <p class="mt-1.5 text-[15px] font-semibold text-slate-900">${escapeHtml(patient.lastName || 'N/A')}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Contact number</p>
                                    <div class="flex items-center gap-2 mt-1.5 text-[15px] font-medium text-slate-900">
                                        <span class="material-symbols-outlined text-primary text-[18px] shrink-0">call</span>
                                        <span>${escapeHtml(patient.contact || 'N/A')}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Email</p>
                                    <div class="flex items-center gap-2 mt-1.5 text-[15px] font-medium text-slate-900 min-w-0">
                                        <span class="material-symbols-outlined text-primary text-[18px] shrink-0">mail</span>
                                        <span class="truncate">${escapeHtml(patient.email || 'N/A')}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Date of birth</p>
                                    <div class="flex items-center gap-2 mt-1.5 text-[15px] font-medium text-slate-900">
                                        <span class="material-symbols-outlined text-primary text-[18px] shrink-0">cake</span>
                                        <span>${escapeHtml(formatDate(patient.dob))}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Gender</p>
                                    <div class="flex items-center gap-2 mt-1.5 text-[15px] font-medium text-slate-900">
                                        <span class="material-symbols-outlined text-primary text-[18px] shrink-0">${genderIcon}</span>
                                        <span>${escapeHtml(patient.gender || 'N/A')}</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <div class="border-t border-slate-100"></div>
                        <section>
                            <div class="flex items-center gap-2 mb-5">
                                <span class="material-symbols-outlined text-primary text-[22px]">home</span>
                                <h5 class="text-base font-bold text-slate-900">Address Information</h5>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-6">
                                <div class="sm:col-span-2">
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">House no. & street</p>
                                    <div class="flex items-start gap-2 mt-1.5 text-[15px] font-medium text-slate-900">
                                        <span class="material-symbols-outlined text-primary text-[18px] shrink-0 mt-0.5">home_pin</span>
                                        <span>${escapeHtml(houseStreet)}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Barangay / city</p>
                                    <div class="flex items-start gap-2 mt-1.5 text-[15px] font-medium text-slate-900">
                                        <span class="material-symbols-outlined text-primary text-[18px] shrink-0 mt-0.5">apartment</span>
                                        <span>${escapeHtml(barangayCity)}</span>
                                    </div>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Province</p>
                                    <div class="flex items-start gap-2 mt-1.5 text-[15px] font-medium text-slate-900">
                                        <span class="material-symbols-outlined text-primary text-[18px] shrink-0 mt-0.5">map</span>
                                        <span>${escapeHtml(province)}</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div id="patient-tab-appointments" class="hidden">
                        <div class="py-2"><p class="text-slate-500 text-sm font-medium">Loading appointments...</p></div>
                    </div>
                    <div id="patient-tab-treatment" class="hidden">
                        <div class="py-2"><p class="text-slate-500 text-sm font-medium">Loading treatment details...</p></div>
                    </div>
                    <div id="patient-tab-files" class="hidden">
                        <div class="py-2"><p class="text-slate-500 text-sm font-medium">Loading files...</p></div>
                    </div>
                </div>
            </div>
        </div>
    `;
    setupPatientTabs();
    void loadAppointmentsForPatient(patient);
    void loadTreatmentForPatient(patient);
    void loadFilesForPatient(patient);
    viewPatientModal.classList.remove('hidden');
    requestAnimationFrame(() => {
        viewPatientPanel.classList.remove('translate-x-full');
        viewPatientPanel.classList.add('translate-x-0');
    });
}

function setupPatientTabs() {
    const root = document.getElementById('viewPatientContent');
    if (!root) return;
    const tabButtons = Array.from(root.querySelectorAll('[data-tab-target]'));
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
    if (viewPatientCloseTimer) {
        clearTimeout(viewPatientCloseTimer);
    }
    viewPatientPanel.classList.add('translate-x-full');
    viewPatientPanel.classList.remove('translate-x-0');
    viewPatientCloseTimer = window.setTimeout(() => {
        viewPatientModal.classList.add('hidden');
        activeProfilePatient = null;
        syncModalBodyScrollLock();
        viewPatientCloseTimer = null;
    }, 300);
}

function syncModalBodyScrollLock() {
    const addOpen = addPatientModal && !addPatientModal.classList.contains('hidden');
    const viewOpen = viewPatientModal && !viewPatientModal.classList.contains('hidden');
    const tpModal = document.getElementById('treatmentProgressModal');
    const tpOpen = tpModal && !tpModal.classList.contains('hidden');
    document.body.style.overflow = (addOpen || viewOpen || tpOpen) ? 'hidden' : '';
}

async function savePatient(event) {
    event.preventDefault();
    const editingId = document.getElementById('editingPatientId').value.trim();
    const selectedGender = getSelectedGender();
    const payload = {
        first_name: document.getElementById('addFirstName').value.trim(),
        last_name: document.getElementById('addLastName').value.trim(),
        contact_number: document.getElementById('addContact').value.trim(),
        mobile: document.getElementById('addContact').value.trim(),
        email: document.getElementById('addEmail').value.trim(),
        date_of_birth: document.getElementById('addDob').value,
        gender: selectedGender,
        blood_type: document.getElementById('addBloodType').value.trim(),
        medical_history: document.getElementById('addMedicalHistory').value.trim(),
        house_street: document.getElementById('addHouseStreet').value.trim(),
        barangay: document.getElementById('addBarangay').value,
        city_municipality: document.getElementById('addCity').value,
        province: document.getElementById('addProvince').value
    };

    if (!validatePatientForm(payload)) {
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
            payload.owner_user_id = STAFF_OWNER_USER_ID || '';
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
        await staffUiAlert({ message: error.message || 'Failed to save patient.', variant: 'error', title: 'Could not save patient' });
    } finally {
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldHtml;
    }
}

async function deletePatient(patientId) {
    const ok = await staffUiConfirm({
        title: 'Delete patient record?',
        message: 'This action cannot be undone.',
        confirmLabel: 'Delete',
        cancelLabel: 'Cancel',
        variant: 'danger'
    });
    if (!ok) return;
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
        await staffUiAlert({ message: error.message || 'Failed to delete patient.', variant: 'error', title: 'Could not delete patient' });
    }
}

document.getElementById('addNewPatientBtn').addEventListener('click', () => openAddModal(null));
document.getElementById('closeAddPatientModal').addEventListener('click', closeAddModal);
document.getElementById('cancelAddPatientBtn').addEventListener('click', closeAddModal);
document.getElementById('closeViewPatientModal').addEventListener('click', closeViewModal);
addPatientModal.addEventListener('click', e => { if (e.target === addPatientModal) closeAddModal(); });
viewPatientModal.addEventListener('click', e => { if (e.target === viewPatientModal) closeViewModal(); });
addPatientForm.addEventListener('submit', savePatient);
addDobInput.addEventListener('change', () => {
    addAgeInput.value = calculateAge(addDobInput.value);
    if (addDobInput.value && !isAtLeastOneYearOld(addDobInput.value)) {
        showFieldError('addDob', 'Patient must be at least 1 year old.');
    } else {
        clearFieldError('addDob');
    }
});
addProvinceSelect.addEventListener('change', async () => {
    clearFieldError('addProvince');
    await loadCities(addProvinceSelect.value);
    fillSelect(addBarangaySelect, [], 'Select barangay');
});
addCitySelect.addEventListener('change', async () => {
    clearFieldError('addCity');
    await loadBarangays(addProvinceSelect.value, addCitySelect.value);
});
addBarangaySelect.addEventListener('change', () => clearFieldError('addBarangay'));
addGenderRadios.forEach(radio => radio.addEventListener('change', () => clearFieldError('addGender')));
Object.keys(fieldValidators).forEach(fieldId => {
    if (fieldId === 'addGender') return;
    const inputEl = document.getElementById(fieldId);
    if (!inputEl) return;
    inputEl.addEventListener('input', () => clearFieldError(fieldId));
    inputEl.addEventListener('change', () => clearFieldError(fieldId));
});

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
    staffUiAlert({
        title: 'Coming soon',
        message: 'Schedule workflow will be wired to appointments module.',
        variant: 'info'
    });
});

document.getElementById('closeTreatmentProgressModalX').addEventListener('click', closeTreatmentProgressModal);
document.getElementById('closeTreatmentProgressModalFooter').addEventListener('click', closeTreatmentProgressModal);
document.getElementById('treatmentProgressModal').addEventListener('click', function (e) {
    if (e.target === this) {
        closeTreatmentProgressModal();
    }
});
document.getElementById('treatmentProgressModalBody').addEventListener('click', function (e) {
    const payBtn = e.target.closest('[data-treatment-progress-pay]');
    if (payBtn) {
        const pid = payBtn.getAttribute('data-patient-id');
        const bid = payBtn.getAttribute('data-booking-id');
        let url = CLINIC_STAFF_BASE + 'StaffPaymentRecording.php?patient_id=' + encodeURIComponent(pid || '');
        if (bid) {
            url += '&booking_id=' + encodeURIComponent(bid);
        }
        window.location.href = url;
        return;
    }
    const schBtn = e.target.closest('[data-treatment-progress-schedule]');
    if (schBtn) {
        const pid = schBtn.getAttribute('data-patient-id');
        window.location.href = CLINIC_STAFF_BASE + 'StaffSetAppointments.php?patient_id=' + encodeURIComponent(pid || '');
    }
});

applyDobConstraints();
loadProvinces();
loadPatients();
</script>
</body>
</html>