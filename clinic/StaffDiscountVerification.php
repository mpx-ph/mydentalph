<?php
$staff_nav_active = 'discount_verification';
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}

$staffDisplayName = trim((string) ($_SESSION['user_name'] ?? ''));
if ($staffDisplayName === '') {
    $staffDisplayName = trim((string) ($_SESSION['full_name'] ?? ''));
}
if ($staffDisplayName === '') {
    $staffDisplayName = trim((string) ($_SESSION['first_name'] ?? '') . ' ' . (string) ($_SESSION['last_name'] ?? ''));
}
if ($staffDisplayName === '') {
    $staffDisplayName = 'Staff';
}

$servicesApiUrl = PROVIDER_BASE_URL . 'clinic/api/services.php';
$discountProgramsApiUrl = PROVIDER_BASE_URL . 'clinic/api/discount_programs.php';
$discountVerificationsApiUrl = PROVIDER_BASE_URL . 'clinic/api/discount_verifications.php';
$patientsApiPath = rtrim((string) dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/api/patients.php';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Discount Verification - Staff Portal</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            colors: {
                primary: '#2b8beb',
                background: '#f8fafc',
                surface: '#ffffff',
                'on-background': '#101922',
                'on-surface-variant': '#404752'
            },
            fontFamily: {
                headline: ['Manrope', 'sans-serif'],
                body: ['Manrope', 'sans-serif'],
                editorial: ['Playfair Display', 'serif']
            }
        }
    }
};
</script>
<style>
body { font-family: 'Manrope', sans-serif; }
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
.elevated-card:hover { box-shadow: 0 12px 32px -12px rgba(15, 23, 42, 0.1); }
.provider-page-enter {
    animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
@keyframes provider-page-in {
    from { opacity: 0; transform: translateY(14px); }
    to { opacity: 1; transform: translateY(0); }
}
.staff-modal-overlay:not(.hidden) {
    animation: staff-modal-fade-in 0.25s ease forwards;
    overscroll-behavior: contain;
}
.staff-modal-overlay:not(.hidden) { display: flex !important; }
.staff-modal-panel {
    animation: staff-modal-panel-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}
@keyframes staff-modal-fade-in { from { opacity: 0; } to { opacity: 1; } }
@keyframes staff-modal-panel-in {
    from { opacity: 0; transform: translateY(10px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
.toggle-peer:checked + .toggle-track { background-color: #2b8beb; }
.toggle-peer:checked + .toggle-track .toggle-knob { transform: translateX(1.25rem); }
</style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<div class="p-10 space-y-8 max-w-[1600px] mx-auto w-full">

    <section class="flex flex-col gap-4">
        <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
            <span class="w-12 h-[1.5px] bg-primary"></span> PATIENTS &amp; SERVICES
        </div>
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6">
            <div>
                <h2 class="font-headline text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Discount <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Verification</span>
                </h2>
                <p class="font-body text-lg sm:text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                    Configure clinic discount programs, verify ID-backed applications, and review approval history.
                </p>
            </div>
            <div class="flex flex-wrap gap-3 shrink-0">
                <button type="button" id="btnNewProgram" class="bg-primary hover:bg-primary/90 text-white px-6 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-primary/30 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">add_circle</span>
                    New discount program
                </button>
                <button type="button" id="btnNewApplication" class="border border-slate-200 bg-white hover:bg-slate-50 text-slate-800 px-6 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">assignment_add</span>
                    Record application
                </button>
            </div>
        </div>
    </section>

    <!-- A: Discount programs -->
    <section class="space-y-4">
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <h3 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">A. Discount programs</h3>
            <p class="text-xs text-slate-500 max-w-xl">Define requirements, amounts, validity, which services apply, and whether discounts can stack. Use the toggle for quick enable/disable.</p>
        </div>
        <div id="programsGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            <p class="text-slate-500 col-span-full py-6 text-center text-sm">Loading programs…</p>
        </div>
    </section>

    <!-- B + C + D: Filters + History -->
    <section class="elevated-card rounded-3xl overflow-hidden">
        <div class="p-6 border-b border-slate-100 bg-white space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <h3 class="text-sm font-black text-slate-800 uppercase tracking-[0.15em] flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[22px]">history</span>
                    C. Verification history
                </h3>
                <p id="pendingBadge" class="text-xs font-bold text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-full inline-flex items-center gap-1.5 w-fit">
                    <span class="material-symbols-outlined text-[16px]">pending_actions</span>
                    <span id="pendingCount">0</span> pending
                </p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">D. Requirements</label>
                    <select id="filterRequirements" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold">
                        <option value="all">All</option>
                        <option value="proof">Upload proof required</option>
                        <option value="notes">Notes required</option>
                        <option value="both">Both required</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Status</label>
                    <select id="filterStatus" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold">
                        <option value="all">All</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date from</label>
                    <input id="filterDateFrom" type="date" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold"/>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date to</label>
                    <input id="filterDateTo" type="date" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold"/>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Approved by</label>
                    <select id="filterStaff" class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold">
                        <option value="all">Any staff</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Patient name</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person_search</span>
                        <input id="filterPatient" type="search" placeholder="Search…" class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 text-sm font-bold"/>
                    </div>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[920px]">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Discount type</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">ID number</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Approved by</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Remarks</th>
                        <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Action</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody" class="divide-y divide-slate-100">
                    <tr><td colspan="8" class="px-6 py-10 text-center text-slate-500">Loading…</td></tr>
                </tbody>
            </table>
        </div>
        <div class="p-6 bg-slate-50/30 border-t border-slate-100">
            <p id="historySummary" class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing 0 records</p>
        </div>
    </section>
</div>
</main>

<!-- Modal: Discount program (add/edit) -->
<div id="programModal" class="staff-modal-overlay fixed inset-0 z-[75] hidden items-center justify-center bg-slate-900/50 backdrop-blur-[2px] p-4" aria-hidden="true">
    <div class="staff-modal-panel bg-white rounded-3xl shadow-[0_24px_64px_-12px_rgba(15,23,42,0.25)] border border-slate-100 w-full max-w-2xl max-h-[92vh] overflow-hidden flex flex-col">
        <div class="shrink-0 px-6 sm:px-8 pt-7 pb-5 border-b border-slate-100 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                <span class="material-symbols-outlined text-2xl text-primary">percent</span>
            </div>
            <div class="min-w-0 flex-1">
                <h2 id="programModalTitle" class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight">New discount program</h2>
                <p class="text-sm text-slate-500 mt-1">Configure how this discount applies before patients use it at checkout.</p>
            </div>
            <button type="button" class="program-modal-close shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100" aria-label="Close">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <form id="programForm" class="flex-1 min-h-0 overflow-y-auto px-6 sm:px-8 py-6 space-y-6">
            <input type="hidden" id="programId" value=""/>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Discount name</label>
                <input id="programName" required type="text" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="e.g. Senior Citizen Discount"/>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Discount type</label>
                    <select id="programDiscountType" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold bg-white">
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed amount (₱)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2" id="programValueLabel">Value</label>
                    <input id="programValue" required type="number" min="0" step="0.01" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="20"/>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Minimum spend (₱)</label>
                <input id="programMinSpend" type="number" min="0" step="1" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="0"/>
                <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">Cart / invoice subtotal must reach this amount before the discount applies. Use <span class="font-semibold text-slate-600">0</span> for no minimum.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Minimum age (years)</label>
                    <input id="programAgeMin" type="number" min="0" max="150" step="1" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="Leave blank if none"/>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Maximum age (years)</label>
                    <input id="programAgeMax" type="number" min="0" max="150" step="1" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/15" placeholder="Leave blank if none"/>
                </div>
            </div>
            <p class="text-xs text-slate-500 -mt-2 leading-relaxed">Optional eligibility window by patient age at treatment (leave both blank for any age). Values are clamped to 0–150.</p>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Requirements</label>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 space-y-3">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input id="reqUploadProof" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary w-4 h-4"/>
                        <span class="text-sm font-semibold text-slate-800 group-hover:text-slate-900">Upload proof</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input id="reqNotes" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary w-4 h-4"/>
                        <span class="text-sm font-semibold text-slate-800 group-hover:text-slate-900">Notes</span>
                    </label>
                    <p class="text-xs text-slate-500 leading-relaxed pt-1">Staff/patients must satisfy checked items before approval (e.g. image upload and/or written notes).</p>
                </div>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-slate-200 p-4 bg-slate-50/80">
                <div>
                    <p class="text-sm font-bold text-slate-800">Status</p>
                    <p class="text-xs text-slate-500 mt-0.5">Disabled programs cannot be selected for new applications.</p>
                </div>
                <label class="relative inline-flex cursor-pointer items-center">
                    <input id="programEnabled" type="checkbox" class="toggle-peer sr-only peer" checked/>
                    <span class="toggle-track h-7 w-12 rounded-full bg-slate-300 transition-colors peer-focus:ring-2 peer-focus:ring-primary/30 relative">
                        <span class="toggle-knob absolute top-0.5 left-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"></span>
                    </span>
                    <span id="programEnabledLabel" class="ml-3 text-sm font-bold text-primary">Enabled</span>
                </label>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Valid from</label>
                    <input id="programStart" type="date" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold"/>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Valid until</label>
                    <input id="programEnd" type="date" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold"/>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Applicable services</label>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="radio" name="programScope" value="all" class="text-primary focus:ring-primary" checked/>
                        All services
                    </label>
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="radio" name="programScope" value="selected" class="text-primary focus:ring-primary"/>
                        Selected procedures only
                    </label>
                    <div id="programServicesWrap" class="hidden max-h-48 overflow-y-auto rounded-xl border border-slate-200 p-3 space-y-2 bg-white">
                        <p class="text-xs text-slate-500 mb-2">Loaded from your clinic service list when available.</p>
                        <div id="programServicesList" class="space-y-2"></div>
                    </div>
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Stacking rules</label>
                <select id="programStacking" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold bg-white">
                    <option value="no">Cannot combine with other discounts</option>
                    <option value="yes">May combine with other discounts</option>
                </select>
            </div>
        </form>
        <div class="shrink-0 px-6 sm:px-8 py-5 border-t border-slate-100 flex justify-end gap-3 bg-white">
            <button type="button" class="program-modal-close px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" form="programForm" class="px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold shadow-lg shadow-primary/25 hover:bg-primary/90">Save program</button>
        </div>
    </div>
</div>

<!-- Modal: Record new application -->
<div id="applicationModal" class="staff-modal-overlay fixed inset-0 z-[75] hidden items-center justify-center bg-slate-900/50 backdrop-blur-[2px] p-4" aria-hidden="true">
    <div class="staff-modal-panel bg-white rounded-3xl shadow-[0_24px_64px_-12px_rgba(15,23,42,0.25)] border border-slate-100 w-full max-w-lg max-h-[92vh] overflow-hidden flex flex-col">
        <div class="shrink-0 px-6 pt-7 pb-5 border-b border-slate-100 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                <span class="material-symbols-outlined text-2xl text-primary">assignment_add</span>
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="text-xl font-extrabold font-headline text-on-background tracking-tight">Record patient application</h2>
                <p class="text-sm text-slate-500 mt-1">Creates a pending verification row for staff follow-up.</p>
            </div>
            <button type="button" class="application-modal-close shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100" aria-label="Close">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <form id="applicationForm" class="flex-1 min-h-0 overflow-y-auto px-6 py-6 space-y-5">
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Patient name</label>
                <select id="appPatientSelect" required class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Select Registered Patient</option>
                </select>
                <input id="appPatientName" type="hidden" value=""/>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Patient ID (optional)</label>
                <input id="appPatientRef" type="text" readonly class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold bg-slate-50 text-slate-600 cursor-not-allowed" placeholder="Filled when you select a patient" autocomplete="off"/>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Discount type applied</label>
                <select id="appDiscountProgram" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold bg-white"></select>
            </div>
            <p id="appReqHint" class="text-xs text-slate-500 -mt-2 hidden"></p>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">ID number (reference)</label>
                <input id="appIdNumber" type="text" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold" placeholder="Government or membership ID (if applicable)"/>
            </div>
            <div id="appProofWrap">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Uploaded proof <span id="appProofRequiredTag" class="text-red-500 font-black hidden">*</span></label>
                <input id="appProofFile" type="file" accept="image/*" class="w-full text-sm"/>
                <div id="appProofPreview" class="mt-3 hidden rounded-xl overflow-hidden border border-slate-200 max-h-40 bg-slate-100">
                    <img id="appProofImg" src="" alt="Proof preview" class="w-full h-full object-contain"/>
                </div>
            </div>
            <div id="appNotesWrap" class="hidden">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Notes <span class="text-red-500 font-black">*</span></label>
                <textarea id="appNotes" rows="3" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-medium resize-y min-h-[88px]" placeholder="Patient or staff notes required by this discount program"></textarea>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Date applied</label>
                <input id="appDateApplied" type="date" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold"/>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Staff assigned</label>
                <input id="appStaffAssigned" type="text" readonly class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold bg-slate-50 text-slate-600"/>
            </div>
        </form>
        <div class="shrink-0 px-6 py-5 border-t border-slate-100 flex justify-end gap-3">
            <button type="button" class="application-modal-close px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" form="applicationForm" class="px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold shadow-lg shadow-primary/25">Submit application</button>
        </div>
    </div>
</div>

<!-- Modal: B. Verify / view details -->
<div id="verifyModal" class="staff-modal-overlay fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/50 backdrop-blur-[2px] p-4" aria-hidden="true">
    <div class="staff-modal-panel bg-white rounded-3xl shadow-[0_24px_64px_-12px_rgba(15,23,42,0.25)] border border-slate-100 w-full max-w-3xl max-h-[92vh] overflow-hidden flex flex-col">
        <div class="shrink-0 px-6 sm:px-8 pt-7 pb-5 border-b border-slate-100 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                <span class="material-symbols-outlined text-2xl text-primary">verified_user</span>
            </div>
            <div class="min-w-0 flex-1">
                <h2 id="verifyModalTitle" class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight">Application &amp; verification</h2>
                <p class="text-sm text-slate-500 mt-1">Review proof, notes, and program requirements before approving a discount.</p>
            </div>
            <button type="button" id="verifyModalClose" class="shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100" aria-label="Close">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <div class="flex-1 min-h-0 overflow-y-auto px-6 sm:px-8 py-6 space-y-6">
            <input type="hidden" id="verifyRecordId" value=""/>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Patient name / ID</p>
                    <p id="verifyPatientLine" class="text-sm font-bold text-slate-900">—</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Discount type applied</p>
                    <p id="verifyDiscountLine" class="text-sm font-bold text-slate-900">—</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">ID number</p>
                    <p id="verifyIdFull" class="text-sm font-mono font-bold text-slate-900">—</p>
                </div>
                <div class="rounded-2xl border border-slate-200 p-4 bg-slate-50/50">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Date applied · Staff assigned</p>
                    <p id="verifyMetaLine" class="text-sm font-semibold text-slate-800">—</p>
                </div>
                <div class="hidden md:col-span-2 rounded-2xl border border-slate-200 p-4 bg-white" id="verifyPatientNotesWrap">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Patient / application notes</p>
                    <p id="verifyPatientNotes" class="text-sm text-slate-800 whitespace-pre-wrap">—</p>
                </div>
            </div>
            <div>
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Uploaded proof</p>
                <div id="verifyProofBox" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 min-h-[160px] flex items-center justify-center overflow-hidden">
                    <span class="text-slate-400 text-sm">No image</span>
                </div>
            </div>
            <div id="verifyReadonlyNote" class="hidden rounded-2xl border border-slate-200 p-4 bg-white">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Remarks / notes</p>
                <p id="verifyRemarksRead" class="text-sm text-slate-700 whitespace-pre-wrap">—</p>
            </div>
            <div id="verifyActionBlock" class="space-y-3">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest">Staff remarks (optional)</label>
                <textarea id="verifyRemarksInput" rows="3" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm" placeholder="Internal notes visible in history…"></textarea>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="button" id="btnApprove" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-bold hover:bg-emerald-700">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span> Approve
                    </button>
                    <button type="button" id="btnReject" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-red-600 text-white text-sm font-bold hover:bg-red-700">
                        <span class="material-symbols-outlined text-[18px]">cancel</span> Reject
                    </button>
                    <button type="button" id="btnRequestInfo" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-amber-300 bg-amber-50 text-amber-900 text-sm font-bold hover:bg-amber-100">
                        <span class="material-symbols-outlined text-[18px]">mail</span> Request additional info
                    </button>
                </div>
                <p class="text-xs text-slate-500">“Request additional info” keeps the row in <strong>Pending</strong> and should be followed up with the patient.</p>
            </div>
        </div>
    </div>
</div>

<script>
window.STAFF_DISCOUNT_V = <?php echo json_encode(array(
    'programsApi' => $discountProgramsApiUrl,
    'verificationsApi' => $discountVerificationsApiUrl,
    'servicesApi' => $servicesApiUrl,
    'patientsApi' => $patientsApiPath,
    'staffName' => $staffDisplayName,
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars(rtrim(BASE_URL, '/') . '/js/staff-discount-verification.js', ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
