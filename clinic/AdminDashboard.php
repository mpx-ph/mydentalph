<?php
/**
 * Admin Dashboard
 * Requires admin authentication
 */
$pageTitle = 'Dental Clinic Admin Dashboard';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // Require admin role
$tenantId = requireClinicTenantId();

// Fetch dashboard statistics
$todayAppointments = 0;
$pendingRequests = 0;

try {
    $pdo = getDBConnection();
    
    // Get today's appointments count
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND tenant_id = ?");
    $stmt->execute([$today, $tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $todayAppointments = isset($result['count']) ? $result['count'] : 0;
    
    // Get pending requests count (appointments with pending status)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending' AND tenant_id = ?");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingRequests = isset($result['count']) ? $result['count'] : 0;
    
} catch (Exception $e) {
    // Log error but don't break the page
    error_log("Error fetching dashboard stats: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<style>
    /* Visual helpers copied from StaffDashboard vibe */
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
    .dark .elevated-card {
        background: rgba(15, 23, 42, 0.95);
        border-color: rgba(51, 65, 85, 0.9);
        box-shadow: 0 10px 40px -20px rgba(0, 0, 0, 0.35);
    }
    .active-glow {
        box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
    }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
</style>
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="px-2">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> ADMIN DASHBOARD
</div>
<div class="mt-5">
<h2 class="font-headline text-4xl sm:text-5xl font-extrabold tracking-tighter leading-tight text-slate-900 dark:text-white">
            Admin <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Dashboard</span>
</h2>
<p class="font-body text-sm sm:text-base font-medium text-slate-500 dark:text-slate-400 mt-4">
            Daily overview for <?php echo date('F j, Y'); ?>
</p>
</div>
</header>
<div class="flex-1 overflow-y-auto p-10 space-y-10 mesh-bg">
<div class="mx-auto max-w-7xl">
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2">
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
    <div class="flex justify-between items-start mb-6">
        <div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">event_available</span>
        </div>
        <span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Today</span>
    </div>
    <div>
        <p id="todayAppointments" class="text-5xl font-extrabold font-headline text-slate-900 dark:text-white tracking-tighter">
            <?php echo number_format($todayAppointments); ?>
        </p>
        <p class="text-xs font-black text-slate-500 dark:text-slate-400 uppercase tracking-[0.2em] mt-2">Today's Appointments</p>
    </div>
    <div class="mt-6 flex items-center pt-4 border-t border-slate-100 dark:border-slate-800">
        <a class="group/link flex items-center text-sm font-bold text-primary hover:text-primary-dark" href="#">
            View Schedule <span class="material-symbols-outlined ml-1 transition-transform group-hover/link:translate-x-1" style="font-size: 16px;">arrow_forward</span>
        </a>
    </div>
</div>

<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
    <div class="flex justify-between items-start mb-6">
        <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-700 transition-colors group-hover:bg-amber-500 group-hover:text-white">
            <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">pending_actions</span>
        </div>
        <span class="text-[10px] font-black text-amber-700 bg-amber-100 px-3 py-1.5 rounded-full uppercase tracking-widest">Pending</span>
    </div>
    <div>
        <p id="pendingRequests" class="text-5xl font-extrabold font-headline text-slate-900 dark:text-white tracking-tighter">
            <?php echo number_format($pendingRequests); ?>
        </p>
        <p class="text-xs font-black text-slate-500 dark:text-slate-400 uppercase tracking-[0.2em] mt-2">Pending Requests</p>
    </div>
    <div class="mt-6 flex items-center pt-4 border-t border-slate-100 dark:border-slate-800">
        <a class="group/link flex items-center text-sm font-bold text-primary hover:text-primary-dark" href="#">
            View Requests <span class="material-symbols-outlined ml-1 transition-transform group-hover/link:translate-x-1" style="font-size: 16px;">arrow_forward</span>
        </a>
    </div>
</div>
</div>
<div class="grid grid-cols-1 gap-6">
<div class="elevated-card rounded-3xl overflow-hidden border-2 border-primary/20">
<div class="px-10 py-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
<div>
<h3 class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">Recent Bookings</h3>
<p class="text-xs font-medium text-slate-500 dark:text-slate-400 mt-2">Latest appointment bookings</p>
</div>
<div class="flex items-center gap-2">
<a href="#" class="flex items-center gap-2 rounded-lg bg-primary/10 dark:bg-primary/20 px-4 py-2 text-sm font-bold text-primary hover:bg-primary/20 dark:hover:bg-primary/30 transition-all">
<span class="material-symbols-outlined" style="font-size: 18px;">visibility</span>
View All
</a>
</div>
</div>
<?php
// Fetch recent appointments from database
try {
    $pdo = getDBConnection();
    $sql = "
        SELECT a.*, 
               p.first_name,
               p.last_name,
               p.patient_id as patient_display_id,
               p.contact_number
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        WHERE a.tenant_id = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recentBookings)) {
        echo '<div class="text-center py-14">
                <span class="material-symbols-outlined text-slate-300 dark:text-slate-600 mb-3" style="font-size: 48px;">event_busy</span>
                <p class="text-sm font-medium text-slate-500 dark:text-slate-400">No bookings found</p>
                <p class="text-xs mt-1 text-slate-400 dark:text-slate-500">Bookings will appear here once created</p>
              </div>';
    } else {
        echo '<div class="overflow-x-auto">
                <table class="w-full text-left">
                  <thead>
                    <tr class="bg-slate-50/50">
                      <th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Patient</th>
                      <th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Booking ID</th>
                      <th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Date &amp; Time</th>
                      <th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Service</th>
                      <th class="px-10 py-6 text-[11px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-primary/20">';
        
        foreach ($recentBookings as $booking) {
            $patientName = trim((isset($booking['first_name']) ? $booking['first_name'] : '') . ' ' . (isset($booking['last_name']) ? $booking['last_name'] : ''));
            if (empty($patientName)) {
                $patientName = 'Unknown Patient';
            }
            
            // Format date
            $appointmentDate = date('M j, Y', strtotime($booking['appointment_date']));
            $appointmentTime = date('g:i A', strtotime($booking['appointment_time']));
            
            // Format service type (truncate if too long)
            $serviceType = isset($booking['service_type']) ? $booking['service_type'] : 'General Consultation';
            if (strlen($serviceType) > 40) {
                $serviceType = substr($serviceType, 0, 40) . '...';
            }
            
            // Status badge styling
            $status = strtolower(isset($booking['status']) ? $booking['status'] : 'pending');
            $statusConfig = [
                'pending' => ['bg' => 'bg-amber-100 dark:bg-amber-900/20', 'text' => 'text-amber-700 dark:text-amber-400', 'label' => 'Pending'],
                'confirmed' => ['bg' => 'bg-blue-100 dark:bg-blue-900/20', 'text' => 'text-blue-700 dark:text-blue-400', 'label' => 'Confirmed'],
                'completed' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/20', 'text' => 'text-emerald-700 dark:text-emerald-400', 'label' => 'Completed'],
                'cancelled' => ['bg' => 'bg-slate-100 dark:bg-slate-800', 'text' => 'text-slate-700 dark:text-slate-300', 'label' => 'Cancelled'],
                'no_show' => ['bg' => 'bg-red-100 dark:bg-red-900/20', 'text' => 'text-red-700 dark:text-red-400', 'label' => 'No Show']
            ];
            
            $statusStyle = isset($statusConfig[$status]) ? $statusConfig[$status] : $statusConfig['pending'];
            
            echo '<tr class="group hover:bg-slate-50/50 transition-colors">
                    <td class="px-10 py-8">
                      <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                          <span class="text-primary font-bold text-sm">' . strtoupper(substr($patientName, 0, 1)) . '</span>
                        </div>
                        <div>
                          <p class="font-bold text-slate-900 dark:text-white text-sm">' . htmlspecialchars($patientName) . '</p>
                          ' . (!empty($booking['contact_number']) ? '<p class="text-xs text-slate-500 dark:text-slate-400">' . htmlspecialchars($booking['contact_number']) . '</p>' : '') . '
                        </div>
                      </div>
                    </td>
                    <td class="px-10 py-8">
                      <span class="font-mono text-sm font-medium text-slate-600 dark:text-slate-300">' . htmlspecialchars(isset($booking['booking_id']) ? $booking['booking_id'] : 'N/A') . '</span>
                    </td>
                    <td class="px-10 py-8">
                      <div class="flex flex-col">
                        <span class="text-sm font-medium text-slate-900 dark:text-white">' . $appointmentDate . '</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">' . $appointmentTime . '</span>
                      </div>
                    </td>
                    <td class="px-10 py-8">
                      <p class="text-sm text-slate-700 dark:text-slate-300">' . htmlspecialchars($serviceType) . '</p>
                    </td>
                    <td class="px-10 py-8">
                      <span class="inline-flex items-center px-4 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest ' . $statusStyle['bg'] . ' ' . $statusStyle['text'] . '">' . $statusStyle['label'] . '</span>
                    </td>
                  </tr>';
        }
        
        echo '  </tbody>
              </table>
            </div>';
    }
} catch (Exception $e) {
    echo '<div class="text-center py-14">
            <span class="material-symbols-outlined text-red-300 dark:text-red-600 mb-3" style="font-size: 48px;">error</span>
            <p class="text-sm font-medium text-red-600 dark:text-red-400">Error loading bookings</p>
            <p class="text-xs mt-1 text-slate-400 dark:text-slate-500">' . htmlspecialchars($e->getMessage()) . '</p>
          </div>';
}
?>
</div>
</div>
<div class="h-10"></div>
</div>
</main>
</div>

