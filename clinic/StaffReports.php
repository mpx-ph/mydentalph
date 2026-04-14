<?php
$staff_nav_active = 'reports';
require_once __DIR__ . '/config/config.php';
// Dentist role restriction: redirect to dashboard
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['user_role']) && strtolower(trim((string) $_SESSION['user_role'])) === 'dentist') {
    header('Location: StaffDashboard.php');
    exit;
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
$totalRevenue = 0.0;
$totalAppointments = 0;
$totalPatients = 0;
$allAppointments = [];

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
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM tbl_payments WHERE tenant_id = ? AND status = 'completed'");
        $stmt->execute([$tenantId]);
        $totalRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total_appointments FROM tbl_appointments WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $totalAppointments = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_appointments'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total_patients FROM tbl_patients WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $totalPatients = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_patients'] ?? 0);

        $appointmentsSql = "
            SELECT
                a.booking_id,
                a.appointment_date,
                a.appointment_time,
                a.service_type,
                a.service_description,
                a.status,
                a.total_treatment_cost,
                p.patient_id,
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
            ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC
            LIMIT 100
        ";
        $stmt = $pdo->prepare($appointmentsSql);
        $stmt->execute([$tenantId]);
        $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} catch (Throwable $e) {
    error_log('Staff reports load error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Reports - Aetheris Dental Systems</title>
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
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<div class="p-10 space-y-8">
<section class="flex flex-col gap-4 mb-2">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> CLINICAL PRECISION
            </div>
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Reports <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Overview</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                    Track appointments, service delivery, and revenue in one place.
                </p>
</div>
</section>

<section class="elevated-card p-8 rounded-3xl">
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
<div class="lg:col-span-2">
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Search</label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
<input class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Search by patient, service, or staff" type="text"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Export Data</label>
<button class="w-full bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-primary/30 flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-sm">download</span>
                        Export CSV
                    </button>
</div>
</div>
</section>

<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">Monthly</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">$<?php echo number_format($totalRevenue, 2); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Revenue</p>
</div>
</div>

<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_available</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">All Time</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo number_format($totalAppointments); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Appointments</p>
</div>
</div>

<div class="elevated-card p-7 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">group</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Registered</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo number_format($totalPatients); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Patients</p>
</div>
</div>
</section>

<section class="elevated-card p-8 rounded-3xl">
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date</label>
<div class="relative">
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_month</span>
<input class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="Select date range" type="text"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Status</label>
<select class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option>All Statuses</option>
<option>Completed</option>
<option>Confirmed</option>
<option>Pending</option>
<option>Cancelled</option>
</select>
</div>
<div>
<label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Assigned Staff</label>
<select class="w-full bg-slate-50 border-none rounded-xl py-2.5 px-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none">
<option>All Staff</option>
<option>Dr. Helena Vance</option>
<option>Dr. Simon Kaan</option>
<option>Dr. Noah Ellis</option>
</select>
</div>
</div>
</section>

<section class="elevated-card rounded-3xl overflow-hidden">
<div class="px-8 py-6 border-b border-slate-100 bg-white">
<h3 class="text-2xl font-bold font-headline text-on-background">All Appointments</h3>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Details</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Appointment Info (Date and Time)</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Treatment/Service (Details)</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Assigned Staff</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Amount</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<?php if (empty($allAppointments)): ?>
<tr>
<td class="px-8 py-8 text-sm font-semibold text-slate-500" colspan="6">No appointments found for this clinic yet.</td>
</tr>
<?php else: ?>
<?php foreach ($allAppointments as $appointment): ?>
<?php
    $patientName = trim(((string) ($appointment['patient_first_name'] ?? '')) . ' ' . ((string) ($appointment['patient_last_name'] ?? '')));
    if ($patientName === '') {
        $patientName = 'Unknown Patient';
    }
    $patientId = trim((string) ($appointment['patient_id'] ?? ''));
    $patientInitials = strtoupper(substr(trim((string) ($appointment['patient_first_name'] ?? 'U')), 0, 1) . substr(trim((string) ($appointment['patient_last_name'] ?? 'P')), 0, 1));
    if ($patientInitials === '') {
        $patientInitials = 'NA';
    }
    $dateLabel = '-';
    if (!empty($appointment['appointment_date'])) {
        $dateLabel = date('M d, Y', strtotime((string) $appointment['appointment_date']));
    }
    $timeLabel = '-';
    if (!empty($appointment['appointment_time'])) {
        $timeLabel = date('h:i A', strtotime((string) $appointment['appointment_time']));
    }
    $serviceMain = trim((string) ($appointment['service_type'] ?? ''));
    if ($serviceMain === '') {
        $serviceMain = 'General Consultation';
    }
    $serviceDetails = trim((string) ($appointment['service_description'] ?? ''));
    $serviceLabel = $serviceDetails !== '' ? ($serviceMain . ' - ' . $serviceDetails) : $serviceMain;
    $dentistName = trim(((string) ($appointment['dentist_first_name'] ?? '')) . ' ' . ((string) ($appointment['dentist_last_name'] ?? '')));
    if ($dentistName === '') {
        $dentistName = 'Unassigned Dentist';
    }
    $status = strtolower(trim((string) ($appointment['status'] ?? 'pending')));
    $statusLabel = $status !== '' ? ucfirst(str_replace('_', ' ', $status)) : 'Pending';
    $statusClasses = 'bg-amber-50 text-amber-600';
    $dotClass = 'bg-amber-500';
    if ($status === 'completed') {
        $statusClasses = 'bg-emerald-50 text-emerald-600';
        $dotClass = 'bg-emerald-500';
    } elseif ($status === 'confirmed') {
        $statusClasses = 'bg-primary/10 text-primary';
        $dotClass = 'bg-primary';
    } elseif ($status === 'cancelled' || $status === 'no_show') {
        $statusClasses = 'bg-slate-100 text-slate-600';
        $dotClass = 'bg-slate-500';
    }
    $amount = (float) ($appointment['total_treatment_cost'] ?? 0);
?>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-6">
<div class="flex items-center gap-4">
<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center font-black text-primary text-xs"><?php echo htmlspecialchars($patientInitials, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider mt-0.5"><?php echo $patientId !== '' ? 'ID: #' . htmlspecialchars($patientId, ENT_QUOTES, 'UTF-8') : 'ID: N/A'; ?></span>
</div>
</div>
</td>
<td class="px-6 py-6">
<div class="flex flex-col">
<span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></span>
<span class="text-[9px] text-slate-400 font-black uppercase tracking-widest mt-0.5"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</td>
<td class="px-6 py-6 text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($serviceLabel, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-6 py-6 text-sm font-medium text-slate-700"><?php echo htmlspecialchars($dentistName, ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-6 py-6">
<span class="inline-flex items-center gap-1.5 px-3 py-1 <?php echo $statusClasses; ?> text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full <?php echo $dotClass; ?>"></span>
<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
</span>
</td>
<td class="px-8 py-6 text-right text-sm font-extrabold text-slate-900">$<?php echo number_format($amount, 2); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</section>
</div>
</main>
</body></html>
