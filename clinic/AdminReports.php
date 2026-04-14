<?php
/**
 * Admin Reports Page
 * Requires admin authentication
 */
$pageTitle = 'DentalPlus Reports Module';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // Require admin role
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

// Fetch statistics from database
$totalRevenue = 0;
$totalAppointments = 0;
$totalPatients = 0;
$allAppointments = [];

try {
    $pdo = getDBConnection();
    
    // Fetch Total Revenue from payments table (only paid payments)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'paid'");
    $stmt->execute();
    $revenueResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalRevenue = $revenueResult['total'] ?? 0;
    
    // Fetch Total Appointments count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments");
    $stmt->execute();
    $appointmentsResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalAppointments = $appointmentsResult['total'] ?? 0;
    
    // Fetch Total Patients count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM patients");
    $stmt->execute();
    $patientsResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalPatients = $patientsResult['total'] ?? 0;
    
    // Fetch All Appointments with patient information
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.booking_id,
            a.appointment_date,
            a.appointment_time,
            a.service_type,
            a.service_description,
            a.status,
            a.total_treatment_cost,
            p.patient_id as patient_display_id,
            p.first_name,
            p.last_name,
            p.contact_number,
            COALESCE((
                SELECT SUM(pay.amount) 
                FROM payments pay 
                WHERE pay.booking_id = a.booking_id 
                AND pay.status = 'paid'
            ), 0) as total_paid
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.patient_id
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute();
    $allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log('Database error in AdminReports.php: ' . $e->getMessage());
    // Continue with default values if database query fails
}
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-8 sticky top-0 z-10 shrink-0">
<div>
<h1 class="text-2xl font-bold">Reports & Analytics</h1>
<p class="text-sm text-slate-500 dark:text-slate-400">Comprehensive insights into clinic performance and appointments.</p>
</div>
<div class="flex items-center gap-6">
<div class="relative hidden md:block">
<span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input id="headerSearchInput" class="pl-10 pr-4 py-2 w-64 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all" placeholder="Search reports..." type="text"/>
</div>
<div class="flex gap-2">
<button id="exportReportsBtn" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-full font-semibold flex items-center gap-2 transition-all shadow-lg shadow-primary/20">
<span class="material-icons-outlined">download</span>
                    Export Data
                </button>
</div>
</header>
<div class="flex-1 overflow-y-auto p-8">
<div class="space-y-8">
<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
<div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-surface-dark p-6 shadow-soft hover:shadow-lg transition-all duration-300 border border-slate-100 dark:border-slate-800">
<div class="flex justify-between items-start">
<div>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">TOTAL REVENUE</p>
        <h3 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight" id="totalRevenue">₱<?php echo number_format($totalRevenue, 2); ?></h3>
        </div>
        <div class="flex size-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400 group-hover:scale-110 transition-transform">
        <span class="material-symbols-outlined">payments</span>
        </div>
        </div>
</div>
<div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-surface-dark p-6 shadow-soft hover:shadow-lg transition-all duration-300 border border-slate-100 dark:border-slate-800">
<div class="flex justify-between items-start">
        <div>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">TOTAL APPOINTMENTS</p>
        <h3 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight" id="totalAppointments"><?php echo number_format($totalAppointments); ?></h3>
        </div>
        <div class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400 group-hover:scale-110 transition-transform">
        <span class="material-symbols-outlined">calendar_month</span>
        </div>
        </div>
</div>
<div class="group relative overflow-hidden rounded-2xl bg-white dark:bg-surface-dark p-6 shadow-soft hover:shadow-lg transition-all duration-300 border border-slate-100 dark:border-slate-800">
<div class="flex justify-between items-start">
        <div>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400 mb-1">TOTAL PATIENTS</p>
        <h3 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight" id="totalPatients"><?php echo number_format($totalPatients); ?></h3>
        </div>
        <div class="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 group-hover:scale-110 transition-transform">
        <span class="material-symbols-outlined">people</span>
        </div>
        </div>
</div>
</div>
<div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-surface-dark shadow-soft overflow-hidden">
<div class="p-5 border-b border-slate-100 dark:border-slate-800">
<h3 class="text-xl font-bold text-slate-900 dark:text-white mb-4">All Appointments</h3>
</div>
<div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30">
<div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
<div class="relative group">
<button class="flex items-center gap-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-surface-dark px-3.5 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:border-primary/50 dark:hover:border-primary/50 hover:text-primary transition-colors shadow-sm">
<span class="material-symbols-outlined text-[18px] text-slate-400 group-hover:text-primary">calendar_today</span>
                                Last 30 Days
                                <span class="material-symbols-outlined text-[18px] text-slate-400 group-hover:text-primary">expand_more</span>
</button>
</div>
<div class="h-6 w-px bg-slate-300 dark:bg-slate-700 hidden sm:block"></div>
<div class="relative group">
<button class="flex items-center gap-2 rounded-lg border border-transparent hover:border-slate-200 dark:hover:border-slate-700 bg-transparent px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 transition-all">
<span>Status</span>
<span class="bg-slate-200 dark:bg-slate-700 text-xs px-1.5 py-0.5 rounded ml-1 text-slate-600 dark:text-slate-300">All</span>
<span class="material-symbols-outlined text-[18px] ml-1">expand_more</span>
</button>
</div>
<div class="relative group">
<button class="flex items-center gap-2 rounded-lg border border-transparent hover:border-slate-200 dark:hover:border-slate-700 bg-transparent px-3 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-white dark:hover:bg-slate-800 transition-all">
<span>Provider</span>
<span class="material-symbols-outlined text-[18px] ml-1">expand_more</span>
</button>
</div>
</div>
<div class="relative w-full sm:max-w-xs group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined text-[20px]">search</span>
</span>
<input id="reportsSearchInput" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 py-2 pl-10 pr-4 text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:border-primary focus:outline-none focus:ring-4 focus:ring-primary/10 transition-all shadow-sm" placeholder="Search by patient, ID or provider..." type="text"/>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left text-sm border-collapse">
<thead class="bg-slate-50 dark:bg-slate-900/50">
<tr>
<th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider w-4" scope="col">
<input class="rounded border-slate-300 text-primary focus:ring-primary h-4 w-4" type="checkbox"/>
</th>
<th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" scope="col">Patient Details</th>
<th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" scope="col">Appointment Info</th>
<th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" scope="col">Treatment</th>
<th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider" scope="col">Status</th>
<th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right" scope="col">Amount</th>
<th class="px-6 py-4 text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center" scope="col">Actions</th>
</tr>
</thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/50" id="appointmentsTableBody">
            <?php
            if (empty($allAppointments)) {
                echo '<tr><td colspan="7" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                    <span class="material-symbols-outlined text-4xl mb-2 block">event_busy</span>
                    <p>No appointments found</p>
                </td></tr>';
            } else {
                foreach ($allAppointments as $apt) {
                    $patientName = trim(($apt['first_name'] ?? '') . ' ' . ($apt['last_name'] ?? ''));
                    if (empty($patientName)) {
                        $patientName = 'Unknown Patient';
                    }
                    
                    // Format date and time
                    $appointmentDate = $apt['appointment_date'] ? date('M j, Y', strtotime($apt['appointment_date'])) : 'N/A';
                    $appointmentTime = $apt['appointment_time'] ? date('g:i A', strtotime($apt['appointment_time'])) : 'N/A';
                    
                    // Service/Treatment info
                    $serviceType = $apt['service_type'] ?? 'General Consultation';
                    $serviceDescription = $apt['service_description'] ?? '';
                    
                    // Status badge styling
                    $status = strtolower($apt['status'] ?? 'pending');
                    $statusConfig = [
                        'pending' => ['bg' => 'bg-amber-50 dark:bg-amber-900/20', 'text' => 'text-amber-700 dark:text-amber-400', 'label' => 'Pending'],
                        'confirmed' => ['bg' => 'bg-blue-50 dark:bg-blue-900/20', 'text' => 'text-blue-700 dark:text-blue-400', 'label' => 'Confirmed'],
                        'completed' => ['bg' => 'bg-green-50 dark:bg-green-900/20', 'text' => 'text-green-700 dark:text-green-400', 'label' => 'Completed'],
                        'cancelled' => ['bg' => 'bg-slate-100 dark:bg-slate-800', 'text' => 'text-slate-600 dark:text-slate-400', 'label' => 'Cancelled'],
                        'no_show' => ['bg' => 'bg-red-50 dark:bg-red-900/20', 'text' => 'text-red-700 dark:text-red-400', 'label' => 'No Show']
                    ];
                    $statusStyle = $statusConfig[$status] ?? $statusConfig['pending'];
                    
                    // Amount - use total_paid if available, otherwise total_treatment_cost
                    $amount = $apt['total_paid'] > 0 ? $apt['total_paid'] : ($apt['total_treatment_cost'] ?? 0);
                    
                    echo '<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-6 py-4">
                            <input class="rounded border-slate-300 text-primary focus:ring-primary h-4 w-4" type="checkbox"/>
                        </td>
                        <td class="px-6 py-4">
                            <p class="font-semibold text-slate-900 dark:text-white">' . htmlspecialchars($patientName) . '</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">' . htmlspecialchars($apt['patient_display_id'] ?? 'N/A') . '</p>
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-slate-900 dark:text-white font-medium">' . htmlspecialchars($appointmentDate) . '</span>
                            <div class="flex items-center gap-1 mt-1">
                                <span class="material-symbols-outlined text-xs text-slate-400">schedule</span>
                                <span class="text-xs text-slate-500 dark:text-slate-400">' . htmlspecialchars($appointmentTime) . '</span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-medium text-slate-900 dark:text-white">' . htmlspecialchars($serviceType) . '</span>
                            ' . (!empty($serviceDescription) && strlen($serviceDescription) > 50 ? '<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">' . htmlspecialchars(substr($serviceDescription, 0, 50)) . '...</p>' : '') . '
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ' . $statusStyle['bg'] . ' ' . $statusStyle['text'] . '">' . $statusStyle['label'] . '</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <span class="font-semibold text-slate-900 dark:text-white">₱' . number_format($amount, 2) . '</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <button class="p-1 text-slate-400 hover:text-primary transition-colors" title="View Details">
                                <span class="material-symbols-outlined text-xl">visibility</span>
                            </button>
                        </td>
                    </tr>';
                }
            }
            ?>
            </tbody>
</table>
</div>
<div class="flex flex-col sm:flex-row items-center justify-between border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-6 py-4 gap-4">
            <div class="text-sm text-slate-500 dark:text-slate-400">
                        Showing <span class="font-semibold text-slate-900 dark:text-white" id="showingCount"><?php echo count($allAppointments); ?></span> of <span class="font-semibold text-slate-900 dark:text-white" id="totalCount"><?php echo count($allAppointments); ?></span> results
                    </div>
<div class="flex gap-2">
<button class="flex items-center gap-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-surface-dark px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed transition-all" disabled="">
<span class="material-symbols-outlined text-[16px]">chevron_left</span> Previous
                        </button>
<div class="hidden sm:flex items-center gap-1">
<button class="h-8 w-8 rounded-lg bg-primary text-white text-sm font-medium">1</button>
<button class="h-8 w-8 rounded-lg border border-transparent hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 text-sm font-medium transition-colors">2</button>
<button class="h-8 w-8 rounded-lg border border-transparent hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 text-sm font-medium transition-colors">3</button>
<span class="text-slate-400 px-1">...</span>
<button class="h-8 w-8 rounded-lg border border-transparent hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 text-sm font-medium transition-colors">12</button>
</div>
<button class="flex items-center gap-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-surface-dark px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-all">
                            Next <span class="material-symbols-outlined text-[16px]">chevron_right</span>
</button>
</div>
</div>
</div>
</div>
</main>
</div>
<script>
    function loadUserData() {
        const userDataStr = sessionStorage.getItem('adminUser');
        if (userDataStr) {
            try {
                const userData = JSON.parse(userDataStr);
                const userNameEl = document.getElementById('userName');
                const userRoleEl = document.getElementById('userRole');
                const userPhotoEl = document.getElementById('userPhoto');
                if (userNameEl) userNameEl.textContent = userData.name || 'Marc';
                if (userRoleEl) userRoleEl.textContent = 'Administrator';
                if (userPhotoEl && userData.photo) {
                    userPhotoEl.style.backgroundImage = `url("${userData.photo}")`;
                }
            } catch (e) {
                console.error('Error loading user data:', e);
            }
        }
    }
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            sessionStorage.removeItem('adminUser');
            window.location.href = '<?php echo BASE_URL; ?>api/logout.php';
        });
    }
    loadUserData();

    // Convert image to base64
    function getImageAsBase64(url) {
        return new Promise((resolve) => {
            fetch(url)
                .then(res => {
                    if (res.ok) {
                        return res.blob();
                    }
                    throw new Error('Fetch failed');
                })
                .then(blob => {
                    const reader = new FileReader();
                    reader.onloadend = () => resolve(reader.result);
                    reader.onerror = () => {
                        tryCanvasMethod();
                    };
                    reader.readAsDataURL(blob);
                })
                .catch(() => {
                    tryCanvasMethod();
                });
            
            function tryCanvasMethod() {
                const img = new Image();
                img.onload = function() {
                    try {
                        const canvas = document.createElement('canvas');
                        canvas.width = img.width;
                        canvas.height = img.height;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0);
                        const dataURL = canvas.toDataURL('image/png');
                        resolve(dataURL);
                    } catch (e) {
                        console.warn('Canvas method failed:', e);
                        resolve(null);
                    }
                };
                img.onerror = function() {
                    console.warn('Could not load logo image:', url);
                    resolve(null);
                };
                img.crossOrigin = 'Anonymous';
                img.src = url;
            }
        });
    }

    // Extract summary statistics from stat cards
    function extractSummaryStats() {
        const stats = {};
        
        // Total Revenue
        const totalRevenueEl = document.getElementById('totalRevenue');
        if (totalRevenueEl) {
            stats.totalRevenue = totalRevenueEl.textContent.trim().replace('₱', '').replace(/,/g, '');
        }
        
        // Total Appointments
        const totalAppointmentsEl = document.getElementById('totalAppointments');
        if (totalAppointmentsEl) {
            stats.totalAppointments = totalAppointmentsEl.textContent.trim().replace(/,/g, '');
        }
        
        // Total Patients
        const totalPatientsEl = document.getElementById('totalPatients');
        if (totalPatientsEl) {
            stats.totalPatients = totalPatientsEl.textContent.trim().replace(/,/g, '');
        }
        
        return stats;
    }

    // Extract table data
    function extractTableData() {
        const rows = [];
        const tableRows = document.querySelectorAll('tbody tr');
        
        tableRows.forEach(row => {
            const patientNameEl = row.querySelector('p.font-semibold');
            const patientIdEl = row.querySelector('p.text-xs.text-slate-500');
            const dateEl = row.querySelector('td:nth-child(3) span.text-slate-900, td:nth-child(3) span.text-slate-200');
            const timeContainer = row.querySelector('td:nth-child(3) .flex.items-center.gap-1');
            let time = '';
            if (timeContainer) {
                const timeText = timeContainer.textContent.trim();
                time = timeText.replace(/schedule/g, '').trim();
            }
            const treatmentEl = row.querySelector('td:nth-child(4) span.font-medium');
            const doctorEl = row.querySelector('td:nth-child(4) .text-xs');
            const statusEl = row.querySelector('td:nth-child(5) span');
            const amountEl = row.querySelector('td:nth-child(6) span');
            
            if (patientNameEl) {
                const patientName = patientNameEl.textContent.trim();
                const patientId = patientIdEl ? patientIdEl.textContent.trim() : '';
                const date = dateEl ? dateEl.textContent.trim() : '';
                const treatment = treatmentEl ? treatmentEl.textContent.trim() : '';
                const doctor = doctorEl ? doctorEl.textContent.trim() : '';
                const status = statusEl ? statusEl.textContent.trim() : '';
                const amount = amountEl ? amountEl.textContent.trim().replace('₱', '').replace(/,/g, '') : '0.00';
                
                rows.push({
                    patientName,
                    patientId,
                    date,
                    time,
                    treatment,
                    doctor,
                    status,
                    amount: parseFloat(amount) || 0
                });
            }
        });
        
        return rows;
    }

    // Get active tab name
    function getActiveTabName() {
        const activeTab = document.querySelector('nav a.border-primary, nav a.text-primary');
        return activeTab ? activeTab.textContent.trim() : 'Appointments';
    }

    // Export Reports to PDF
    async function exportReportsToPDF() {
        try {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            const summaryStats = extractSummaryStats();
            const tableData = extractTableData();
            
            // Load logo
            let logoData = null;
            const logoPaths = [
                'DRCGLogo2.png',
                './DRCGLogo2.png',
                '../DRCGLogo2.png'
            ];
            
            const existingImg = document.querySelector('img[src*="DRCGLogo2"], img[src*="DRCGLogo"]');
            if (existingImg && existingImg.complete && existingImg.naturalWidth > 0) {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = existingImg.naturalWidth;
                    canvas.height = existingImg.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(existingImg, 0, 0);
                    logoData = canvas.toDataURL('image/png');
                } catch (e) {
                    console.warn('Could not get logo from DOM:', e);
                }
            }
            
            if (!logoData) {
                for (const logoPath of logoPaths) {
                    try {
                        logoData = await getImageAsBase64(logoPath);
                        if (logoData && logoData.startsWith('data:')) {
                            break;
                        }
                    } catch (e) {
                        console.warn('Could not load logo from', logoPath);
                    }
                }
            }
            
            // Set up PDF
            const pageWidth = doc.internal.pageSize.getWidth();
            const margin = 20;
            let yPos = margin;
            let logoWidth = 60;
            let logoHeight = 20;
            
            // Add logo if available
            if (logoData && logoData.startsWith('data:')) {
                try {
                    const img = new Image();
                    await new Promise((resolve) => {
                        img.onload = () => {
                            const aspectRatio = img.naturalWidth / img.naturalHeight;
                            const maxLogoWidth = 70;
                            const maxLogoHeight = 25;
                            
                            if (aspectRatio > 2.5) {
                                logoWidth = maxLogoWidth;
                                logoHeight = maxLogoWidth / aspectRatio;
                            } else {
                                logoHeight = maxLogoHeight;
                                logoWidth = maxLogoHeight * aspectRatio;
                            }
                            resolve();
                        };
                        img.onerror = () => resolve();
                        img.src = logoData;
                        setTimeout(() => resolve(), 1000);
                    });
                    
                    doc.addImage(logoData, 'PNG', margin, yPos, logoWidth, logoHeight);
                    yPos = margin + logoHeight + 5;
                } catch (e) {
                    console.warn('Could not add logo:', e);
                    yPos = 25;
                }
            } else {
                yPos = 25;
            }
            
            // Header
            doc.setFontSize(20);
            doc.setFont(undefined, 'bold');
            doc.text('Reports & Analytics', pageWidth - margin, yPos, { align: 'right' });
            
            yPos += 8;
            doc.setFontSize(10);
            doc.text(`Generated: ${new Date().toLocaleString()}`, pageWidth - margin, yPos, { align: 'right' });
            doc.setTextColor(0, 0, 0);
            
            // Summary Statistics Section
            yPos += 15;
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('Summary Statistics', margin, yPos);
            
            yPos += 8;
            doc.setDrawColor(200, 200, 200);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            
            yPos += 8;
            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            
            const summaryLines = [
                ['Total Revenue:', 'PHP ' + parseFloat(summaryStats.totalRevenue || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })],
                ['Total Appointments:', summaryStats.totalAppointments || '0'],
                ['Total Patients:', summaryStats.totalPatients || '0']
            ];
            
            summaryLines.forEach(([label, value]) => {
                doc.setFont(undefined, 'bold');
                doc.text(label, margin, yPos);
                doc.setFont(undefined, 'normal');
                const labelWidth = doc.getTextWidth(label);
                doc.text(value, margin + labelWidth + 5, yPos);
                yPos += 7;
            });
            
            // Table Data Section
            yPos += 10;
            if (yPos > 250) {
                doc.addPage();
                yPos = margin;
                if (logoData && logoData.startsWith('data:')) {
                    try {
                        doc.addImage(logoData, 'PNG', margin, margin, logoWidth, logoHeight);
                    } catch (e) {
                        console.warn('Could not add logo to new page:', e);
                    }
                }
            }
            
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('All Appointments', margin, yPos);
            
            yPos += 8;
            doc.setDrawColor(200, 200, 200);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            
            yPos += 10;
            
            if (tableData.length === 0) {
                doc.setFontSize(10);
                doc.setFont(undefined, 'italic');
                doc.setTextColor(150, 150, 150);
                doc.text('No appointments available.', margin, yPos);
                doc.setTextColor(0, 0, 0);
            } else {
                // Table header - calculate column positions based on page width
                const availableWidth = pageWidth - (margin * 2);
                const colWidths = {
                    patient: availableWidth * 0.25,  // 25%
                    date: availableWidth * 0.20,       // 20%
                    treatment: availableWidth * 0.25,  // 25%
                    status: availableWidth * 0.15,    // 15%
                    amount: availableWidth * 0.15      // 15%
                };
                const colPositions = {
                    patient: margin,
                    date: margin + colWidths.patient,
                    treatment: margin + colWidths.patient + colWidths.date,
                    status: margin + colWidths.patient + colWidths.date + colWidths.treatment,
                    amount: margin + colWidths.patient + colWidths.date + colWidths.treatment + colWidths.status
                };
                
                // Draw table header
                doc.setFontSize(9);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(255, 255, 255);
                doc.setFillColor(59, 130, 246); // Blue background
                doc.rect(margin, yPos - 5, pageWidth - (margin * 2), 8, 'F');
                doc.text('Patient', colPositions.patient + 2, yPos);
                doc.text('Date/Time', colPositions.date + 2, yPos);
                doc.text('Treatment', colPositions.treatment + 2, yPos);
                doc.text('Status', colPositions.status + 2, yPos);
                doc.text('Amount', colPositions.amount + 2, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 10;
                
                // Draw table rows
                doc.setFontSize(8);
                doc.setFont(undefined, 'normal');
                let rowNum = 0;
                
                tableData.forEach((row, index) => {
                    // Check if we need a new page
                    if (yPos > 270) {
                        doc.addPage();
                        yPos = margin + 10;
                        
                        // Redraw header on new page
                        doc.setFontSize(9);
                        doc.setFont(undefined, 'bold');
                        doc.setTextColor(255, 255, 255);
                        doc.setFillColor(59, 130, 246);
                        doc.rect(margin, yPos - 5, pageWidth - (margin * 2), 8, 'F');
                        doc.text('Patient', colPositions.patient + 2, yPos);
                        doc.text('Date/Time', colPositions.date + 2, yPos);
                        doc.text('Treatment', colPositions.treatment + 2, yPos);
                        doc.text('Status', colPositions.status + 2, yPos);
                        doc.text('Amount', colPositions.amount + 2, yPos);
                        doc.setTextColor(0, 0, 0);
                        yPos += 10;
                        doc.setFontSize(8);
                        doc.setFont(undefined, 'normal');
                    }
                    
                    // Alternate row background
                    if (rowNum % 2 === 0) {
                        doc.setFillColor(249, 250, 251);
                        doc.rect(margin, yPos - 4, pageWidth - (margin * 2), 6, 'F');
                    }
                    
                    // Patient name (truncate if too long to fit column)
                    let patientName = row.patientName || 'N/A';
                    const maxPatientWidth = colWidths.patient - 4;
                    if (doc.getTextWidth(patientName) > maxPatientWidth) {
                        while (doc.getTextWidth(patientName + '...') > maxPatientWidth && patientName.length > 0) {
                            patientName = patientName.substring(0, patientName.length - 1);
                        }
                        patientName += '...';
                    }
                    doc.text(patientName, colPositions.patient + 2, yPos);
                    
                    // Date and Time
                    let dateTime = `${row.date || 'N/A'} ${row.time || ''}`.trim();
                    const maxDateTimeWidth = colWidths.date - 4;
                    if (doc.getTextWidth(dateTime) > maxDateTimeWidth) {
                        while (doc.getTextWidth(dateTime + '...') > maxDateTimeWidth && dateTime.length > 0) {
                            dateTime = dateTime.substring(0, dateTime.length - 1);
                        }
                        dateTime += '...';
                    }
                    doc.text(dateTime, colPositions.date + 2, yPos);
                    
                    // Treatment (truncate if too long)
                    let treatment = row.treatment || 'N/A';
                    const maxTreatmentWidth = colWidths.treatment - 4;
                    if (doc.getTextWidth(treatment) > maxTreatmentWidth) {
                        while (doc.getTextWidth(treatment + '...') > maxTreatmentWidth && treatment.length > 0) {
                            treatment = treatment.substring(0, treatment.length - 1);
                        }
                        treatment += '...';
                    }
                    doc.text(treatment, colPositions.treatment + 2, yPos);
                    
                    // Status
                    let status = row.status || 'N/A';
                    const maxStatusWidth = colWidths.status - 4;
                    if (doc.getTextWidth(status) > maxStatusWidth) {
                        while (doc.getTextWidth(status + '...') > maxStatusWidth && status.length > 0) {
                            status = status.substring(0, status.length - 1);
                        }
                        status += '...';
                    }
                    doc.text(status, colPositions.status + 2, yPos);
                    
                    // Amount - use PHP instead of peso sign for better PDF compatibility
                    const amount = parseFloat(row.amount || 0).toFixed(2);
                    const amountText = `PHP ${parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                    doc.text(amountText, colPositions.amount + 2, yPos);
                    
                    // Draw row border
                    doc.setDrawColor(220, 220, 220);
                    doc.line(margin, yPos + 2, pageWidth - margin, yPos + 2);
                    
                    yPos += 8;
                    rowNum++;
                });
            }
            
            // Footer on all pages
            const totalPages = doc.internal.pages.length - 1;
            for (let i = 1; i <= totalPages; i++) {
                doc.setPage(i);
                doc.setFontSize(8);
                doc.setTextColor(150, 150, 150);
                doc.text(
                    `Page ${i} of ${totalPages} - DR. ROMARICO C. GONZALES Dental Clinic`,
                    pageWidth / 2,
                    doc.internal.pageSize.getHeight() - 10,
                    { align: 'center' }
                );
                doc.setTextColor(0, 0, 0);
            }
            
            // Generate PDF blob and open in new window for preview (always preview first)
            const fileName = `Reports_${new Date().toISOString().split('T')[0]}.pdf`;
            const pdfBlob = doc.output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            
            const exportReportsBtn = document.getElementById('exportReportsBtn');
            const originalHTML = exportReportsBtn ? exportReportsBtn.innerHTML : '';
            
            // Always open preview first
            const previewWindow = window.open(pdfUrl, '_blank', 'width=800,height=600');
            
            if (previewWindow) {
                if (exportReportsBtn) {
                    exportReportsBtn.innerHTML = '<span class="material-symbols-outlined">visibility</span> Previewing...';
                    exportReportsBtn.disabled = true;
                    
                    // Wait a bit for the preview to open, then offer download option
                    setTimeout(() => {
                        // Reset button state
                        exportReportsBtn.innerHTML = originalHTML;
                        exportReportsBtn.disabled = false;
                        
                        // Offer download after preview is shown
                        setTimeout(() => {
                            const userWantsDownload = confirm('PDF is now open in a new window for preview. Would you like to download it as well?');
                            
                            if (userWantsDownload) {
                                const downloadLink = document.createElement('a');
                                downloadLink.href = pdfUrl;
                                downloadLink.download = fileName;
                                downloadLink.style.display = 'none';
                                document.body.appendChild(downloadLink);
                                downloadLink.click();
                                document.body.removeChild(downloadLink);
                                
                                if (exportReportsBtn) {
                                    exportReportsBtn.innerHTML = '<span class="material-symbols-outlined">check</span> Downloaded!';
                                    exportReportsBtn.classList.add('text-green-500');
                                    
                                    setTimeout(() => {
                                        exportReportsBtn.innerHTML = originalHTML;
                                        exportReportsBtn.classList.remove('text-green-500');
                                    }, 2000);
                                }
                            }
                            
                            // Clean up URL after a delay
                            setTimeout(() => {
                                URL.revokeObjectURL(pdfUrl);
                            }, 10000);
                        }, 2000);
                    }, 500);
                }
            } else {
                // If popup was blocked, fall back to download
                alert('Popup was blocked. Downloading PDF instead...');
                doc.save(fileName);
                if (exportReportsBtn) {
                    exportReportsBtn.innerHTML = '<span class="material-symbols-outlined">check</span> Downloaded!';
                    exportReportsBtn.classList.add('text-green-500');
                    setTimeout(() => {
                        exportReportsBtn.innerHTML = originalHTML;
                        exportReportsBtn.classList.remove('text-green-500');
                    }, 2000);
                }
                URL.revokeObjectURL(pdfUrl);
            }
            
        } catch (error) {
            console.error('Error exporting PDF:', error);
            alert('Error generating PDF. Please try again.');
            const exportReportsBtn = document.getElementById('exportReportsBtn');
            if (exportReportsBtn) {
                const originalHTML = exportReportsBtn.innerHTML;
                exportReportsBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">download</span> Export Data';
            }
        }
    }
    
    // Event listener for export button
    document.getElementById('exportReportsBtn')?.addEventListener('click', exportReportsToPDF);

    // ==================== Mobile Sidebar Toggle ====================
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const mobileSidebar = document.getElementById('mobileSidebar');
    const mobileSidebarBackdrop = document.getElementById('mobileSidebarBackdrop');
    const mobileSidebarPanel = mobileSidebar?.querySelector('aside');

    function openMobileSidebar() {
        if (mobileSidebar && mobileSidebarPanel) {
            mobileSidebar.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            setTimeout(() => {
                mobileSidebarPanel.classList.remove('-translate-x-full');
            }, 10);
        }
    }

    function closeMobileSidebar() {
        if (mobileSidebar && mobileSidebarPanel) {
            mobileSidebarPanel.classList.add('-translate-x-full');
            setTimeout(() => {
                mobileSidebar.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        }
    }

    if (mobileMenuBtn) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            openMobileSidebar();
        });
    }

    if (mobileSidebarBackdrop) {
        mobileSidebarBackdrop.addEventListener('click', closeMobileSidebar);
    }

    // Close sidebar when clicking on a link
    if (mobileSidebar) {
        mobileSidebar.querySelectorAll('nav a').forEach(link => {
            link.addEventListener('click', closeMobileSidebar);
        });
    }

    // Sync mobile user data
    function syncMobileUserData() {
        const userDataStr = sessionStorage.getItem('adminUser');
        if (userDataStr) {
            try {
                const userData = JSON.parse(userDataStr);
                const mobileUserNameEl = document.getElementById('mobileUserName');
                const mobileUserRoleEl = document.getElementById('mobileUserRole');
                const mobileUserPhotoEl = document.getElementById('mobileUserPhoto');
                if (mobileUserNameEl) mobileUserNameEl.textContent = userData.name || 'Marc';
                if (mobileUserRoleEl) mobileUserRoleEl.textContent = 'Administrator';
                if (mobileUserPhotoEl && userData.photo) {
                    mobileUserPhotoEl.style.backgroundImage = `url("${userData.photo}")`;
                }
            } catch (e) {
                console.error('Error loading mobile user data:', e);
            }
        }
    }

    // Mobile logout handler
    const mobileLogoutBtn = document.getElementById('mobileLogoutBtn');
    if (mobileLogoutBtn) {
        mobileLogoutBtn.addEventListener('click', function() {
            sessionStorage.removeItem('adminUser');
            window.location.href = '<?php echo BASE_URL; ?>api/logout.php';
        });
    }

    syncMobileUserData();

    // ==================== Search Functionality ====================
    function filterAppointmentsTable() {
        const searchInput = document.getElementById('reportsSearchInput');
        const tableBody = document.getElementById('appointmentsTableBody');
        const showingCount = document.getElementById('showingCount');
        const totalCount = document.getElementById('totalCount');
        
        if (!searchInput || !tableBody) return;
        
        const searchTerm = searchInput.value.trim().toLowerCase();
        const rows = tableBody.querySelectorAll('tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            // Skip the "No appointments found" row
            if (row.querySelector('td[colspan="7"]')) {
                return;
            }
            
            // Get all text content from the row
            const patientName = row.querySelector('td:nth-child(2) p.font-semibold')?.textContent?.toLowerCase() || '';
            const patientId = row.querySelector('td:nth-child(2) p.text-xs')?.textContent?.toLowerCase() || '';
            const appointmentDate = row.querySelector('td:nth-child(3) span')?.textContent?.toLowerCase() || '';
            const appointmentTime = row.querySelector('td:nth-child(3) .text-xs')?.textContent?.toLowerCase() || '';
            const serviceType = row.querySelector('td:nth-child(4) span.font-medium')?.textContent?.toLowerCase() || '';
            const serviceDescription = row.querySelector('td:nth-child(4) p.text-xs')?.textContent?.toLowerCase() || '';
            const status = row.querySelector('td:nth-child(5) span')?.textContent?.toLowerCase() || '';
            const amount = row.querySelector('td:nth-child(6) span')?.textContent?.toLowerCase() || '';
            
            // Combine all searchable text
            const searchableText = `${patientName} ${patientId} ${appointmentDate} ${appointmentTime} ${serviceType} ${serviceDescription} ${status} ${amount}`;
            
            // Check if search term matches
            if (!searchTerm || searchableText.includes(searchTerm)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Update count display
        if (showingCount) {
            showingCount.textContent = visibleCount;
        }
        
        // Show "No results" message if needed
        let noResultsRow = tableBody.querySelector('tr.no-results-row');
        if (searchTerm && visibleCount === 0) {
            if (!noResultsRow) {
                noResultsRow = document.createElement('tr');
                noResultsRow.className = 'no-results-row';
                noResultsRow.innerHTML = `
                    <td colspan="7" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">
                        <span class="material-symbols-outlined text-4xl mb-2 block">search_off</span>
                        <p>No appointments found matching "${searchTerm}"</p>
                    </td>
                `;
                tableBody.appendChild(noResultsRow);
            }
        } else {
            if (noResultsRow) {
                noResultsRow.remove();
            }
        }
    }
    
    // Add event listeners to both search inputs
    const reportsSearchInput = document.getElementById('reportsSearchInput');
    const headerSearchInput = document.getElementById('headerSearchInput');
    
    // Function to sync search inputs
    function syncSearchInputs() {
        if (reportsSearchInput && headerSearchInput) {
            reportsSearchInput.addEventListener('input', function() {
                headerSearchInput.value = reportsSearchInput.value;
                filterAppointmentsTable();
            });
            
            headerSearchInput.addEventListener('input', function() {
                reportsSearchInput.value = headerSearchInput.value;
                filterAppointmentsTable();
            });
        } else if (reportsSearchInput) {
            reportsSearchInput.addEventListener('input', filterAppointmentsTable);
        } else if (headerSearchInput) {
            headerSearchInput.addEventListener('input', filterAppointmentsTable);
        }
    }
    
    syncSearchInputs();
</script>