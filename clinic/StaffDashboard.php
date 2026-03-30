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

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM tbl_payments WHERE tenant_id = ? AND DATE(payment_date) = ? AND status = 'completed'");
        $stmt->execute([$tenantId, $today]);
        $todayRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0);

        $recentSql = "
            SELECT
                a.booking_id,
                a.appointment_date,
                a.appointment_time,
                COALESCE(s.services_list, a.service_type) AS service_type,
                a.status,
                a.total_treatment_cost,
                p.patient_id AS patient_display_id,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                d.first_name AS dentist_first_name,
                d.last_name AS dentist_last_name
            FROM tbl_appointments a
            LEFT JOIN (
                SELECT 
                    booking_id, 
                    GROUP_CONCAT(service_name SEPARATOR ', ') as services_list
                FROM tbl_appointment_services 
                GROUP BY booking_id
            ) s ON s.booking_id = a.booking_id
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
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<div class="p-10 space-y-10">
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> STAFF DASHBOARD
</div>
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Welcome to <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Dashboard</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                    Live overview of appointments, pending requests, and today's revenue.
                </p>
</div>
</section>

<section class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between">
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

<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between">
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

<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">payments</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">Today</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">$<?php echo number_format($todayRevenue, 2); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Today's Revenue</p>
</div>
</div>
</section>

<section class="elevated-card rounded-3xl overflow-hidden">
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
</section>
</div>
</main>
</body></html>