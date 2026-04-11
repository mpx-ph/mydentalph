<?php
$pageTitle = 'Walk-In Booking';
$staff_nav_active = 'appointments';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/tenant.php';

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

$walkInDentists = [];
try {
    if (function_exists('getDBConnection')) {
        $pdo = getDBConnection();
        $tenantId = null;
        if (function_exists('getClinicTenantId')) {
            $tenantId = getClinicTenantId();
        }
        if (empty($tenantId) && isset($currentTenantId) && $currentTenantId !== '') {
            $tenantId = (string) $currentTenantId;
        }
        if (empty($tenantId) && !empty($_SESSION['tenant_id'])) {
            $tenantId = (string) $_SESSION['tenant_id'];
        }
        if (empty($tenantId) && !empty($_SESSION['public_tenant_id'])) {
            $tenantId = (string) $_SESSION['public_tenant_id'];
        }
        if ($pdo && $tenantId) {
            $stmt = $pdo->prepare("
                SELECT
                    d.dentist_id,
                    COALESCE(d.first_name, '') AS first_name,
                    COALESCE(d.last_name, '') AS last_name,
                    '' AS profile_image,
                    COALESCE(d.status, 'active') AS status
                FROM tbl_dentists d
                WHERE d.tenant_id = ?
                ORDER BY d.first_name ASC, d.last_name ASC
            ");
            $stmt->execute([$tenantId]);
            $walkInDentists = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }
} catch (Throwable $e) {
    $walkInDentists = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'create_walkin') {
    header('Content-Type: application/json');
    try {
        if (empty($tenantId)) {
            if (function_exists('getClinicTenantId')) {
                $tenantId = getClinicTenantId();
            }
        }
        if (empty($tenantId) && isset($currentTenantId) && $currentTenantId !== '') {
            $tenantId = (string) $currentTenantId;
        }
        if (empty($tenantId) && !empty($_SESSION['tenant_id'])) {
            $tenantId = (string) $_SESSION['tenant_id'];
        }
        if (empty($tenantId) && !empty($_SESSION['public_tenant_id'])) {
            $tenantId = (string) $_SESSION['public_tenant_id'];
        }

        if (empty($tenantId)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Tenant context missing. Please log in again.']);
            exit;
        }

        $rawBody = file_get_contents('php://input');
        $input = json_decode((string) $rawBody, true);
        if (!is_array($input)) {
            $input = [];
        }

        $patientId = trim((string) ($input['patient_id'] ?? ''));
        $dentistId = trim((string) ($input['dentist_id'] ?? ''));
        $appointmentDate = trim((string) ($input['appointment_date'] ?? ''));
        $appointmentTime = trim((string) ($input['appointment_time'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        $services = isset($input['services']) && is_array($input['services']) ? $input['services'] : [];
        $status = trim((string) ($input['status'] ?? 'pending'));
        $visitType = 'walk_in';

        if ($patientId === '') {
            echo json_encode(['success' => false, 'message' => 'Patient selection is required.']);
            exit;
        }
        if ($dentistId === '') {
            echo json_encode(['success' => false, 'message' => 'Assigned dentist is required.']);
            exit;
        }
        if ($appointmentDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDate)) {
            echo json_encode(['success' => false, 'message' => 'Invalid appointment date.']);
            exit;
        }
        if ($appointmentTime === '' || !preg_match('/^\d{2}:\d{2}:\d{2}$/', $appointmentTime)) {
            echo json_encode(['success' => false, 'message' => 'Invalid appointment time.']);
            exit;
        }
        if (empty($services)) {
            echo json_encode(['success' => false, 'message' => 'Please select at least one service.']);
            exit;
        }

        $statusAllowed = ['pending', 'confirmed', 'scheduled'];
        if (!in_array(strtolower($status), $statusAllowed, true)) {
            $status = 'pending';
        }

        $pdo = getDBConnection();
        $pdo->beginTransaction();

        $patientCheckStmt = $pdo->prepare("
            SELECT patient_id
            FROM tbl_patients
            WHERE tenant_id = ? AND patient_id = ?
            LIMIT 1
        ");
        $patientCheckStmt->execute([$tenantId, $patientId]);
        if (!$patientCheckStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Selected patient was not found.']);
            exit;
        }

        $dentistCheckStmt = $pdo->prepare("
            SELECT dentist_id
            FROM tbl_dentists
            WHERE tenant_id = ? AND dentist_id = ?
            LIMIT 1
        ");
        $dentistCheckStmt->execute([$tenantId, $dentistId]);
        if (!$dentistCheckStmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Selected dentist was not found.']);
            exit;
        }

        $bookingPrefix = 'BK-' . date('Y') . '-';
        $bookingStmt = $pdo->prepare("
            SELECT booking_id
            FROM tbl_appointments
            WHERE tenant_id = ?
              AND booking_id LIKE ?
            ORDER BY booking_id DESC
            LIMIT 1
        ");
        $bookingStmt->execute([$tenantId, $bookingPrefix . '%']);
        $lastBookingId = (string) ($bookingStmt->fetchColumn() ?: '');
        $sequence = 1;
        if ($lastBookingId !== '') {
            $parts = explode('-', $lastBookingId);
            if (count($parts) === 3) {
                $sequence = ((int) $parts[2]) + 1;
            }
        }
        $bookingId = $bookingPrefix . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);

        $serviceNames = [];
        $serviceDescriptions = [];
        $totalCost = 0.0;
        $normalizedServices = [];

        foreach ($services as $service) {
            $serviceId = trim((string) ($service['id'] ?? $service['service_id'] ?? ''));
            if ($serviceId === '') {
                continue;
            }
            $serviceStmt = $pdo->prepare("
                SELECT service_id, service_name, category, price
                FROM tbl_services
                WHERE tenant_id = ? AND service_id = ? AND status = 'active'
                LIMIT 1
            ");
            $serviceStmt->execute([$tenantId, $serviceId]);
            $serviceRow = $serviceStmt->fetch(PDO::FETCH_ASSOC);
            if (!$serviceRow) {
                continue;
            }
            $price = (float) ($serviceRow['price'] ?? 0);
            $name = trim((string) ($serviceRow['service_name'] ?? ''));
            $normalizedServices[] = [
                'service_id' => (string) ($serviceRow['service_id'] ?? ''),
                'service_name' => $name,
                'price' => $price,
            ];
            if ($name !== '') {
                $serviceNames[] = $name;
                $serviceDescriptions[] = $name . ' (₱' . number_format($price, 2) . ')';
            }
            $totalCost += $price;
        }

        if (empty($normalizedServices)) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'No valid active services were selected.']);
            exit;
        }

        $serviceType = implode(', ', array_slice($serviceNames, 0, 3));
        if (count($serviceNames) > 3) {
            $serviceType .= ' (+' . (count($serviceNames) - 3) . ' more)';
        }
        $serviceDescription = implode('; ', $serviceDescriptions) . ' | Total: ₱' . number_format($totalCost, 2);
        $createdBy = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
        if ($createdBy === '') {
            $createdBy = null;
        }

        $insertAppointmentStmt = $pdo->prepare("
            INSERT INTO tbl_appointments (
                tenant_id,
                dentist_id,
                booking_id,
                patient_id,
                appointment_date,
                appointment_time,
                service_type,
                service_description,
                treatment_type,
                visit_type,
                status,
                notes,
                total_treatment_cost,
                created_by,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'short_term', ?, ?, ?, ?, ?, NOW())
        ");
        $insertAppointmentStmt->execute([
            $tenantId,
            $dentistId,
            $bookingId,
            $patientId,
            $appointmentDate,
            $appointmentTime,
            $serviceType,
            $serviceDescription,
            $visitType,
            $status,
            $notes !== '' ? $notes : null,
            $totalCost,
            $createdBy,
        ]);

        $appointmentId = (int) $pdo->lastInsertId();
        $serviceInsertStmt = $pdo->prepare("
            INSERT INTO tbl_appointment_services (
                tenant_id,
                booking_id,
                appointment_id,
                service_id,
                service_name,
                price,
                is_original,
                added_by,
                added_at
            ) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())
        ");
        foreach ($normalizedServices as $serviceRow) {
            $serviceInsertStmt->execute([
                $tenantId,
                $bookingId,
                $appointmentId > 0 ? $appointmentId : null,
                $serviceRow['service_id'],
                $serviceRow['service_name'],
                $serviceRow['price'],
                $createdBy,
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Walk-in appointment created successfully.',
            'data' => [
                'booking_id' => $bookingId,
                'appointment_id' => $appointmentId,
            ],
        ]);
        exit;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Staff walk-in create error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Unable to create walk-in appointment right now.',
        ]);
        exit;
    }
}
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
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.02) 0px, transparent 50%);
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
            box-shadow: 0 20px 35px -20px rgba(43, 139, 235, 0.9);
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
                        Create <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Walk-In Booking</span>
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
                    <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                        <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">person_search</span>
                    </div>
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Patient Selection</p>
                        <h2 class="text-lg font-extrabold text-slate-900">Select Patient</h2>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="block">
                        <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Select Patient</span>
                        <input id="selectedPatientId" type="hidden" value=""/>
                        <button
                            id="choosePatientBtn"
                            type="button"
                            class="walkin-input w-full py-3 px-4 text-left inline-flex items-center justify-between"
                        >
                            <span id="selectedPatientLabel">Choose patient</span>
                            <span class="material-symbols-outlined text-[18px] text-slate-500">keyboard_arrow_down</span>
                        </button>
                    </div>
                    <button type="button" class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary to-blue-500 text-white py-3 text-sm font-bold shadow-lg shadow-primary/30 walkin-primary-btn">
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
                            <input id="selectedDentistId" type="hidden" value=""/>
                            <button id="chooseDentistBtn" type="button" class="walkin-input w-full py-3 px-4 text-left inline-flex items-center justify-between">
                                <span id="selectedDentistLabel">Select dentist</span>
                                <span class="material-symbols-outlined text-[18px] text-slate-500">keyboard_arrow_down</span>
                            </button>
                        </label>
                        <label class="block">
                            <span class="block text-[10px] font-black uppercase tracking-[0.18em] text-on-surface-variant/70 mb-2">Service / Treatment</span>
                            <div class="flex items-center gap-2">
                                <input id="selectedServiceId" type="hidden" value=""/>
                                <button id="chooseServiceBtn" type="button" class="walkin-input w-full py-3 px-4 text-left inline-flex items-center justify-between">
                                    <span id="selectedServiceLabel">Select service</span>
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">keyboard_arrow_down</span>
                                </button>
                                <button id="addServiceBtn" type="button" class="w-11 h-11 rounded-xl bg-primary text-white inline-flex items-center justify-center hover:bg-primary/90 transition-colors">
                                    <span class="material-symbols-outlined text-[18px]">add</span>
                                </button>
                            </div>
                            <div id="selectedServicesContainer" class="mt-3 flex flex-wrap gap-2"></div>
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
                            <textarea id="walkInNotesInput" rows="4" class="walkin-input w-full py-3 px-4 resize-y" placeholder="Additional notes or special instructions for this appointment."></textarea>
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

        <section class="pt-1 grid grid-cols-1 xl:grid-cols-12">
            <div class="xl:col-span-8 xl:col-start-5">
                <button id="createWalkInAppointmentBtn" type="button" class="walkin-primary-btn w-full rounded-2xl bg-gradient-to-r from-primary to-blue-500 text-white py-3.5 text-sm font-extrabold uppercase tracking-wide shadow-lg shadow-primary/35 inline-flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">calendar_add_on</span>
                    Create Walk-In Appointment
                </button>
            </div>
        </section>
    </div>
</main>

<div id="choosePatientModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/45"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Patient Selection</p>
                    <h3 class="text-lg font-extrabold text-slate-900">Choose Patient</h3>
                </div>
                <button id="closeChoosePatientModalBtn" type="button" class="w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div class="px-5 py-4 border-b border-slate-100">
                <input id="patientSearchInput" type="text" class="walkin-input w-full py-3 px-4" placeholder="Search patient name, ID, or contact number"/>
            </div>

            <div class="max-h-[26rem] overflow-y-auto">
                <div id="patientListEmptyState" class="hidden px-5 py-8 text-center text-sm font-semibold text-slate-500"></div>
                <div id="patientListContainer" class="divide-y divide-slate-100"></div>
            </div>
        </div>
    </div>
</div>

<div id="chooseDentistModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/45"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Dentist Selection</p>
                    <h3 class="text-lg font-extrabold text-slate-900">Choose Dentist</h3>
                </div>
                <button id="closeChooseDentistModalBtn" type="button" class="w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            <div id="dentistListContainer" class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
            <div id="dentistListEmptyState" class="hidden px-5 pb-5 text-sm font-semibold text-slate-500"></div>
        </div>
    </div>
</div>

<div id="chooseServiceModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/45"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Service Selection</p>
                    <h3 class="text-lg font-extrabold text-slate-900">Choose Service</h3>
                </div>
                <button id="closeChooseServiceModalBtn" type="button" class="w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div class="px-5 py-4 border-b border-slate-100">
                <input id="serviceSearchInput" type="text" class="walkin-input w-full py-3 px-4" placeholder="Search service name or category"/>
            </div>

            <div class="max-h-[24rem] overflow-y-auto">
                <div id="serviceListEmptyState" class="hidden px-5 py-8 text-center text-sm font-semibold text-slate-500"></div>
                <div id="serviceListContainer" class="divide-y divide-slate-100"></div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>js/staff-ui-dialogs.js"></script>
<script>
    (function () {
        const dateInput = document.getElementById('walkInDateInput');
        const timeInput = document.getElementById('walkInTimeInput');
        const choosePatientBtn = document.getElementById('choosePatientBtn');
        const selectedPatientLabel = document.getElementById('selectedPatientLabel');
        const selectedPatientIdInput = document.getElementById('selectedPatientId');
        const choosePatientModal = document.getElementById('choosePatientModal');
        const closeChoosePatientModalBtn = document.getElementById('closeChoosePatientModalBtn');
        const patientSearchInput = document.getElementById('patientSearchInput');
        const patientListContainer = document.getElementById('patientListContainer');
        const patientListEmptyState = document.getElementById('patientListEmptyState');
        const chooseDentistBtn = document.getElementById('chooseDentistBtn');
        const selectedDentistLabel = document.getElementById('selectedDentistLabel');
        const selectedDentistIdInput = document.getElementById('selectedDentistId');
        const chooseDentistModal = document.getElementById('chooseDentistModal');
        const closeChooseDentistModalBtn = document.getElementById('closeChooseDentistModalBtn');
        const dentistListContainer = document.getElementById('dentistListContainer');
        const dentistListEmptyState = document.getElementById('dentistListEmptyState');
        const chooseServiceBtn = document.getElementById('chooseServiceBtn');
        const selectedServiceLabel = document.getElementById('selectedServiceLabel');
        const selectedServiceIdInput = document.getElementById('selectedServiceId');
        const addServiceBtn = document.getElementById('addServiceBtn');
        const selectedServicesContainer = document.getElementById('selectedServicesContainer');
        const chooseServiceModal = document.getElementById('chooseServiceModal');
        const closeChooseServiceModalBtn = document.getElementById('closeChooseServiceModalBtn');
        const serviceSearchInput = document.getElementById('serviceSearchInput');
        const serviceListContainer = document.getElementById('serviceListContainer');
        const serviceListEmptyState = document.getElementById('serviceListEmptyState');
        const notesInput = document.getElementById('walkInNotesInput');
        const createWalkInAppointmentBtn = document.getElementById('createWalkInAppointmentBtn');
        const dentistsSeedData = <?php echo json_encode($walkInDentists, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const patientsApiUrl = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/patients.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const servicesApiUrl = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/services.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const appointmentsApiUrl = <?php
            $selfPath = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
            $query = ['action' => 'create_walkin'];
            if ($currentTenantSlug !== '') {
                $query['clinic_slug'] = $currentTenantSlug;
            }
            echo json_encode($selfPath . '?' . http_build_query($query), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>;
        const clinicSlug = <?php echo json_encode((string) $currentTenantSlug, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const stockDentistImage = 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&w=300&q=60';
        let allPatients = [];
        let allServices = [];
        let selectedServices = [];

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

        function formatTimeForApi(date) {
            return pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds());
        }

        function updateNow() {
            const now = new Date();
            if (dateInput) dateInput.value = formatDate(now);
            if (timeInput) timeInput.value = formatTime(now);
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function setEmptyState(message) {
            if (!patientListEmptyState || !patientListContainer) return;
            patientListEmptyState.textContent = message;
            patientListEmptyState.classList.remove('hidden');
            patientListContainer.innerHTML = '';
        }

        function renderPatientsList(patients) {
            if (!patientListContainer || !patientListEmptyState) return;
            if (!patients.length) {
                setEmptyState('No patients found.');
                return;
            }

            patientListEmptyState.classList.add('hidden');
            patientListContainer.innerHTML = patients.map(function (patient) {
                const displayId = escapeHtml(patient.patient_id || '-');
                const fullName = escapeHtml((patient.first_name || '') + ' ' + (patient.last_name || ''));
                const contact = escapeHtml(patient.contact_number || '-');

                return '' +
                    '<div class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">' +
                        '<div class="min-w-0">' +
                            '<p class="text-sm font-bold text-slate-900 truncate">' + fullName + '</p>' +
                            '<p class="text-xs font-semibold text-slate-500 mt-1">ID: ' + displayId + ' | Contact: ' + contact + '</p>' +
                        '</div>' +
                        '<button type="button" data-action="select-patient" data-patient-id="' + escapeHtml(patient.patient_id || '') + '" data-patient-name="' + fullName + '" class="shrink-0 rounded-lg bg-primary text-white px-3 py-2 text-xs font-extrabold uppercase tracking-wide hover:bg-primary/90 transition-colors">' +
                            'Select' +
                        '</button>' +
                    '</div>';
            }).join('');
        }

        function setServiceEmptyState(message) {
            if (!serviceListContainer || !serviceListEmptyState) return;
            serviceListEmptyState.textContent = message;
            serviceListEmptyState.classList.remove('hidden');
            serviceListContainer.innerHTML = '';
        }

        function renderServicesList(services) {
            if (!serviceListContainer || !serviceListEmptyState) return;
            if (!services.length) {
                setServiceEmptyState('No services found.');
                return;
            }
            serviceListEmptyState.classList.add('hidden');
            serviceListContainer.innerHTML = services.map(function (service) {
                const serviceId = escapeHtml(service.service_id || '');
                const serviceName = escapeHtml(service.service_name || '');
                const category = escapeHtml(service.category || '-');
                const price = Number(service.price || 0);
                return '' +
                    '<div class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">' +
                        '<div class="min-w-0">' +
                            '<p class="text-sm font-bold text-slate-900 truncate">' + serviceName + '</p>' +
                            '<p class="text-xs font-semibold text-slate-500 mt-1">' + category + ' | Php ' + price.toFixed(2) + '</p>' +
                        '</div>' +
                        '<button type="button" data-action="select-service" data-service-id="' + serviceId + '" class="shrink-0 rounded-lg bg-primary text-white px-3 py-2 text-xs font-extrabold uppercase tracking-wide hover:bg-primary/90 transition-colors">Select</button>' +
                    '</div>';
            }).join('');
        }

        function renderSelectedServices() {
            if (!selectedServicesContainer) return;
            if (!selectedServices.length) {
                selectedServicesContainer.innerHTML = '<p class="text-[11px] font-semibold text-slate-500">No services added yet.</p>';
                document.getElementById('walkInTotalAmount').textContent = 'P0.00';
                return;
            }
            const totalAmount = selectedServices.reduce(function (sum, svc) {
                return sum + Number(svc.price || 0);
            }, 0);
            selectedServicesContainer.innerHTML = selectedServices.map(function (service) {
                const serviceId = escapeHtml(service.service_id || '');
                const serviceName = escapeHtml(service.service_name || '');
                const price = Number(service.price || 0).toFixed(2);
                return '' +
                    '<div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">' +
                        '<span class="text-xs font-bold text-slate-700">' + serviceName + ' (Php ' + price + ')</span>' +
                        '<button type="button" data-action="remove-service" data-service-id="' + serviceId + '" class="w-6 h-6 rounded-md bg-red-50 text-red-500 hover:bg-red-100 inline-flex items-center justify-center">' +
                            '<span class="material-symbols-outlined text-[14px]">close</span>' +
                        '</button>' +
                    '</div>';
            }).join('');
            document.getElementById('walkInTotalAmount').textContent = 'P' + totalAmount.toFixed(2);
        }

        function renderDentistsList() {
            if (!dentistListContainer || !dentistListEmptyState) return;
            if (!dentistsSeedData.length) {
                dentistListEmptyState.textContent = 'No dentists available.';
                dentistListEmptyState.classList.remove('hidden');
                dentistListContainer.innerHTML = '';
                return;
            }

            dentistListEmptyState.classList.add('hidden');
            dentistListContainer.innerHTML = dentistsSeedData.map(function (dentist) {
                const firstName = String(dentist.first_name || '').trim();
                const lastName = String(dentist.last_name || '').trim();
                const fullNameText = (firstName + ' ' + lastName).trim() || 'Unnamed Dentist';
                const fullName = escapeHtml(fullNameText);
                const availability = String(dentist.status || '').toLowerCase() === 'active' ? 'Available today' : 'Unavailable';
                const imageSrc = escapeHtml(dentist.profile_image || stockDentistImage);
                const dentistId = escapeHtml(dentist.dentist_id || '');
                return '' +
                    '<div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 flex flex-col items-center text-center">' +
                        '<img src="' + imageSrc + '" alt="' + fullName + '" class="w-24 h-24 rounded-full object-cover border border-slate-200 bg-white"/>' +
                        '<p class="mt-3 text-sm font-extrabold text-slate-900">' + fullName + '</p>' +
                        '<p class="mt-1 text-xs font-semibold text-slate-500">' + escapeHtml(availability) + '</p>' +
                        '<button type="button" data-action="select-dentist" data-dentist-id="' + dentistId + '" data-dentist-name="' + fullName + '" class="mt-3 rounded-lg bg-primary text-white px-3 py-2 text-xs font-extrabold uppercase tracking-wide hover:bg-primary/90 transition-colors">Select</button>' +
                    '</div>';
            }).join('');
        }

        async function loadAllPatients() {
            if (!patientListContainer) return;
            setEmptyState('Loading patients...');

            const mergedPatients = [];
            let page = 1;
            let totalPages = 1;

            try {
                while (page <= totalPages) {
                    const response = await fetch(patientsApiUrl + '?page=' + page + '&limit=100', {
                        credentials: 'include'
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to load patients.');
                    }

                    const pagePatients = Array.isArray(data.data && data.data.patients) ? data.data.patients : [];
                    mergedPatients.push.apply(mergedPatients, pagePatients);
                    const pagesCount = Number(data.data && data.data.pagination && data.data.pagination.pages);
                    totalPages = Number.isFinite(pagesCount) && pagesCount > 0 ? pagesCount : 1;
                    page += 1;
                }
            } catch (error) {
                setEmptyState(error.message || 'Failed to load patients.');
                return;
            }

            allPatients = mergedPatients;
            renderPatientsList(allPatients);
        }

        async function loadAllServices() {
            setServiceEmptyState('Loading services...');
            const mergedServices = [];
            let page = 1;
            let hasMore = true;

            try {
                while (hasMore) {
                    const response = await fetch(servicesApiUrl + '?status=active&page=' + page + '&limit=100', {
                        credentials: 'include'
                    });
                    const data = await response.json();
                    if (!response.ok || !data.success) {
                        throw new Error(data.message || 'Failed to load services.');
                    }
                    const pageServices = Array.isArray(data.data && data.data.services) ? data.data.services : [];
                    mergedServices.push.apply(mergedServices, pageServices);
                    hasMore = pageServices.length === 100;
                    page += 1;
                }
            } catch (error) {
                setServiceEmptyState(error.message || 'Failed to load services.');
                return;
            }

            allServices = mergedServices;
            renderServicesList(allServices);
        }

        function openChoosePatientModal() {
            if (!choosePatientModal) return;
            choosePatientModal.classList.remove('hidden');
            if (patientSearchInput) patientSearchInput.value = '';
            loadAllPatients();
        }

        function closeChoosePatientModal() {
            if (!choosePatientModal) return;
            choosePatientModal.classList.add('hidden');
        }

        function openChooseDentistModal() {
            if (!chooseDentistModal) return;
            chooseDentistModal.classList.remove('hidden');
            renderDentistsList();
        }

        function closeChooseDentistModal() {
            if (!chooseDentistModal) return;
            chooseDentistModal.classList.add('hidden');
        }

        function openChooseServiceModal() {
            if (!chooseServiceModal) return;
            chooseServiceModal.classList.remove('hidden');
            if (serviceSearchInput) serviceSearchInput.value = '';
            loadAllServices();
        }

        function closeChooseServiceModal() {
            if (!chooseServiceModal) return;
            chooseServiceModal.classList.add('hidden');
        }

        function setSelectedPatient(patientId, patientName) {
            if (selectedPatientIdInput) selectedPatientIdInput.value = patientId || '';
            if (selectedPatientLabel) selectedPatientLabel.textContent = patientName || 'Choose patient';
        }

        function setSelectedDentist(dentistId, dentistName) {
            if (selectedDentistIdInput) selectedDentistIdInput.value = dentistId || '';
            if (selectedDentistLabel) selectedDentistLabel.textContent = dentistName || 'Select dentist';
        }

        function setSelectedService(serviceId, serviceName) {
            if (selectedServiceIdInput) selectedServiceIdInput.value = serviceId || '';
            if (selectedServiceLabel) selectedServiceLabel.textContent = serviceName || 'Select service';
        }

        function buildApiUrl(baseUrl) {
            if (!clinicSlug) return baseUrl;
            const separator = baseUrl.indexOf('?') === -1 ? '?' : '&';
            return baseUrl + separator + 'clinic_slug=' + encodeURIComponent(clinicSlug);
        }

        async function parseJsonResponse(response) {
            const rawText = await response.text();
            if (!rawText || !rawText.trim()) {
                return {
                    success: false,
                    message: 'Server returned an empty response.'
                };
            }
            try {
                return JSON.parse(rawText);
            } catch (error) {
                return {
                    success: false,
                    message: 'Unexpected server response. Please try again.',
                    raw: rawText
                };
            }
        }

        async function submitWalkInAppointment() {
            const patientId = selectedPatientIdInput ? String(selectedPatientIdInput.value || '').trim() : '';
            const dentistId = selectedDentistIdInput ? String(selectedDentistIdInput.value || '').trim() : '';
            const notes = notesInput ? String(notesInput.value || '').trim() : '';

            if (!patientId) {
                void staffUiAlert({ message: 'Please select a patient first.', variant: 'warning', title: 'Patient required' });
                return;
            }
            if (!dentistId) {
                void staffUiAlert({ message: 'Please select an assigned dentist.', variant: 'warning', title: 'Dentist required' });
                return;
            }
            if (!selectedServices.length) {
                void staffUiAlert({ message: 'Please add at least one service.', variant: 'warning', title: 'Services required' });
                return;
            }

            const now = new Date();
            const payload = {
                patient_id: patientId,
                appointment_date: now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate()),
                appointment_time: formatTimeForApi(now),
                services: selectedServices.map(function (service) {
                    return {
                        id: service.service_id || null,
                        name: service.service_name || '',
                        price: Number(service.price || 0),
                        category: service.category || ''
                    };
                }),
                service_categories: Array.from(new Set(selectedServices.map(function (service) {
                    return String(service.category || '').trim();
                }).filter(Boolean))),
                notes: notes,
                dentist_id: dentistId,
                visit_type: 'walk_in',
                status: 'pending'
            };

            if (createWalkInAppointmentBtn) {
                createWalkInAppointmentBtn.disabled = true;
                createWalkInAppointmentBtn.classList.add('opacity-70', 'cursor-not-allowed');
            }

            try {
                const response = await fetch(buildApiUrl(appointmentsApiUrl), {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                const data = await parseJsonResponse(response);
                if (!response.ok || !data.success) {
                    const message = (data && data.message) ? data.message : 'Failed to create walk-in appointment.';
                    throw new Error(message);
                }

                await staffUiAlert({
                    title: 'Walk-in booked',
                    message: 'Walk-in appointment created successfully. Booking ID: ' + (data.data && data.data.booking_id ? data.data.booking_id : 'N/A'),
                    variant: 'success'
                });
                window.location.href = <?php echo json_encode($backToAppointmentsHref, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
            } catch (error) {
                await staffUiAlert({
                    title: 'Could not create walk-in',
                    message: error && error.message ? error.message : 'Unable to create walk-in appointment right now.',
                    variant: 'error'
                });
            } finally {
                if (createWalkInAppointmentBtn) {
                    createWalkInAppointmentBtn.disabled = false;
                    createWalkInAppointmentBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                }
            }
        }

        if (choosePatientBtn) {
            choosePatientBtn.addEventListener('click', openChoosePatientModal);
        }
        if (closeChoosePatientModalBtn) {
            closeChoosePatientModalBtn.addEventListener('click', closeChoosePatientModal);
        }
        if (choosePatientModal) {
            choosePatientModal.addEventListener('click', function (event) {
                if (event.target === choosePatientModal || event.target === choosePatientModal.firstElementChild) {
                    closeChoosePatientModal();
                }
            });
        }
        if (chooseDentistBtn) {
            chooseDentistBtn.addEventListener('click', openChooseDentistModal);
        }
        if (closeChooseDentistModalBtn) {
            closeChooseDentistModalBtn.addEventListener('click', closeChooseDentistModal);
        }
        if (chooseDentistModal) {
            chooseDentistModal.addEventListener('click', function (event) {
                if (event.target === chooseDentistModal || event.target === chooseDentistModal.firstElementChild) {
                    closeChooseDentistModal();
                }
            });
        }
        if (chooseServiceBtn) {
            chooseServiceBtn.addEventListener('click', openChooseServiceModal);
        }
        if (closeChooseServiceModalBtn) {
            closeChooseServiceModalBtn.addEventListener('click', closeChooseServiceModal);
        }
        if (chooseServiceModal) {
            chooseServiceModal.addEventListener('click', function (event) {
                if (event.target === chooseServiceModal || event.target === chooseServiceModal.firstElementChild) {
                    closeChooseServiceModal();
                }
            });
        }
        if (serviceSearchInput) {
            serviceSearchInput.addEventListener('input', function () {
                const keyword = serviceSearchInput.value.trim().toLowerCase();
                if (!keyword) {
                    renderServicesList(allServices);
                    return;
                }
                const filtered = allServices.filter(function (service) {
                    const haystack = [
                        service.service_name,
                        service.category,
                        service.service_id
                    ].join(' ').toLowerCase();
                    return haystack.indexOf(keyword) !== -1;
                });
                renderServicesList(filtered);
            });
        }
        if (patientSearchInput) {
            patientSearchInput.addEventListener('input', function () {
                const keyword = patientSearchInput.value.trim().toLowerCase();
                if (!keyword) {
                    renderPatientsList(allPatients);
                    return;
                }
                const filtered = allPatients.filter(function (patient) {
                    const haystack = [
                        patient.patient_id,
                        patient.first_name,
                        patient.last_name,
                        patient.contact_number
                    ].join(' ').toLowerCase();
                    return haystack.indexOf(keyword) !== -1;
                });
                renderPatientsList(filtered);
            });
        }
        if (patientListContainer) {
            patientListContainer.addEventListener('click', function (event) {
                const button = event.target.closest('button[data-action="select-patient"]');
                if (!button) return;
                const patientId = button.getAttribute('data-patient-id') || '';
                const patientName = button.getAttribute('data-patient-name') || '';
                setSelectedPatient(patientId, patientName);
                closeChoosePatientModal();
            });
        }
        if (dentistListContainer) {
            dentistListContainer.addEventListener('click', function (event) {
                const button = event.target.closest('button[data-action="select-dentist"]');
                if (!button) return;
                setSelectedDentist(button.getAttribute('data-dentist-id') || '', button.getAttribute('data-dentist-name') || '');
                closeChooseDentistModal();
            });
        }
        if (serviceListContainer) {
            serviceListContainer.addEventListener('click', function (event) {
                const button = event.target.closest('button[data-action="select-service"]');
                if (!button) return;
                const serviceId = button.getAttribute('data-service-id') || '';
                const service = allServices.find(function (item) {
                    return String(item.service_id || '') === serviceId;
                });
                if (!service) return;
                setSelectedService(service.service_id || '', service.service_name || '');
                closeChooseServiceModal();
            });
        }
        if (addServiceBtn) {
            addServiceBtn.addEventListener('click', function () {
                const serviceId = selectedServiceIdInput ? selectedServiceIdInput.value : '';
                if (!serviceId) return;
                const service = allServices.find(function (item) {
                    return String(item.service_id || '') === String(serviceId);
                });
                if (!service) return;
                const alreadyAdded = selectedServices.some(function (item) {
                    return String(item.service_id || '') === String(service.service_id || '');
                });
                if (alreadyAdded) return;
                selectedServices.push(service);
                setSelectedService('', 'Select service');
                renderSelectedServices();
            });
        }
        if (selectedServicesContainer) {
            selectedServicesContainer.addEventListener('click', function (event) {
                const button = event.target.closest('button[data-action="remove-service"]');
                if (!button) return;
                const serviceId = button.getAttribute('data-service-id') || '';
                selectedServices = selectedServices.filter(function (item) {
                    return String(item.service_id || '') !== String(serviceId);
                });
                renderSelectedServices();
            });
        }
        if (createWalkInAppointmentBtn) {
            createWalkInAppointmentBtn.addEventListener('click', submitWalkInAppointment);
        }

        updateNow();
        renderSelectedServices();
        setInterval(updateNow, 1000);
    })();
</script>
</body>
</html>
