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

$tenantIdJs = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
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
                <input id="appPatientName" required type="text" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold" placeholder="Full name"/>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Patient ID (optional)</label>
                <input id="appPatientRef" type="text" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold" placeholder="Internal chart / record ID"/>
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
(function () {
    var TENANT = <?php echo json_encode($tenantIdJs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    var STAFF_NAME = <?php echo json_encode($staffDisplayName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;
    var SERVICES_API = <?php echo json_encode($servicesApiUrl, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?>;

    var LS_PROGRAMS = 'mdp_discount_programs_' + (TENANT || 'default');
    var LS_RECORDS = 'mdp_discount_verifications_' + (TENANT || 'default');

    function uid() {
        return 'dv_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 9);
    }

    function defaultPrograms() {
        var today = new Date();
        var y = today.getFullYear();
        var iso = function (m, d) {
            return y + '-' + String(m).padStart(2, '0') + '-' + String(d).padStart(2, '0');
        };
        return [
            {
                id: uid(),
                name: 'Senior Citizen Discount',
                discountType: 'percentage',
                value: 20,
                reqUploadProof: true,
                reqNotes: false,
                enabled: true,
                validFrom: iso(1, 1),
                validTo: iso(12, 31),
                serviceScope: 'all',
                serviceIds: [],
                stacking: 'no'
            },
            {
                id: uid(),
                name: 'PWD Relief Rate',
                discountType: 'percentage',
                value: 20,
                reqUploadProof: true,
                reqNotes: true,
                enabled: true,
                validFrom: iso(1, 1),
                validTo: iso(12, 31),
                serviceScope: 'all',
                serviceIds: [],
                stacking: 'no'
            },
            {
                id: uid(),
                name: 'Summer Smile Promo',
                discountType: 'fixed',
                value: 500,
                reqUploadProof: false,
                reqNotes: true,
                enabled: false,
                validFrom: iso(4, 1),
                validTo: iso(5, 31),
                serviceScope: 'selected',
                serviceIds: [],
                stacking: 'yes'
            }
        ];
    }

    function snapshotRequirements(prog) {
        if (!prog) return { reqUploadProof: false, reqNotes: false };
        return {
            reqUploadProof: !!prog.reqUploadProof,
            reqNotes: !!prog.reqNotes
        };
    }

    function defaultRecords(programs) {
        var pA = programs[0];
        var pB = programs[1] || programs[0];
        var snapA = snapshotRequirements(pA);
        var snapB = snapshotRequirements(pB);
        return [
            {
                id: uid(),
                patientName: 'Ana L. Reyes',
                patientRef: 'PT-10492',
                programId: pA.id,
                programName: pA.name,
                reqUploadProof: snapA.reqUploadProof,
                reqNotes: snapA.reqNotes,
                applicationNotes: '',
                idNumber: 'SC-FR-882934',
                proofDataUrl: '',
                dateApplied: new Date().toISOString().slice(0, 10),
                staffAssigned: STAFF_NAME,
                status: 'pending',
                approvedBy: '',
                remarks: '',
                updatedAt: new Date().toISOString()
            },
            {
                id: uid(),
                patientName: 'Miguel Santos',
                patientRef: 'PT-8812',
                programId: pB.id,
                programName: pB.name,
                reqUploadProof: snapB.reqUploadProof,
                reqNotes: snapB.reqNotes,
                applicationNotes: 'PWD ID presented at front desk.',
                idNumber: 'PWD-PH-112299',
                proofDataUrl: '',
                dateApplied: new Date(Date.now() - 86400000 * 4).toISOString().slice(0, 10),
                staffAssigned: 'R. Cruz',
                status: 'approved',
                approvedBy: 'R. Cruz',
                remarks: 'ID verified in person.',
                updatedAt: new Date().toISOString()
            },
            {
                id: uid(),
                patientName: 'Lourdes Guevara',
                patientRef: '',
                programId: pA.id,
                programName: pA.name,
                reqUploadProof: snapA.reqUploadProof,
                reqNotes: snapA.reqNotes,
                applicationNotes: '',
                idNumber: 'SC-NCR-009921',
                proofDataUrl: '',
                dateApplied: new Date(Date.now() - 86400000 * 10).toISOString().slice(0, 10),
                staffAssigned: STAFF_NAME,
                status: 'rejected',
                approvedBy: STAFF_NAME,
                remarks: 'Blurred ID photo — ask patient to resubmit.',
                updatedAt: new Date().toISOString()
            }
        ];
    }

    function loadJson(key, fallback) {
        try {
            var raw = localStorage.getItem(key);
            if (raw) {
                var v = JSON.parse(raw);
                if (v) return v;
            }
        } catch (e) {}
        return fallback;
    }

    function saveJson(key, val) {
        try {
            localStorage.setItem(key, JSON.stringify(val));
        } catch (e) {}
    }

    function normalizeProgram(raw) {
        var p = raw;
        if (typeof p.reqUploadProof === 'boolean' && typeof p.reqNotes === 'boolean') {
            if ('eligibility' in p) delete p.eligibility;
            return p;
        }
        if (p.eligibility === 'promo') {
            p.reqUploadProof = false;
            p.reqNotes = true;
        } else if (p.eligibility === 'pwd' || p.eligibility === 'senior') {
            p.reqUploadProof = true;
            p.reqNotes = false;
        } else {
            p.reqUploadProof = true;
            p.reqNotes = false;
        }
        delete p.eligibility;
        return p;
    }

    function normalizeRecord(raw) {
        var r = raw;
        if (typeof r.reqUploadProof === 'boolean' && typeof r.reqNotes === 'boolean') {
            if ('cardType' in r) delete r.cardType;
            if (r.applicationNotes === undefined) r.applicationNotes = '';
            return r;
        }
        if (r.cardType === 'promo') {
            r.reqUploadProof = false;
            r.reqNotes = true;
        } else {
            r.reqUploadProof = true;
            r.reqNotes = false;
        }
        delete r.cardType;
        if (r.applicationNotes === undefined) r.applicationNotes = '';
        return r;
    }

    function getPrograms() {
        var p = loadJson(LS_PROGRAMS, null);
        if (!p || !p.length) {
            p = defaultPrograms();
            saveJson(LS_PROGRAMS, p);
            return p;
        }
        var migrated = false;
        p = p.map(function (row) {
            var n = Object.assign({}, row);
            if ('eligibility' in n || typeof n.reqUploadProof !== 'boolean' || typeof n.reqNotes !== 'boolean') {
                migrated = true;
            }
            normalizeProgram(n);
            return n;
        });
        if (migrated) persistPrograms(p);
        return p;
    }

    function getRecords() {
        var programs = getPrograms();
        var r = loadJson(LS_RECORDS, null);
        if (!r || !r.length) {
            r = defaultRecords(programs);
            saveJson(LS_RECORDS, r);
            return r;
        }
        var migrated = false;
        r = r.map(function (row) {
            var n = Object.assign({}, row);
            if ('cardType' in n || typeof n.reqUploadProof !== 'boolean' || typeof n.reqNotes !== 'boolean' || n.applicationNotes === undefined) {
                migrated = true;
            }
            normalizeRecord(n);
            return n;
        });
        if (migrated) persistRecords(r);
        return r;
    }

    function persistPrograms(p) { saveJson(LS_PROGRAMS, p); }
    function persistRecords(r) { saveJson(LS_RECORDS, r); }

    function maskId(num) {
        if (!num || String(num).length < 5) return '••••';
        var s = String(num);
        return s.slice(0, 3) + '••••' + s.slice(-2);
    }

    function requirementsSummaryFromFlags(upload, notes) {
        var parts = [];
        if (upload) parts.push('Upload proof');
        if (notes) parts.push('Notes');
        return parts.length ? parts.join(' · ') : 'No requirements set';
    }

    function requirementsSummaryProgram(p) {
        if (!p) return '—';
        return requirementsSummaryFromFlags(!!p.reqUploadProof, !!p.reqNotes);
    }

    function requirementsSummaryRecord(r) {
        if (!r) return '—';
        return requirementsSummaryFromFlags(!!r.reqUploadProof, !!r.reqNotes);
    }

    function effectiveStatus(r) {
        if (r.status === 'pending') {
            var programs = getPrograms();
            var pr = programs.find(function (p) { return p.id === r.programId; });
            if (pr && pr.validTo) {
                var end = new Date(pr.validTo + 'T23:59:59');
                if (new Date() > end) return 'expired';
            }
        }
        return r.status;
    }

    function formatDiscountSummary(prog) {
        if (!prog) return '—';
        if (prog.discountType === 'percentage') return prog.name + ' (' + prog.value + '%)';
        return prog.name + ' (₱' + Number(prog.value).toLocaleString() + ' off)';
    }

    function openOverlay(el) {
        el.classList.remove('hidden');
        el.classList.add('flex');
        el.setAttribute('aria-hidden', 'false');
    }
    function closeOverlay(el) {
        el.classList.add('hidden');
        el.classList.remove('flex');
        el.setAttribute('aria-hidden', 'true');
    }

    var clinicServices = [];

    function fetchServices() {
        fetch(SERVICES_API + '?limit=500', { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var payload = (data && data.data) ? data.data : {};
                var list = Array.isArray(payload.services) ? payload.services : (Array.isArray(data) ? data : []);
                clinicServices = list.map(function (s) {
                    var sid = s.service_id != null ? s.service_id : (s.id != null ? s.id : '');
                    return { id: String(sid), name: s.service_name || s.name || 'Service' };
                });
                renderProgramServiceCheckboxes();
            })
            .catch(function () {
                clinicServices = [
                    { id: '1', name: 'Oral prophylaxis' },
                    { id: '2', name: 'Tooth extraction' },
                    { id: '3', name: 'Composite filling' },
                    { id: '4', name: 'Root canal' },
                    { id: '5', name: 'Zirconia crown' }
                ];
                renderProgramServiceCheckboxes();
            });
    }

    function renderProgramServiceCheckboxes() {
        var box = document.getElementById('programServicesList');
        if (!box) return;
        box.innerHTML = '';
        clinicServices.forEach(function (s) {
            var lab = document.createElement('label');
            lab.className = 'flex items-center gap-2 text-sm text-slate-700';
            lab.innerHTML = '<input type="checkbox" class="svc-cb rounded border-slate-300 text-primary focus:ring-primary" data-id="' + s.id.replace(/"/g, '&quot;') + '"/> <span>' + s.name.replace(/</g, '&lt;') + '</span>';
            box.appendChild(lab);
        });
    }

    function setProgramServiceChecks(ids) {
        var set = {};
        (ids || []).forEach(function (id) { set[id] = true; });
        document.querySelectorAll('#programServicesList .svc-cb').forEach(function (cb) {
            cb.checked = !!set[cb.getAttribute('data-id')];
        });
    }

    function getCheckedServiceIds() {
        var ids = [];
        document.querySelectorAll('#programServicesList .svc-cb:checked').forEach(function (cb) {
            ids.push(cb.getAttribute('data-id'));
        });
        return ids;
    }

    function renderPrograms() {
        var grid = document.getElementById('programsGrid');
        var programs = getPrograms();
        if (!programs.length) {
            grid.innerHTML = '<p class="text-slate-500 col-span-full py-8 text-center text-sm">No programs yet. Click “New discount program”.</p>';
            return;
        }
        grid.innerHTML = programs.map(function (p) {
            var statusCls = p.enabled ? 'bg-emerald-50 text-emerald-800 border-emerald-100' : 'bg-slate-100 text-slate-600 border-slate-200';
            var stackTxt = p.stacking === 'yes' ? 'May stack' : 'No stacking';
            var scopeTxt = p.serviceScope === 'all' ? 'All services' : 'Selected procedures';
            return (
                '<article class="elevated-card rounded-2xl p-6 flex flex-col gap-4 border border-slate-100">' +
                '<div class="flex items-start justify-between gap-3">' +
                '<div class="min-w-0">' +
                '<h4 class="font-headline font-bold text-lg text-slate-900 leading-tight">' + p.name.replace(/</g, '&lt;') + '</h4>' +
                '<p class="text-xs font-bold text-primary mt-1 uppercase tracking-wide">' + requirementsSummaryProgram(p).replace(/</g, '&lt;') + '</p>' +
                '</div>' +
                '<span class="shrink-0 text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-lg border ' + statusCls + '">' + (p.enabled ? 'Enabled' : 'Disabled') + '</span>' +
                '</div>' +
                '<p class="text-sm font-semibold text-slate-700">' + formatDiscountSummary(p).replace(/</g, '&lt;') + '</p>' +
                '<p class="text-xs text-slate-500">' + (p.validFrom || '—') + ' → ' + (p.validTo || '—') + '</p>' +
                '<div class="flex flex-wrap gap-2 text-[11px] font-bold text-slate-600">' +
                '<span class="px-2 py-1 rounded-md bg-slate-50 border border-slate-100">' + scopeTxt + '</span>' +
                '<span class="px-2 py-1 rounded-md bg-slate-50 border border-slate-100">' + stackTxt + '</span>' +
                '</div>' +
                '<div class="flex items-center justify-between pt-2 mt-auto border-t border-slate-100">' +
                '<label class="relative inline-flex cursor-pointer items-center">' +
                '<input type="checkbox" class="toggle-quick sr-only peer" data-id="' + p.id + '" ' + (p.enabled ? 'checked' : '') + '/>' +
                '<span class="h-7 w-12 rounded-full bg-slate-300 transition-colors peer-checked:bg-primary peer-focus:ring-2 peer-focus:ring-primary/30 relative">' +
                '<span class="absolute top-0.5 left-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform peer-checked:translate-x-5"></span>' +
                '</span>' +
                '<span class="ml-2 text-xs font-bold text-slate-600">Quick enable</span>' +
                '</label>' +
                '<div class="flex gap-2">' +
                '<button type="button" class="edit-program px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-50" data-id="' + p.id + '">Edit</button>' +
                '</div>' +
                '</div>' +
                '</article>'
            );
        }).join('');

        grid.querySelectorAll('.toggle-quick').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var id = cb.getAttribute('data-id');
                var pr = getPrograms();
                var prog = pr.find(function (x) { return x.id === id; });
                if (prog) {
                    prog.enabled = cb.checked;
                    persistPrograms(pr);
                    renderPrograms();
                    populateApplicationPrograms();
                }
            });
        });
        grid.querySelectorAll('.edit-program').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openProgramModal(btn.getAttribute('data-id'));
            });
        });
    }

    function updateAppFormForProgram() {
        var sel = document.getElementById('appDiscountProgram');
        var hint = document.getElementById('appReqHint');
        var proofWrap = document.getElementById('appProofWrap');
        var notesWrap = document.getElementById('appNotesWrap');
        var proofTag = document.getElementById('appProofRequiredTag');
        var proofInput = document.getElementById('appProofFile');
        var notesTa = document.getElementById('appNotes');
        if (!sel || !hint || !proofWrap || !notesWrap || !proofInput || !notesTa) return;
        var pid = sel.value;
        var programs = getPrograms();
        var prog = programs.find(function (x) { return x.id === pid; });
        if (!prog) {
            hint.classList.add('hidden');
            return;
        }
        hint.textContent = 'Requires: ' + requirementsSummaryProgram(prog) + '.';
        hint.classList.remove('hidden');
        proofWrap.classList.toggle('hidden', !prog.reqUploadProof);
        notesWrap.classList.toggle('hidden', !prog.reqNotes);
        if (proofTag) proofTag.classList.toggle('hidden', !prog.reqUploadProof);
        if (prog.reqNotes) notesTa.setAttribute('required', 'required'); else notesTa.removeAttribute('required');
        if (prog.reqUploadProof) proofInput.setAttribute('required', 'required'); else proofInput.removeAttribute('required');
    }

    function populateApplicationPrograms() {
        var sel = document.getElementById('appDiscountProgram');
        if (!sel) return;
        var programs = getPrograms().filter(function (p) { return p.enabled; });
        sel.innerHTML = programs.map(function (p) {
            return '<option value="' + p.id + '">' + p.name.replace(/</g, '&lt;') + '</option>';
        }).join('');
        if (!programs.length) {
            sel.innerHTML = '<option value="">No enabled programs — add one first</option>';
        }
        updateAppFormForProgram();
    }

    function updateProgramValueLabel() {
        var t = document.getElementById('programDiscountType');
        var lbl = document.getElementById('programValueLabel');
        if (!t || !lbl) return;
        lbl.textContent = t.value === 'percentage' ? 'Percentage (%)' : 'Fixed amount (₱)';
    }

    function openProgramModal(editId) {
        var programs = getPrograms();
        var form = document.getElementById('programForm');
        document.getElementById('programModalTitle').textContent = editId ? 'Edit discount program' : 'New discount program';
        document.getElementById('programId').value = editId || '';
        if (editId) {
            var p = programs.find(function (x) { return x.id === editId; });
            if (!p) return;
            document.getElementById('programName').value = p.name;
            document.getElementById('programDiscountType').value = p.discountType;
            document.getElementById('programValue').value = p.value;
            document.getElementById('reqUploadProof').checked = !!p.reqUploadProof;
            document.getElementById('reqNotes').checked = !!p.reqNotes;
            document.getElementById('programEnabled').checked = !!p.enabled;
            document.getElementById('programStart').value = p.validFrom || '';
            document.getElementById('programEnd').value = p.validTo || '';
            document.getElementById('programStacking').value = p.stacking || 'no';
            document.querySelector('input[name="programScope"][value="' + (p.serviceScope || 'all') + '"]').checked = true;
            setProgramServiceChecks(p.serviceIds || []);
        } else {
            form.reset();
            document.getElementById('programId').value = '';
            document.getElementById('programEnabled').checked = true;
            document.getElementById('reqUploadProof').checked = true;
            document.getElementById('reqNotes').checked = false;
            document.querySelector('input[name="programScope"][value="all"]').checked = true;
            setProgramServiceChecks([]);
        }
        updateProgramValueLabel();
        document.getElementById('programServicesWrap').classList.toggle('hidden', document.querySelector('input[name="programScope"]:checked').value !== 'selected');
        document.getElementById('programEnabledLabel').textContent = document.getElementById('programEnabled').checked ? 'Enabled' : 'Disabled';
        openOverlay(document.getElementById('programModal'));
    }

    document.querySelectorAll('.program-modal-close').forEach(function (b) {
        b.addEventListener('click', function () { closeOverlay(document.getElementById('programModal')); });
    });
    document.getElementById('btnNewProgram').addEventListener('click', function () { openProgramModal(null); });

    document.getElementById('programDiscountType').addEventListener('change', updateProgramValueLabel);
    document.getElementById('programEnabled').addEventListener('change', function () {
        document.getElementById('programEnabledLabel').textContent = document.getElementById('programEnabled').checked ? 'Enabled' : 'Disabled';
    });
    document.querySelectorAll('input[name="programScope"]').forEach(function (r) {
        r.addEventListener('change', function () {
            var sel = document.querySelector('input[name="programScope"]:checked').value === 'selected';
            document.getElementById('programServicesWrap').classList.toggle('hidden', !sel);
        });
    });

    document.getElementById('programForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var programs = getPrograms();
        var id = document.getElementById('programId').value || uid();
        var scope = document.querySelector('input[name="programScope"]:checked').value;
        var row = {
            id: id,
            name: document.getElementById('programName').value.trim(),
            discountType: document.getElementById('programDiscountType').value,
            value: parseFloat(document.getElementById('programValue').value) || 0,
            reqUploadProof: document.getElementById('reqUploadProof').checked,
            reqNotes: document.getElementById('reqNotes').checked,
            enabled: document.getElementById('programEnabled').checked,
            validFrom: document.getElementById('programStart').value,
            validTo: document.getElementById('programEnd').value,
            serviceScope: scope,
            serviceIds: scope === 'selected' ? getCheckedServiceIds() : [],
            stacking: document.getElementById('programStacking').value
        };
        var idx = programs.findIndex(function (x) { return x.id === id; });
        if (idx >= 0) programs[idx] = row;
        else programs.push(row);
        persistPrograms(programs);
        closeOverlay(document.getElementById('programModal'));
        renderPrograms();
        populateApplicationPrograms();
        renderHistory();
    });

    document.querySelectorAll('.application-modal-close').forEach(function (b) {
        b.addEventListener('click', function () { closeOverlay(document.getElementById('applicationModal')); });
    });
    document.getElementById('appDiscountProgram').addEventListener('change', updateAppFormForProgram);

    document.getElementById('btnNewApplication').addEventListener('click', function () {
        document.getElementById('applicationForm').reset();
        document.getElementById('appStaffAssigned').value = STAFF_NAME;
        document.getElementById('appDateApplied').value = new Date().toISOString().slice(0, 10);
        document.getElementById('appProofPreview').classList.add('hidden');
        var imgEl = document.getElementById('appProofImg');
        if (imgEl) imgEl.removeAttribute('src');
        populateApplicationPrograms();
        openOverlay(document.getElementById('applicationModal'));
    });

    document.getElementById('appProofFile').addEventListener('change', function (ev) {
        var f = ev.target.files && ev.target.files[0];
        var prev = document.getElementById('appProofPreview');
        var img = document.getElementById('appProofImg');
        if (!f || !f.type.match(/^image\//)) {
            prev.classList.add('hidden');
            return;
        }
        var r = new FileReader();
        r.onload = function () {
            img.src = r.result;
            prev.classList.remove('hidden');
        };
        r.readAsDataURL(f);
    });

    document.getElementById('applicationForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var pid = document.getElementById('appDiscountProgram').value;
        if (!pid) {
            alert('Enable at least one discount program first.');
            return;
        }
        var programs = getPrograms();
        var prog = programs.find(function (x) { return x.id === pid; });
        if (!prog) return;
        var snap = snapshotRequirements(prog);
        var proof = '';
        var imgEl = document.getElementById('appProofImg');
        if (imgEl && imgEl.src && imgEl.src.indexOf('data:') === 0) proof = imgEl.src;
        var notesVal = document.getElementById('appNotes').value.trim();
        if (snap.reqUploadProof && !proof) {
            alert('This discount program requires an uploaded proof image.');
            return;
        }
        if (snap.reqNotes && !notesVal) {
            alert('This discount program requires notes.');
            return;
        }
        var records = getRecords();
        records.push({
            id: uid(),
            patientName: document.getElementById('appPatientName').value.trim(),
            patientRef: document.getElementById('appPatientRef').value.trim(),
            programId: pid,
            programName: prog.name,
            reqUploadProof: snap.reqUploadProof,
            reqNotes: snap.reqNotes,
            applicationNotes: notesVal,
            idNumber: document.getElementById('appIdNumber').value.trim(),
            proofDataUrl: proof,
            dateApplied: document.getElementById('appDateApplied').value,
            staffAssigned: document.getElementById('appStaffAssigned').value,
            status: 'pending',
            approvedBy: '',
            remarks: '',
            updatedAt: new Date().toISOString()
        });
        persistRecords(records);
        closeOverlay(document.getElementById('applicationModal'));
        renderHistory();
    });

    function renderHistory() {
        var records = getRecords();
        var fReq = document.getElementById('filterRequirements').value;
        var fStat = document.getElementById('filterStatus').value;
        var fFrom = document.getElementById('filterDateFrom').value;
        var fTo = document.getElementById('filterDateTo').value;
        var fStaff = document.getElementById('filterStaff').value;
        var fPatient = document.getElementById('filterPatient').value.trim().toLowerCase();

        var staffSet = {};
        records.forEach(function (r) {
            if (r.approvedBy) staffSet[r.approvedBy] = true;
        });
        var staffSel = document.getElementById('filterStaff');
        var keep = staffSel.value;
        staffSel.innerHTML = '<option value="all">Any staff</option>' +
            Object.keys(staffSet).sort().map(function (s) {
                return '<option value="' + s.replace(/"/g, '&quot;') + '">' + s.replace(/</g, '&lt;') + '</option>';
            }).join('');
        if (keep && Array.prototype.some.call(staffSel.options, function (o) { return o.value === keep; })) {
            staffSel.value = keep;
        }

        var filtered = records.filter(function (r) {
            var eff = effectiveStatus(r);
            if (fReq === 'proof' && !r.reqUploadProof) return false;
            if (fReq === 'notes' && !r.reqNotes) return false;
            if (fReq === 'both' && (!r.reqUploadProof || !r.reqNotes)) return false;
            if (fStat !== 'all' && eff !== fStat) return false;
            if (fPatient && (r.patientName || '').toLowerCase().indexOf(fPatient) === -1) return false;
            if (fStaff !== 'all' && (eff !== 'approved' || r.approvedBy !== fStaff)) return false;
            var d = r.dateApplied || '';
            if (fFrom && d < fFrom) return false;
            if (fTo && d > fTo) return false;
            return true;
        });

        filtered.sort(function (a, b) {
            return String(b.dateApplied).localeCompare(String(a.dateApplied));
        });

        var pendingN = records.filter(function (r) { return effectiveStatus(r) === 'pending'; }).length;
        document.getElementById('pendingCount').textContent = String(pendingN);

        var tbody = document.getElementById('historyTableBody');
        if (!filtered.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-10 text-center text-slate-500">No records match filters.</td></tr>';
        } else {
            tbody.innerHTML = filtered.map(function (r) {
                var eff = effectiveStatus(r);
                var badgeCls = 'bg-slate-100 text-slate-700';
                if (eff === 'pending') badgeCls = 'bg-amber-50 text-amber-800 border border-amber-100';
                if (eff === 'approved') badgeCls = 'bg-emerald-50 text-emerald-800 border border-emerald-100';
                if (eff === 'rejected') badgeCls = 'bg-red-50 text-red-800 border border-red-100';
                if (eff === 'expired') badgeCls = 'bg-slate-200 text-slate-700 border border-slate-300';
                var actions = '';
                if (eff === 'pending') {
                    actions = '<button type="button" class="verify-open text-primary font-bold text-xs uppercase tracking-wide hover:underline mr-2" data-id="' + r.id + '">Verify</button>';
                }
                actions += '<button type="button" class="view-open text-slate-600 font-bold text-xs uppercase tracking-wide hover:underline" data-id="' + r.id + '">View</button>';
                return '<tr class="hover:bg-slate-50/80">' +
                    '<td class="px-6 py-4 text-sm font-semibold text-slate-800 whitespace-nowrap">' + (r.dateApplied || '—') + '</td>' +
                    '<td class="px-6 py-4 text-sm font-bold text-slate-900">' + (r.patientName || '').replace(/</g, '&lt;') +
                    (r.patientRef ? '<span class="block text-xs font-medium text-slate-500">' + r.patientRef.replace(/</g, '&lt;') + '</span>' : '') + '</td>' +
                    '<td class="px-6 py-4 text-sm text-slate-700">' + (r.programName || '').replace(/</g, '&lt;') + '</td>' +
                    '<td class="px-6 py-4 text-sm font-mono text-slate-600">' + maskId(r.idNumber) + '</td>' +
                    '<td class="px-6 py-4"><span class="text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-lg ' + badgeCls + '">' + eff + '</span></td>' +
                    '<td class="px-6 py-4 text-sm text-slate-700">' + (eff === 'approved' ? (r.approvedBy || '—').replace(/</g, '&lt;') : '—') + '</td>' +
                    '<td class="px-6 py-4 text-sm text-slate-600 max-w-[200px] truncate" title="' + String(r.remarks || '').replace(/"/g, '&quot;') + '">' + (r.remarks ? r.remarks.replace(/</g, '&lt;') : '—') + '</td>' +
                    '<td class="px-6 py-4 text-right whitespace-nowrap">' + actions + '</td>' +
                    '</tr>';
            }).join('');
        }

        document.getElementById('historySummary').textContent = 'Showing ' + filtered.length + ' of ' + records.length + ' records';

        tbody.querySelectorAll('.verify-open').forEach(function (b) {
            b.addEventListener('click', function () { openVerifyModal(b.getAttribute('data-id'), true); });
        });
        tbody.querySelectorAll('.view-open').forEach(function (b) {
            b.addEventListener('click', function () { openVerifyModal(b.getAttribute('data-id'), false); });
        });
    }

    function openVerifyModal(recId, allowActions) {
        var r = getRecords().find(function (x) { return x.id === recId; });
        if (!r) return;
        var eff = effectiveStatus(r);
        document.getElementById('verifyRecordId').value = r.id;
        document.getElementById('verifyPatientLine').textContent = (r.patientName || '—') + (r.patientRef ? ' · ' + r.patientRef : '');
        document.getElementById('verifyDiscountLine').textContent = (r.programName || '—') + ' · ' + requirementsSummaryRecord(r);
        var pnw = document.getElementById('verifyPatientNotesWrap');
        var pnt = document.getElementById('verifyPatientNotes');
        if (r.applicationNotes && String(r.applicationNotes).trim() !== '') {
            pnw.classList.remove('hidden');
            pnt.textContent = r.applicationNotes;
        } else {
            pnw.classList.add('hidden');
        }
        document.getElementById('verifyIdFull').textContent = r.idNumber || '—';
        document.getElementById('verifyMetaLine').textContent = (r.dateApplied || '—') + ' · Assigned: ' + (r.staffAssigned || '—');
        var box = document.getElementById('verifyProofBox');
        box.innerHTML = '';
        if (r.proofDataUrl) {
            var im = document.createElement('img');
            im.src = r.proofDataUrl;
            im.alt = 'ID proof';
            im.className = 'max-h-64 w-auto mx-auto object-contain';
            box.appendChild(im);
        } else {
            box.innerHTML = '<div class="text-center p-6"><span class="material-symbols-outlined text-4xl text-slate-300">image</span><p class="text-sm text-slate-500 mt-2">No image on file</p></div>';
        }
        var readonlyNote = document.getElementById('verifyReadonlyNote');
        var remarksRead = document.getElementById('verifyRemarksRead');
        if (r.remarks) {
            readonlyNote.classList.remove('hidden');
            remarksRead.textContent = r.remarks;
        } else {
            readonlyNote.classList.add('hidden');
        }
        var actionBlock = document.getElementById('verifyActionBlock');
        var canAct = allowActions && eff === 'pending';
        actionBlock.style.display = canAct ? 'block' : 'none';
        document.getElementById('verifyRemarksInput').value = '';
        document.getElementById('verifyModalTitle').textContent = canAct ? 'Verify application' : 'Application details';
        openOverlay(document.getElementById('verifyModal'));
    }

    document.getElementById('verifyModalClose').addEventListener('click', function () {
        closeOverlay(document.getElementById('verifyModal'));
    });

    function patchRecord(id, patch) {
        var records = getRecords();
        var idx = records.findIndex(function (x) { return x.id === id; });
        if (idx < 0) return;
        Object.assign(records[idx], patch);
        records[idx].updatedAt = new Date().toISOString();
        persistRecords(records);
        renderHistory();
        closeOverlay(document.getElementById('verifyModal'));
    }

    document.getElementById('btnApprove').addEventListener('click', function () {
        var id = document.getElementById('verifyRecordId').value;
        var note = document.getElementById('verifyRemarksInput').value.trim();
        patchRecord(id, { status: 'approved', approvedBy: STAFF_NAME, remarks: note || 'Approved.' });
    });
    document.getElementById('btnReject').addEventListener('click', function () {
        var id = document.getElementById('verifyRecordId').value;
        var note = document.getElementById('verifyRemarksInput').value.trim();
        if (!note) {
            alert('Please add a short remark when rejecting (e.g. reason).');
            return;
        }
        patchRecord(id, { status: 'rejected', approvedBy: STAFF_NAME, remarks: note });
    });
    document.getElementById('btnRequestInfo').addEventListener('click', function () {
        var id = document.getElementById('verifyRecordId').value;
        var note = document.getElementById('verifyRemarksInput').value.trim();
        patchRecord(id, { status: 'pending', approvedBy: '', remarks: note || 'Additional information requested.' });
    });

    ['filterRequirements', 'filterStatus', 'filterDateFrom', 'filterDateTo', 'filterStaff', 'filterPatient'].forEach(function (fid) {
        var el = document.getElementById(fid);
        if (el) el.addEventListener('input', renderHistory);
        if (el) el.addEventListener('change', renderHistory);
    });

    fetchServices();
    renderPrograms();
    populateApplicationPrograms();
    renderHistory();
})();
</script>
</body>
</html>
