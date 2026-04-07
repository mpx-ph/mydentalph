<?php
$staff_nav_active = 'dashboard';
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

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$todayAppointments = 0;
$pendingRequests = 0;
$todayRevenue = 0.0;
$recentBookings = [];
$todayCompleted = 0;
$todayCancelled = 0;

try {
    $pdo = getDBConnection();

    if ($tenantId === '' && $currentTenantSlug !== '') {
        $tenantStmt = $pdo->prepare('SELECT tenant_id FROM tbl_tenants WHERE clinic_slug = ? LIMIT 1');
        $tenantStmt->execute([$currentTenantSlug]);
        $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC);
        if ($tenantRow && isset($tenantRow['tenant_id'])) {
            $tenantId = (string) $tenantRow['tenant_id'];
        }
    }

    if ($tenantId !== '') {
        $today = date('Y-m-d');

        $stmt = $pdo->prepare('SELECT COUNT(*) AS count FROM tbl_appointments WHERE tenant_id = ? AND appointment_date = ?');
        $stmt->execute([$tenantId, $today]);
        $todayAppointments = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM tbl_appointments WHERE tenant_id = ? AND status = 'pending'");
        $stmt->execute([$tenantId]);
        $pendingRequests = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM tbl_appointments WHERE tenant_id = ? AND appointment_date = ? AND status = 'completed'");
        $stmt->execute([$tenantId, $today]);
        $todayCompleted = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        $stmt = $pdo->prepare("SELECT COUNT(*) AS count FROM tbl_appointments WHERE tenant_id = ? AND appointment_date = ? AND (status = 'cancelled' OR status = 'no_show')");
        $stmt->execute([$tenantId, $today]);
        $todayCancelled = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM tbl_payments WHERE tenant_id = ? AND DATE(payment_date) = ? AND status = 'completed'");
        $stmt->execute([$tenantId, $today]);
        $todayRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0);

        $recentSql = "
            SELECT
                a.booking_id,
                a.appointment_date,
                a.appointment_time,
                a.service_type,
                a.status,
                a.total_treatment_cost,
                p.patient_id AS patient_display_id,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                d.first_name AS dentist_first_name,
                d.last_name AS dentist_last_name
            FROM tbl_appointments a
            LEFT JOIN tbl_patients p
                ON p.tenant_id = a.tenant_id
               AND p.patient_id = a.patient_id
            LEFT JOIN tbl_dentists d
                ON d.tenant_id = a.tenant_id
               AND d.dentist_id = a.dentist_id
            WHERE a.tenant_id = ?
            ORDER BY a.created_at DESC
            LIMIT 10
        ";
        $stmt = $pdo->prepare($recentSql);
        $stmt->execute([$tenantId]);
        $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    error_log('Staff dashboard load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision - Staff Dashboard</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
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
                    }
                }
            }
        };
    </script>
<style>
        html { scrollbar-gutter: stable; }
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
        .glass-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        }
        .editorial-shadow {
            box-shadow: 0 12px 40px -10px rgba(19, 28, 37, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .editorial-word {
            text-shadow: 0 0 12px rgba(43, 139, 235, 0.1);
            letter-spacing: -0.02em;
        }
        @keyframes staff-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .staff-page-enter {
            animation: staff-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .staff-card-lift {
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .staff-card-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        .staff-stat-card {
            background: linear-gradient(165deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.92) 100%);
            box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.95), 0 10px 40px -10px rgba(15, 23, 42, 0.1);
        }
        .staff-welcome-banner {
            background: linear-gradient(120deg, #1e3a5f 0%, #2b8beb 42%, #5ab0ff 100%);
            box-shadow: 0 20px 50px -20px rgba(43, 139, 235, 0.45);
        }
        .staff-action-btn {
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
        }
        .staff-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px -14px rgba(15, 23, 42, 0.45);
        }
        .staff-modal-hidden {
            opacity: 0;
            pointer-events: none;
            transform: scale(0.98);
        }
        .staff-modal-visible {
            opacity: 1;
            pointer-events: auto;
            transform: scale(1);
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 staff-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<div class="p-10 space-y-10">
<section class="relative">
<div class="absolute top-10 right-8 w-[26rem] h-[26rem] bg-primary/10 rounded-full blur-[120px] -z-10 pointer-events-none" aria-hidden="true"></div>
<p class="text-primary font-bold text-[10px] sm:text-xs uppercase tracking-[0.35em] flex items-center gap-3 mb-3">
<span class="w-8 sm:w-10 h-px bg-primary/40"></span> Staff dashboard
</p>
<h2 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold font-headline tracking-tight text-on-background">
                Clinic <span class="font-editorial italic font-normal text-primary editorial-word">Command Center</span>
</h2>
<p class="text-on-surface-variant mt-3 text-base sm:text-lg font-medium max-w-2xl leading-relaxed">
                Live overview of appointments, pending requests, and today&rsquo;s revenue.
</p>
</section>

<section class="staff-welcome-banner rounded-3xl px-6 sm:px-10 py-7 sm:py-8 text-white relative overflow-hidden">
<div class="absolute inset-0 opacity-[0.12] pointer-events-none" style="background-image: radial-gradient(circle at 20% 120%, #fff 0, transparent 55%), radial-gradient(circle at 90% -20%, #fff 0, transparent 45%);" aria-hidden="true"></div>
<div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
<div class="max-w-2xl">
<p class="text-white/80 text-xs font-bold uppercase tracking-[0.25em] mb-2">Today at a glance</p>
<p class="text-2xl sm:text-3xl font-extrabold font-headline tracking-tight">Keep the queue moving and monitor daily outcomes.</p>
<p class="text-white/85 mt-2 text-sm sm:text-base font-medium leading-relaxed">Use quick actions to jump to appointments or review today&rsquo;s booking activity.</p>
</div>
<div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-3 shrink-0">
<a class="staff-action-btn inline-flex items-center justify-center gap-2 rounded-2xl bg-white text-primary px-6 py-3.5 text-sm font-bold shadow-lg shadow-black/10 hover:brightness-[1.03] transition-all ring-2 ring-white/30" href="StaffAppointments.php">
<span class="material-symbols-outlined text-xl">calendar_month</span>
                    Open Appointments
</a>
<button id="staff-open-quick-modal" type="button" class="staff-action-btn inline-flex items-center justify-center gap-2 rounded-2xl bg-white/10 text-white border border-white/25 px-6 py-3.5 text-sm font-bold hover:bg-white/15 transition-all backdrop-blur-sm">
<span class="material-symbols-outlined text-xl">tips_and_updates</span>
                    Quick Actions
</button>
</div>
</div>
</section>

<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
<div class="elevated-card staff-card-lift staff-stat-card p-8 rounded-3xl flex flex-col justify-between">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_available</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Today</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo number_format($todayAppointments); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Today's Appointments</p>
</div>
</div>

<div class="elevated-card staff-card-lift staff-stat-card p-8 rounded-3xl flex flex-col justify-between">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-600">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">pending_actions</span>
</div>
<span class="text-[10px] font-black text-amber-600 bg-amber-50 px-3 py-1.5 rounded-full uppercase tracking-widest">Queue</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo number_format($pendingRequests); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Pending Request</p>
</div>
</div>

<div class="elevated-card staff-card-lift staff-stat-card p-8 rounded-3xl flex flex-col justify-between">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">Today</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">₱<?php echo number_format($todayRevenue, 2); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Today's Revenue</p>
</div>
</div>
</section>

<section class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8 items-start">
<div class="lg:col-span-2 elevated-card glass-card editorial-shadow rounded-3xl overflow-hidden staff-card-lift">
<div class="p-8 border-b border-slate-100 flex justify-between items-center bg-white">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">Recent Bookings</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Latest tenant appointment entries</p>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Name</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date &amp; Time</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Service Type</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Assigned Dentist</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<?php if (empty($recentBookings)): ?>
<tr>
<td class="px-8 py-10 text-sm font-semibold text-slate-500" colspan="6">No recent bookings found for this clinic.</td>
</tr>
<?php else: ?>
<?php foreach ($recentBookings as $booking): ?>
<?php
    $patientName = trim(((string) ($booking['patient_first_name'] ?? '')) . ' ' . ((string) ($booking['patient_last_name'] ?? '')));
    if ($patientName === '') {
        $patientName = 'Unknown Patient';
    }
    $patientIdLabel = trim((string) ($booking['patient_display_id'] ?? ''));
    $serviceType = trim((string) ($booking['service_type'] ?? ''));
    if ($serviceType === '') {
        $serviceType = 'General Consultation';
    }
    $dentistName = trim(((string) ($booking['dentist_first_name'] ?? '')) . ' ' . ((string) ($booking['dentist_last_name'] ?? '')));
    if ($dentistName === '') {
        $dentistName = 'Unassigned Dentist';
    }
    $status = strtolower(trim((string) ($booking['status'] ?? 'pending')));
    $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Pending';
    $statusClasses = 'bg-amber-50 text-amber-600';
    if ($status === 'completed') {
        $statusClasses = 'bg-emerald-50 text-emerald-600';
    } elseif ($status === 'confirmed') {
        $statusClasses = 'bg-primary/10 text-primary';
    } elseif ($status === 'cancelled' || $status === 'no_show') {
        $statusClasses = 'bg-slate-100 text-slate-600';
    }
    $dateDisplay = '-';
    if (!empty($booking['appointment_date'])) {
        $dateDisplay = date('M d, Y', strtotime((string) $booking['appointment_date']));
    }
    $timeDisplay = '-';
    if (!empty($booking['appointment_time'])) {
        $timeDisplay = date('h:i A', strtotime((string) $booking['appointment_time']));
    }
    $amount = (float) ($booking['total_treatment_cost'] ?? 0);
?>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-5">
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5">
<?php echo $patientIdLabel !== '' ? 'ID: ' . htmlspecialchars($patientIdLabel, ENT_QUOTES, 'UTF-8') : 'ID: N/A'; ?>
</p>
</div>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($dateDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5"><?php echo htmlspecialchars($timeDisplay, ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-6 py-5">
<span class="px-3 py-1 bg-slate-100 text-slate-600 text-[10px] font-bold rounded-full uppercase tracking-wider"><?php echo htmlspecialchars($serviceType, ENT_QUOTES, 'UTF-8'); ?></span>
</td>
<td class="px-6 py-5">
<p class="text-sm font-medium text-slate-700"><?php echo htmlspecialchars($dentistName, ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 <?php echo $statusClasses; ?> text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full bg-current"></span>
<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
</span>
</td>
<td class="px-8 py-5 text-right">
<p class="text-sm font-extrabold text-slate-900">$<?php echo number_format($amount, 2); ?></p>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="p-6 bg-slate-50/30 border-t border-slate-100">
<p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing <?php echo count($recentBookings); ?> recent entries</p>
</div>
</div>

<div class="space-y-4">
<div class="glass-card rounded-3xl p-6 editorial-shadow staff-card-lift">
<h3 class="text-base font-extrabold font-headline text-on-background mb-1">Today&rsquo;s outcomes</h3>
<p class="text-xs text-on-surface-variant mb-5">Status breakdown for staff operations today</p>
<div class="space-y-4">
<div class="flex items-center justify-between gap-3 rounded-2xl bg-amber-50/90 border border-amber-100 px-4 py-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-amber-700 text-xl shrink-0">schedule</span>
<span class="text-sm font-bold text-amber-950">Pending</span>
</div>
<span class="text-xl font-extrabold tabular-nums text-amber-950"><?php echo number_format($pendingRequests); ?></span>
</div>
<div class="flex items-center justify-between gap-3 rounded-2xl bg-emerald-50/90 border border-emerald-100 px-4 py-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-emerald-700 text-xl shrink-0">check_circle</span>
<span class="text-sm font-bold text-emerald-950">Completed</span>
</div>
<span class="text-xl font-extrabold tabular-nums text-emerald-950"><?php echo number_format($todayCompleted); ?></span>
</div>
<div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-100/90 border border-slate-200 px-4 py-3">
<div class="flex items-center gap-2 min-w-0">
<span class="material-symbols-outlined text-slate-600 text-xl shrink-0">cancel</span>
<span class="text-sm font-bold text-slate-900">Cancelled / No-show</span>
</div>
<span class="text-xl font-extrabold tabular-nums text-slate-900"><?php echo number_format($todayCancelled); ?></span>
</div>
</div>
</div>
</div>
</section>
</div>
</main>
<div id="staff-quick-modal" class="fixed inset-0 z-[70] bg-slate-950/50 backdrop-blur-sm transition-all duration-200 staff-modal-hidden" aria-hidden="true">
<div class="min-h-screen w-full flex items-center justify-center p-5">
<div class="w-full max-w-lg rounded-3xl bg-white border border-slate-200 shadow-2xl shadow-slate-900/20 overflow-hidden">
<div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between gap-3">
<div>
<p class="text-[11px] font-bold uppercase tracking-[0.22em] text-primary">Staff quick actions</p>
<h3 class="text-xl font-extrabold font-headline text-on-background mt-1">Jump to frequently used pages</h3>
</div>
<button id="staff-close-quick-modal-top" type="button" class="w-9 h-9 rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-all">
<span class="material-symbols-outlined text-[20px]">close</span>
</button>
</div>
<div class="p-6 space-y-3">
<a href="StaffAppointments.php" class="block rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 hover:border-primary/40 hover:bg-primary/5 transition-all">
<p class="font-bold text-slate-900">Manage Appointments</p>
<p class="text-sm text-slate-600 mt-1">Review schedules, queue, and updates.</p>
</a>
<a href="StaffManagePatient.php" class="block rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 hover:border-primary/40 hover:bg-primary/5 transition-all">
<p class="font-bold text-slate-900">Open Patient Records</p>
<p class="text-sm text-slate-600 mt-1">Search and view patient information quickly.</p>
</a>
<a href="StaffPaymentRecording.php" class="block rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3 hover:border-primary/40 hover:bg-primary/5 transition-all">
<p class="font-bold text-slate-900">Record Payments</p>
<p class="text-sm text-slate-600 mt-1">Capture completed payments for today.</p>
</a>
</div>
<div class="px-6 pb-6">
<button id="staff-close-quick-modal" type="button" class="w-full rounded-2xl bg-primary text-white font-bold py-3 hover:brightness-105 transition-all">Close</button>
</div>
</div>
</div>
</div>
<script>
    (function () {
        var modal = document.getElementById('staff-quick-modal');
        var openBtn = document.getElementById('staff-open-quick-modal');
        var closeBtn = document.getElementById('staff-close-quick-modal');
        var closeTopBtn = document.getElementById('staff-close-quick-modal-top');
        if (!modal || !openBtn || !closeBtn || !closeTopBtn) {
            return;
        }

        function closeModal() {
            modal.classList.remove('staff-modal-visible');
            modal.classList.add('staff-modal-hidden');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
        }

        function openModal() {
            modal.classList.remove('staff-modal-hidden');
            modal.classList.add('staff-modal-visible');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        }

        openBtn.addEventListener('click', openModal);
        closeBtn.addEventListener('click', closeModal);
        closeTopBtn.addEventListener('click', closeModal);
        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    })();
</script>
</body></html>