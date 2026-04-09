<?php
$pageTitle = 'Walk-In Booking';
$staff_nav_active = 'appointments';
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_GET['clinic_slug']) && !empty($_SESSION['public_tenant_slug'])) {
    $_GET['clinic_slug'] = $_SESSION['public_tenant_slug'];
}

if (empty($_GET['clinic_slug'])) {
    $reqUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $reqPath = $reqUri !== '' ? parse_url($reqUri, PHP_URL_PATH) : '';
    $scriptBase = isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : 'StaffWalkIn.php';
    if (is_string($reqPath) && $reqPath !== '') {
        $segments = array_values(array_filter(explode('/', trim($reqPath, '/')), 'strlen'));
        $scriptIdx = array_search($scriptBase, $segments, true);
        if ($scriptIdx !== false && $scriptIdx > 0) {
            $slugFromPath = strtolower(trim((string) $segments[$scriptIdx - 1]));
            if ($slugFromPath !== '' && preg_match('/^[a-z0-9\-]+$/', $slugFromPath)) {
                $_GET['clinic_slug'] = $slugFromPath;
            }
        }
    }
}

$clinicSlugBoot = isset($_GET['clinic_slug']) ? trim((string) $_GET['clinic_slug']) : '';
if ($clinicSlugBoot !== '' && preg_match('/^[a-z0-9\-]+$/', strtolower($clinicSlugBoot))) {
    $_GET['clinic_slug'] = strtolower($clinicSlugBoot);
    require_once __DIR__ . '/tenant_bootstrap.php';
    if (!isset($currentTenantSlug) || trim((string) $currentTenantSlug) === '') {
        $currentTenantSlug = strtolower($clinicSlugBoot);
    }
} else {
    $currentTenantSlug = '';
}

$selectedDateValue = date('Y-m-d');
$selectedDateDisplay = date('m/d/Y');
$selectedTimeDisplay = date('g:i:s A');

$baseParams = [];
if ($currentTenantSlug !== '') {
    $baseParams['clinic_slug'] = $currentTenantSlug;
}
$backToAppointmentsHref = BASE_URL . 'StaffAppointments.php' . ($baseParams ? ('?' . http_build_query($baseParams)) : '');
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Walk-In Booking | Clinical Precision</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
                        "on-surface-variant": "#404752",
                        "walkin-accent": "#22c7b8",
                        "walkin-accent-strong": "#0ea5a1"
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
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(14, 165, 161, 0.04) 0px, transparent 50%);
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
        .walkin-input {
            border: none;
            background: #f8fafc;
            border-radius: 0.9rem;
            font-size: 0.86rem;
            font-weight: 700;
            color: #0f172a;
            transition: box-shadow 0.25s ease, background-color 0.25s ease;
        }
        .walkin-input:focus {
            outline: none;
            background: #f1f5f9;
            box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.18);
        }
        .walkin-primary-btn {
            transition: transform 0.25s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.25s ease, filter 0.25s ease;
        }
        .walkin-primary-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 35px -20px rgba(16, 185, 129, 0.9);
            filter: brightness(1.02);
        }
        .walkin-primary-btn:active {
            transform: translateY(0) scale(0.99);
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
                <span class="w-12 h-[1.5px] bg-primary"></span> APPOINTMENT MANAGEMENT
            </div>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Create <span class="font-editorial italic font-normal text-walkin-accent-strong transform -skew-x-6 inline-block">Walk-In Booking</span>
                    </h1>
                    <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                        Register and schedule a same-day patient appointment with quick service and payment preview.
                    </p>
                </div>
                <a
                    href="<?php echo htmlspecialchars($backToAppointmentsHref, ENT_QUOTES, 'UTF-8'); ?>"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-slate-700 transition-colors"
                >
                    <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                    Back to Appointments
                </a>
            </div>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-12 gap-6">
            <div class="xl:col-span-4 elevated-card rounded-3xl p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-xl bg-walkin-accent/15 text-walkin-accent-strong flex items-center justify-center">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person_search</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Patient Selection</p>
                        <h2 class="text-lg font-extrabold text-slate-900">Select Patient</h2>
                    </div>
                </div>
                <div class="space-y-3">
                    <label class="block">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Select Patient</span>
                        <select class="walkin-input w-full py-3 px-4">
                            <option value="">Choose patient</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Search Patient</span>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[18px]">search</span>
                            <input type="text" class="walkin-input w-full py-3 pl-10 pr-4" placeholder="Search patient"/>
                        </div>
                    </label>
                    <button type="button" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-walkin-accent to-walkin-accent-strong text-white py-3 text-sm font-bold shadow-lg shadow-walkin-accent/30 walkin-primary-btn">
                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">person_add</span>
                        Register Patient
                    </button>
                    <p class="text-xs font-semibold text-slate-500 leading-relaxed">
                        Tip: You can also register a new patient from the patients menu if they are not in the list.
                    </p>
                </div>
            </div>

            <div class="xl:col-span-8 space-y-6">
                <div class="elevated-card rounded-3xl p-6">
                    <div class="flex items-center gap-3 mb-5">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_note</span>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Appointment Details</p>
                            <h2 class="text-lg font-extrabold text-slate-900">Booking Information</h2>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Assigned Dentist</span>
                            <select class="walkin-input w-full py-3 px-4">
                                <option value="">Select dentist</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Service / Treatment</span>
                            <div class="flex items-center gap-2">
                                <select class="walkin-input w-full py-3 px-4">
                                    <option value="">Select service</option>
                                </select>
                                <button type="button" class="w-11 h-11 rounded-xl bg-walkin-accent text-white inline-flex items-center justify-center hover:bg-walkin-accent-strong transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">add</span>
                                </button>
                            </div>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Appointment Date</span>
                            <input id="walkInDateInput" type="text" class="walkin-input w-full py-3 px-4" value="<?php echo htmlspecialchars($selectedDateDisplay, ENT_QUOTES, 'UTF-8'); ?>" readonly/>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Appointment Time</span>
                            <input id="walkInTimeInput" type="text" class="walkin-input w-full py-3 px-4" value="<?php echo htmlspecialchars($selectedTimeDisplay, ENT_QUOTES, 'UTF-8'); ?>" readonly/>
                        </label>
                        <label class="block md:col-span-2">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Notes (Optional)</span>
                            <textarea rows="4" class="walkin-input w-full py-3 px-4 resize-y" placeholder="Additional notes or special instructions for this appointment."></textarea>
                        </label>
                    </div>

                    <div class="mt-4 rounded-2xl border border-primary/15 bg-primary/5 px-4 py-3">
                        <p class="text-xs font-bold text-primary flex items-center gap-2">
                            <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">info</span>
                            Walk-In Appointment
                        </p>
                        <p class="text-[11px] font-semibold text-slate-600 mt-1">
                            Date and time are synchronized to the current clinic server time and update every second.
                        </p>
                    </div>
                </div>

                <div class="elevated-card rounded-3xl p-6">
                    <div class="flex items-center justify-between gap-3 mb-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Payment Details</p>
                            <h3 class="text-lg font-extrabold text-slate-900">Cost Preview</h3>
                        </div>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-slate-500">
                            <span class="material-symbols-outlined text-[16px]">payments</span>
                            Installment Available: No
                        </span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-left">
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Total Amount</p>
                            <p id="walkInTotalAmount" class="mt-2 text-2xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Down Payment (Min)</p>
                            <p class="mt-2 text-xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Monthly (Est.)</p>
                            <p class="mt-2 text-xl font-extrabold text-slate-900">P0.00</p>
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-4">
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">Duration (Max)</p>
                            <p class="mt-2 text-xl font-extrabold text-slate-900">0 Months</p>
                        </div>
                    </div>
                    <p class="text-[11px] font-semibold text-slate-500 mt-4">Actual payment terms will be finalized during payment processing.</p>
                </div>
            </div>
        </section>

        <section class="pt-1">
            <button type="button" class="walkin-primary-btn w-full rounded-2xl bg-gradient-to-r from-walkin-accent to-walkin-accent-strong text-white py-3.5 text-sm font-extrabold uppercase tracking-wide shadow-lg shadow-walkin-accent/35 inline-flex items-center justify-center gap-2">
                <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">calendar_add_on</span>
                Create Walk-In Appointment
            </button>
        </section>
    </div>
</main>

<script>
    (function () {
        const dateInput = document.getElementById('walkInDateInput');
        const timeInput = document.getElementById('walkInTimeInput');

        function pad(number) {
            return String(number).padStart(2, '0');
        }

        function formatDate(date) {
            return pad(date.getMonth() + 1) + '/' + pad(date.getDate()) + '/' + date.getFullYear();
        }

        function formatTime(date) {
            let hours = date.getHours();
            const minutes = pad(date.getMinutes());
            const seconds = pad(date.getSeconds());
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            return hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        }

        function updateNow() {
            const now = new Date();
            if (dateInput) dateInput.value = formatDate(now);
            if (timeInput) timeInput.value = formatTime(now);
        }

        updateNow();
        setInterval(updateNow, 1000);
    })();
</script>
</body>
</html>
