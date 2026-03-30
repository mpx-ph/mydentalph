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
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-8 sticky top-0 z-10 shrink-0">
<div>
<h1 class="text-2xl font-bold">Admin Dashboard</h1>
<p class="text-sm text-slate-500 dark:text-slate-400">Daily overview for <?php echo date('F j, Y'); ?></p>
</div>
</header>
<div class="flex-1 overflow-y-auto p-8">
<div class="mx-auto max-w-7xl space-y-8">
<div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
<div class="relative overflow-hidden rounded-2xl bg-surface-light dark:bg-surface-dark p-6 shadow-card hover:shadow-lg transition-shadow duration-300 group">
<div class="absolute right-0 top-0 h-32 w-32 translate-x-8 translate-y-[-8px] rounded-full bg-blue-50 dark:bg-blue-900/10 opacity-50 blur-2xl transition-all group-hover:bg-blue-100 dark:group-hover:bg-blue-900/20"></div>
<div class="relative z-10 flex flex-col h-full justify-between">
<div class="flex justify-between items-start">
<div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
<span class="material-symbols-outlined">event_available</span>
</div>
<span class="text-xs font-bold uppercase tracking-wide text-slate-400 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-md">Today</span>
</div>
<div class="mt-6">
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Today's Appointments</p>
<h3 id="todayAppointments" class="mt-1 text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight"><?php echo number_format($todayAppointments); ?></h3>
</div>
<div class="mt-4 flex items-center pt-4 border-t border-slate-50 dark:border-slate-800">
<a class="group/link flex items-center text-sm font-bold text-primary hover:text-primary-dark" href="#">
                                    View Schedule <span class="material-symbols-outlined ml-1 transition-transform group-hover/link:translate-x-1" style="font-size: 16px;">arrow_forward</span>
</a>
</div>
</div>
</div>
<div class="relative overflow-hidden rounded-2xl bg-surface-light dark:bg-surface-dark p-6 shadow-card hover:shadow-lg transition-shadow duration-300 group">
<div class="absolute right-0 top-0 h-32 w-32 translate-x-8 translate-y-[-8px] rounded-full bg-violet-50 dark:bg-violet-900/10 opacity-50 blur-2xl transition-all group-hover:bg-violet-100 dark:group-hover:bg-violet-900/20"></div>
<div class="relative z-10 flex flex-col h-full justify-between">
<div class="flex justify-between items-start">
<div class="flex h-12 w-12 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
<span class="material-symbols-outlined">pending_actions</span>
</div>
</div>
<div class="mt-6">
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Pending Requests</p>
<h3 id="pendingRequests" class="mt-1 text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight"><?php echo number_format($pendingRequests); ?></h3>
</div>
<div class="mt-4 flex items-center pt-4 border-t border-slate-50 dark:border-slate-800">
<a class="group/link flex items-center text-sm font-bold text-primary hover:text-primary-dark" href="#">
                                    View Requests <span class="material-symbols-outlined ml-1 transition-transform group-hover/link:translate-x-1" style="font-size: 16px;">arrow_forward</span>
</a>
</div>
    </div>
</div>
</div>
<div class="grid grid-cols-1 gap-6">
<div class="rounded-2xl bg-surface-light dark:bg-surface-dark p-8 shadow-card">
<div class="mb-8 flex flex-wrap items-center justify-between gap-4">
<div>
<h3 class="text-xl font-bold text-slate-900 dark:text-white">Recent Bookings</h3>
<p class="text-sm text-slate-500 dark:text-slate-400 font-medium">Latest appointment bookings</p>
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
        echo '<div class="text-center py-12">
                <span class="material-symbols-outlined text-slate-300 dark:text-slate-600 mb-3" style="font-size: 48px;">event_busy</span>
                <p class="text-sm font-medium text-slate-400 dark:text-slate-500">No bookings found</p>
                <p class="text-xs mt-1 text-slate-400 dark:text-slate-500">Bookings will appear here once created</p>
              </div>';
    } else {
        echo '<div class="overflow-x-auto">
                <table class="w-full">
                  <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-700">
                      <th class="text-left py-3 px-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Patient</th>
                      <th class="text-left py-3 px-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Booking ID</th>
                      <th class="text-left py-3 px-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Date & Time</th>
                      <th class="text-left py-3 px-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Service</th>
                      <th class="text-left py-3 px-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-slate-800">';
        
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
                'pending' => ['bg' => 'bg-amber-50 dark:bg-amber-900/20', 'text' => 'text-amber-700 dark:text-amber-400', 'label' => 'Pending'],
                'confirmed' => ['bg' => 'bg-blue-50 dark:bg-blue-900/20', 'text' => 'text-blue-700 dark:text-blue-400', 'label' => 'Confirmed'],
                'completed' => ['bg' => 'bg-green-50 dark:bg-green-900/20', 'text' => 'text-green-700 dark:text-green-400', 'label' => 'Completed'],
                'cancelled' => ['bg' => 'bg-slate-100 dark:bg-slate-800', 'text' => 'text-slate-600 dark:text-slate-400', 'label' => 'Cancelled'],
                'no_show' => ['bg' => 'bg-red-50 dark:bg-red-900/20', 'text' => 'text-red-700 dark:text-red-400', 'label' => 'No Show']
            ];
            
            $statusStyle = isset($statusConfig[$status]) ? $statusConfig[$status] : $statusConfig['pending'];
            
            echo '<tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="py-4 px-4">
                      <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary/10 dark:bg-primary/20 flex items-center justify-center">
                          <span class="text-primary font-bold text-sm">' . strtoupper(substr($patientName, 0, 1)) . '</span>
                        </div>
                        <div>
                          <p class="font-bold text-slate-900 dark:text-white text-sm">' . htmlspecialchars($patientName) . '</p>
                          ' . (!empty($booking['contact_number']) ? '<p class="text-xs text-slate-500 dark:text-slate-400">' . htmlspecialchars($booking['contact_number']) . '</p>' : '') . '
                        </div>
                      </div>
                    </td>
                    <td class="py-4 px-4">
                      <span class="font-mono text-sm font-medium text-slate-600 dark:text-slate-300">' . htmlspecialchars(isset($booking['booking_id']) ? $booking['booking_id'] : 'N/A') . '</span>
                    </td>
                    <td class="py-4 px-4">
                      <div class="flex flex-col">
                        <span class="text-sm font-medium text-slate-900 dark:text-white">' . $appointmentDate . '</span>
                        <span class="text-xs text-slate-500 dark:text-slate-400">' . $appointmentTime . '</span>
                      </div>
                    </td>
                    <td class="py-4 px-4">
                      <p class="text-sm text-slate-700 dark:text-slate-300">' . htmlspecialchars($serviceType) . '</p>
                    </td>
                    <td class="py-4 px-4">
                      <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ' . $statusStyle['bg'] . ' ' . $statusStyle['text'] . '">' . $statusStyle['label'] . '</span>
                    </td>
                  </tr>';
        }
        
        echo '  </tbody>
              </table>
            </div>';
    }
} catch (Exception $e) {
    echo '<div class="text-center py-12">
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

