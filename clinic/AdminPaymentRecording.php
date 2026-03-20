<?php
/**
 * Admin Payment Recording Page
 * Requires admin authentication
 */
$pageTitle = 'Payment Recording - DentalPro Admin';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // Require admin role
require_once __DIR__ . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-8 sticky top-0 z-10 shrink-0">
<div>
<h1 class="text-2xl font-bold">Payment Recording</h1>
<p class="text-sm text-slate-500 dark:text-slate-400">Manage daily collections and verify transaction records.</p>
</div>
</header>
<div class="flex-1 overflow-y-auto p-8">
<div class="max-w-[1600px] mx-auto flex flex-col gap-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
<div class="relative overflow-hidden rounded-2xl p-5 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark shadow-soft group">
<div class="absolute right-0 top-0 h-full w-24 bg-gradient-to-l from-primary/5 to-transparent pointer-events-none"></div>
<div class="flex justify-between items-start mb-4">
<div class="p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-primary">
<span class="material-symbols-outlined filled">account_balance_wallet</span>
</div>
                    <span class="text-emerald-600 dark:text-emerald-400 text-xs font-bold bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-full flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span>
                                    </span>
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-text-sub text-sm font-medium">Total Revenue</p>
                    <h3 class="text-text-main dark:text-white text-2xl font-bold tracking-tight"></h3>
                </div>
            </div>
            <div class="relative overflow-hidden rounded-2xl p-5 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark shadow-soft group">
<div class="absolute right-0 top-0 h-full w-24 bg-gradient-to-l from-purple-500/5 to-transparent pointer-events-none"></div>
<div class="flex justify-between items-start mb-4">
<div class="p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg text-purple-600 dark:text-purple-400">
<span class="material-symbols-outlined filled">payments</span>
</div>
                    <span class="text-text-sub text-xs font-medium bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded-full">
                                    </span>
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-text-sub text-sm font-medium">Today's Revenue</p>
                    <h3 class="text-text-main dark:text-white text-2xl font-bold tracking-tight"></h3>
</div>
</div>
<div class="relative overflow-hidden rounded-2xl p-5 bg-white dark:bg-surface-dark border border-border-light dark:border-border-dark shadow-soft group">
<div class="absolute right-0 top-0 h-full w-24 bg-gradient-to-l from-teal-500/5 to-transparent pointer-events-none"></div>
<div class="flex justify-between items-start mb-4">
<div class="p-2 bg-teal-50 dark:bg-teal-900/20 rounded-lg text-teal-600 dark:text-teal-400">
<span class="material-symbols-outlined filled">qr_code_scanner</span>
</div>
                    <span class="text-emerald-600 dark:text-emerald-400 text-xs font-bold bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-full flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">trending_up</span>
                                    </span>
                </div>
                <div class="flex flex-col gap-1">
                    <p class="text-text-sub text-sm font-medium">Total Payments</p>
                    <h3 class="text-text-main dark:text-white text-2xl font-bold tracking-tight"></h3>
</div>
</div>
</div>
</div>
<!-- Separator -->
<div class="border-t border-slate-200 dark:border-slate-700 my-6"></div>
<div class="flex flex-col gap-4">
<div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark shadow-sm overflow-hidden">
<div class="px-6 py-5 border-b border-border-light dark:border-border-dark flex items-center justify-between bg-white dark:bg-surface-dark">
<h2 class="text-lg font-bold text-text-main dark:text-white flex items-center gap-2">
<span class="flex items-center justify-center size-8 rounded-full bg-primary/10 text-primary">
<span class="material-symbols-outlined text-[18px]">add</span>
</span>
                                        New Transaction
                                    </h2>
<div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></div>
</div>
<form class="p-6 flex flex-col gap-6">
<div class="flex flex-col gap-2">
<label class="text-text-main dark:text-slate-200 text-xs uppercase font-bold tracking-wider">Patient Details</label>
<div class="relative group">
<div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
<span class="material-symbols-outlined text-text-sub text-[20px]">person_search</span>
</div>
<input id="patientSearchInput" class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-text-main dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all text-sm font-medium" type="text"/>
<div id="patientDropdown" class="hidden absolute z-50 w-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg max-h-60 overflow-y-auto">
<!-- Patient dropdown items will be populated here -->
</div>
</div>
</div>
<div id="transactionDetailsSection" class="flex flex-col gap-3 hidden">
<label class="text-text-main dark:text-slate-200 text-xs uppercase font-bold tracking-wider">Transaction Details</label>
<div class="p-4 rounded-xl bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700">
<div class="flex flex-col gap-3">
<div class="flex items-start gap-3">
<div class="p-2 bg-primary/10 rounded-lg">
<span class="material-symbols-outlined text-primary text-[20px]">receipt_long</span>
</div>
<div class="flex-1 min-w-0">
<div id="selectedPatientName" class="font-bold text-text-main dark:text-white text-sm mb-1"></div>
<div id="selectedTreatmentInfo" class="flex flex-col gap-1 text-xs text-text-sub">
<div id="treatmentIdDisplay" class="flex items-center gap-1.5">
<span class="material-symbols-outlined text-[14px]">tag</span>
<span id="treatmentIdValue" class="font-mono font-semibold text-primary"></span>
</div>
<div id="treatmentNameDisplay" class="flex items-center gap-1.5">
<span class="material-symbols-outlined text-[14px]">medical_services</span>
<span id="treatmentNameValue"></span>
</div>
<div id="appointmentDateDisplay" class="flex items-center gap-1.5">
<span class="material-symbols-outlined text-[14px]">calendar_today</span>
<span id="appointmentDateValue"></span>
</div>
<div id="doctorDisplay" class="flex items-center gap-1.5">
<span class="material-symbols-outlined text-[14px]">stethoscope</span>
<span id="doctorValue"></span>
</div>
</div>
</div>
</div>
</div>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label class="text-text-main dark:text-slate-200 text-xs uppercase font-bold tracking-wider">Amount</label>
<div class="relative">
<div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none z-10">
<span class="text-text-sub font-bold">₱</span>
</div>
<input id="amountInput" class="w-full pl-8 pr-4 py-3.5 rounded-xl bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-text-main dark:text-white font-mono font-bold text-lg placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="number" step="0.01" min="0"/>
</div>
</div>
<div class="flex flex-col gap-2">
<label class="text-text-main dark:text-slate-200 text-xs uppercase font-bold tracking-wider">Date</label>
<div class="relative">
<input class="w-full pl-4 pr-4 py-3.5 rounded-xl bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-text-main dark:text-white text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" type="date" value="<?php echo date('Y-m-d'); ?>"/>
</div>
</div>
</div>
<div class="flex flex-col gap-3">
<label class="text-text-main dark:text-slate-200 text-xs uppercase font-bold tracking-wider">Payment Method</label>
<div class="grid grid-cols-3 gap-3">
<label class="cursor-pointer group">
<input checked="" class="peer sr-only" name="method" type="radio" value="cash" id="methodCash"/>
<div class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/30 text-text-sub peer-checked:bg-emerald-50 peer-checked:dark:bg-emerald-900/20 peer-checked:border-emerald-500 peer-checked:text-emerald-700 peer-checked:dark:text-emerald-400 transition-all h-full hover:border-slate-300 dark:hover:border-slate-600">
<div class="absolute top-2 right-2 opacity-0 peer-checked:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-[18px] filled text-emerald-500">check_circle</span>
</div>
<span class="material-symbols-outlined mb-2 text-[28px]">payments</span>
<span class="text-xs font-bold">Cash</span>
</div>
</label>
<label class="cursor-pointer group">
<input class="peer sr-only" name="method" type="radio" value="bank" id="methodBank"/>
<div class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/30 text-text-sub peer-checked:bg-blue-50 peer-checked:dark:bg-blue-900/20 peer-checked:border-primary peer-checked:text-primary transition-all h-full hover:border-slate-300 dark:hover:border-slate-600">
<div class="absolute top-2 right-2 opacity-0 peer-checked:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-[18px] filled text-primary">check_circle</span>
</div>
<span class="material-symbols-outlined mb-2 text-[28px]">account_balance</span>
<span class="text-xs font-bold">Bank</span>
</div>
</label>
<label class="cursor-pointer group">
<input class="peer sr-only" name="method" type="radio" value="gcash" id="methodGCash"/>
<div class="relative flex flex-col items-center justify-center p-4 rounded-xl border-2 border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/30 text-text-sub peer-checked:bg-blue-50 peer-checked:dark:bg-blue-900/20 peer-checked:border-blue-500 peer-checked:text-blue-600 peer-checked:dark:text-blue-400 transition-all h-full hover:border-slate-300 dark:hover:border-slate-600">
<div class="absolute top-2 right-2 opacity-0 peer-checked:opacity-100 transition-opacity">
<span class="material-symbols-outlined text-[18px] filled text-blue-500">check_circle</span>
</div>
<span class="material-symbols-outlined mb-2 text-[28px]">qr_code_2</span>
<span class="text-xs font-bold">GCash</span>
</div>
</label>
</div>
</div>
<div class="flex flex-col gap-2">
<label class="text-text-main dark:text-slate-200 text-xs uppercase font-bold tracking-wider flex justify-between">
                                            Reference No.
                                            <span class="text-text-sub font-normal normal-case text-xs">(Auto-disabled for Cash)</span>
</label>
<input id="referenceNoInput" class="w-full px-4 py-3.5 rounded-xl bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-text-main dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all disabled:opacity-50 disabled:cursor-not-allowed text-sm" disabled="" type="text"/>
</div>
<div class="flex flex-col gap-2">
<label class="text-text-main dark:text-slate-200 text-xs uppercase font-bold tracking-wider">Notes</label>
<textarea class="w-full px-4 py-3.5 rounded-xl bg-white dark:bg-slate-800/50 border border-slate-200 dark:border-slate-700 text-text-main dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all resize-none text-sm" rows="2"></textarea>
</div>
<button class="mt-2 w-full bg-primary hover:bg-primary-dark text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/30 hover:shadow-primary/40 active:scale-[0.99] transition-all flex justify-center items-center gap-2 group" type="button">
<span class="material-symbols-outlined group-hover:scale-110 transition-transform filled">check_circle</span>
                                        Confirm Payment
                                    </button>
</form>
</div>
</div>
<!-- Separator -->
<div class="border-t border-slate-200 dark:border-slate-700 my-6"></div>
<div class="flex flex-col gap-4 h-full">
<div class="bg-white dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark shadow-sm flex flex-col h-full min-h-[700px]">
<div class="px-6 py-5 border-b border-border-light dark:border-border-dark flex flex-wrap gap-4 justify-between items-center bg-white dark:bg-surface-dark rounded-t-2xl">
<h2 class="text-lg font-bold text-text-main dark:text-white">Recent Transactions</h2>
<div class="flex items-center gap-3">
<div class="relative">
<span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input class="pl-10 pr-4 py-2 w-64 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all text-sm" placeholder="Search payments..." type="text"/>
</div>
<button id="exportReportBtn" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-full font-semibold flex items-center gap-2 transition-all shadow-lg shadow-primary/20">
<span class="material-icons-outlined">download</span>
                    Export Data
                </button>
</div>
</div>
<div class="flex-1 overflow-x-auto">
<table class="w-full text-left border-collapse">
                <thead class="bg-white dark:bg-slate-800/50 border-b border-border-light dark:border-border-dark sticky top-0 z-10">
                <tr>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider">Payment ID</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider">Patient Name</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider">Service</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider">Appointment ID</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider">Payment Date</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider">Time</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider">Payment Method</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider">Reference No.</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider text-right">Amount</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider text-center">Status</th>
                <th class="px-6 py-4 text-xs font-bold text-text-sub uppercase tracking-wider text-center">Action</th>
                </tr>
                </thead>
<tbody id="transactionsBody" class="divide-y divide-border-light dark:divide-border-dark bg-white dark:bg-surface-dark">
<!-- Transactions will be populated here dynamically -->
</tbody>
</table>
</div>
<div class="px-6 py-4 border-t border-border-light dark:border-border-dark flex items-center justify-between bg-white dark:bg-surface-dark rounded-b-2xl">
<p id="transactionsCount" class="text-xs text-text-sub font-medium">No transactions</p>
<div class="flex gap-2">
<button class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main dark:text-white hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50 transition-colors">Previous</button>
<button class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-border-light dark:border-border-dark bg-white dark:bg-slate-800 text-text-main dark:text-white hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Next</button>
</div>
</div>
</div>
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

    // Patient/treatment data loaded from API
    let patientTreatmentData = [];
    let selectedTreatmentData = null;

    // Load appointments and filter unpaid bookings
    async function loadUnpaidBookings() {
        try {
            // Load appointments (API already includes pending_balance calculation)
            const appointmentsResponse = await fetch('<?php echo BASE_URL; ?>api/appointments.php', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            const appointmentsData = await appointmentsResponse.json();
            
            if (appointmentsData.success && appointmentsData.data && appointmentsData.data.appointments) {
                const appointments = appointmentsData.data.appointments;
                
                // Filter to show only bookings that still need payment
                // A booking needs payment if:
                // 1. It has a pending_balance > 0, OR
                // 2. It has no payments at all (pending_balance might be null/0 but no payments exist)
                // Exclude cancelled bookings
                const unpaidAppointments = appointments.filter(apt => {
                    // Exclude cancelled bookings
                    if (apt.status === 'cancelled' || apt.status === 'no_show') {
                        return false;
                    }
                    
                    // Check if booking has pending balance
                    const pendingBalance = parseFloat(apt.pending_balance || 0);
                    const totalCost = parseFloat(apt.total_cost || apt.total_treatment_cost || 0);
                    const totalPaid = parseFloat(apt.total_paid || 0);
                    
                    // Include if there's a pending balance or if total cost exists but no payment made
                    if (pendingBalance > 0) {
                        return true;
                    }
                    // Include if there's a total cost but no payment has been made
                    if (totalCost > 0 && totalPaid === 0) {
                        return true;
                    }
                    // Exclude if fully paid or no cost
                    return false;
                });
                
                // Transform appointments to patientTreatmentData format
                patientTreatmentData = unpaidAppointments.map(appointment => {
                    const appointmentDate = new Date(appointment.appointment_date);
                    const formattedDate = appointmentDate.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'short', 
                        day: 'numeric' 
                    });
                    
                    // Get patient name from first_name and last_name
                    const patientName = appointment.first_name && appointment.last_name
                        ? `${appointment.first_name} ${appointment.last_name}`
                        : (appointment.patient_first_name && appointment.patient_last_name
                            ? `${appointment.patient_first_name} ${appointment.patient_last_name}`
                            : 'Unknown Patient');
                    
                    // Get doctor name (if available)
                    const doctorName = appointment.dentist_name || appointment.doctor_name || 'N/A';
                    
                    return {
                        patientName: patientName,
                        bookingId: appointment.booking_id,
                        treatment: appointment.service_type || appointment.service_description || 'N/A',
                        doctor: doctorName,
                        appointmentDate: formattedDate,
                        appointmentTime: appointment.appointment_time || '',
                        status: appointment.status || 'pending',
                        patientId: appointment.patient_id,
                        appointmentId: appointment.id,
                        pendingBalance: parseFloat(appointment.pending_balance || 0),
                        totalCost: parseFloat(appointment.total_cost || appointment.total_treatment_cost || 0),
                        totalPaid: parseFloat(appointment.total_paid || 0)
                    };
                });
            }
        } catch (error) {
            console.error('Error loading unpaid bookings:', error);
            patientTreatmentData = [];
        }
    }

    // Patient search functionality
    const patientSearchInput = document.getElementById('patientSearchInput');
    const patientDropdown = document.getElementById('patientDropdown');
    const transactionDetailsSection = document.getElementById('transactionDetailsSection');
    const selectedPatientName = document.getElementById('selectedPatientName');
    const treatmentIdValue = document.getElementById('treatmentIdValue');
    const treatmentNameValue = document.getElementById('treatmentNameValue');
    const appointmentDateValue = document.getElementById('appointmentDateValue');
    const doctorValue = document.getElementById('doctorValue');

    function filterPatients(searchTerm) {
        if (!searchTerm || searchTerm.trim().length < 1) {
            patientDropdown.classList.add('hidden');
            return;
        }
        
        const searchLower = searchTerm.toLowerCase().trim();
        const filtered = patientTreatmentData.filter(record => 
            record.patientName.toLowerCase().includes(searchLower) ||
            record.bookingId.toLowerCase().includes(searchLower) ||
            record.treatment.toLowerCase().includes(searchLower)
        );
        
        if (filtered.length > 0) {
            patientDropdown.innerHTML = filtered.map(record => `
                <div class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer border-b border-slate-100 dark:border-slate-700 last:border-b-0 select-treatment-item" data-booking-id="${record.bookingId}">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-primary text-[20px]">person</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-text-main dark:text-white text-sm truncate">${record.patientName}</p>
                            <p class="text-xs text-text-sub font-mono mt-0.5">${record.bookingId}</p>
                            <p class="text-xs text-text-sub mt-0.5">${record.treatment}</p>
                        </div>
                    </div>
                </div>
            `).join('');
            
            patientDropdown.classList.remove('hidden');
            
            // Add click handlers to patient items
            patientDropdown.querySelectorAll('.select-treatment-item').forEach(item => {
                item.addEventListener('click', () => {
                    const bookingId = item.dataset.bookingId;
                    const record = patientTreatmentData.find(r => r.bookingId === bookingId);
                    if (record) {
                        selectTreatment(record);
                    }
                });
            });
        } else {
            patientDropdown.innerHTML = '<div class="p-4 text-center text-text-sub text-sm">No records found</div>';
            patientDropdown.classList.remove('hidden');
        }
    }

    async function selectTreatment(record) {
        selectedTreatmentData = record;
        if (patientSearchInput) patientSearchInput.value = `${record.patientName} - ${record.bookingId}`;
        if (patientDropdown) patientDropdown.classList.add('hidden');
        
        // Populate transaction details section
        if (selectedPatientName) selectedPatientName.textContent = record.patientName;
        if (treatmentIdValue) treatmentIdValue.textContent = record.bookingId;
        if (treatmentNameValue) treatmentNameValue.textContent = record.treatment;
        const dateTime = record.appointmentTime ? `${record.appointmentDate} ${record.appointmentTime}` : record.appointmentDate;
        if (appointmentDateValue) appointmentDateValue.textContent = dateTime;
        if (doctorValue) doctorValue.textContent = record.doctor;
        
        // Fetch latest appointment details to get accurate balance
        const amountInput = document.getElementById('amountInput');
        try {
            const response = await fetch(`<?php echo BASE_URL; ?>api/appointments.php?booking_id=${encodeURIComponent(record.bookingId)}`, {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            const data = await response.json();
            if (data.success && data.data && data.data.appointments && data.data.appointments.length > 0) {
                const appointment = data.data.appointments[0];
                const pendingBalance = parseFloat(appointment.pending_balance || 0);
                const totalCost = parseFloat(appointment.total_cost || appointment.total_treatment_cost || 0);
                const totalPaid = parseFloat(appointment.total_paid || 0);
                
                // Calculate the actual pending balance
                let balanceToUse = pendingBalance;
                if (balanceToUse <= 0 && totalCost > 0) {
                    balanceToUse = totalCost - totalPaid;
                }
                
                // Update the record with latest values
                record.pendingBalance = balanceToUse;
                record.totalCost = totalCost;
                record.totalPaid = totalPaid;
                
                // Auto-fill amount with pending balance
                if (amountInput) {
                    if (balanceToUse > 0) {
                        amountInput.value = balanceToUse.toFixed(2);
                    } else if (totalCost > 0) {
                        // If no pending balance but there's a total cost, use total cost
                        amountInput.value = totalCost.toFixed(2);
                    } else {
                        amountInput.value = '';
                    }
                }
            } else {
                // Fallback to stored values if API call fails
                if (amountInput && record.pendingBalance !== undefined) {
                    const pendingBalance = record.pendingBalance > 0 
                        ? record.pendingBalance 
                        : (record.totalCost || 0) - (record.totalPaid || 0);
                    
                    if (pendingBalance > 0) {
                        amountInput.value = pendingBalance.toFixed(2);
                    } else {
                        const totalCost = record.totalCost || 0;
                        if (totalCost > 0) {
                            amountInput.value = totalCost.toFixed(2);
                        } else {
                            amountInput.value = '';
                        }
                    }
                }
            }
        } catch (error) {
            console.error('Error fetching appointment balance:', error);
            // Fallback to stored values
            if (amountInput && record.pendingBalance !== undefined) {
                const pendingBalance = record.pendingBalance > 0 
                    ? record.pendingBalance 
                    : (record.totalCost || 0) - (record.totalPaid || 0);
                
                if (pendingBalance > 0) {
                    amountInput.value = pendingBalance.toFixed(2);
                } else {
                    const totalCost = record.totalCost || 0;
                    if (totalCost > 0) {
                        amountInput.value = totalCost.toFixed(2);
                    } else {
                        amountInput.value = '';
                    }
                }
            }
        }
        
        // Show transaction details section
        if (transactionDetailsSection) transactionDetailsSection.classList.remove('hidden');
    }

    function clearSelection() {
        selectedTreatmentData = null;
        if (patientSearchInput) patientSearchInput.value = '';
        if (patientDropdown) patientDropdown.classList.add('hidden');
        if (transactionDetailsSection) transactionDetailsSection.classList.add('hidden');
    }

    // Event listeners
    if (patientSearchInput) {
        patientSearchInput.addEventListener('input', (e) => {
            filterPatients(e.target.value);
        });
        
        patientSearchInput.addEventListener('focus', (e) => {
            if (e.target.value.trim()) {
                filterPatients(e.target.value);
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (patientDropdown && !patientSearchInput.contains(e.target) && !patientDropdown.contains(e.target)) {
                patientDropdown.classList.add('hidden');
            }
        });
    }

    // Payment method change handler for Reference No. field
    const referenceNoInput = document.getElementById('referenceNoInput');
    const paymentMethodRadios = document.querySelectorAll('input[name="method"]');
    
    function handlePaymentMethodChange() {
        const selectedMethod = document.querySelector('input[name="method"]:checked');
        if (referenceNoInput && selectedMethod) {
            if (selectedMethod.value === 'cash') {
                referenceNoInput.disabled = true;
            } else {
                // Enable for Bank and GCash
                referenceNoInput.disabled = false;
            }
        }
    }

    // Add event listeners to payment method radio buttons
    if (paymentMethodRadios.length > 0) {
        paymentMethodRadios.forEach(radio => {
            radio.addEventListener('change', handlePaymentMethodChange);
        });
        
        // Initialize on page load (Cash is checked by default)
        handlePaymentMethodChange();
    }

    // Store all transactions from database
    let allTransactions = [];
    let currentPage = 1;
    const transactionsPerPage = 20;
    let totalTransactions = 0;
    let totalPages = 1;

    // Fetch payments from database
    async function fetchPayments(page = 1, limit = 100) {
        try {
            const response = await fetch(`<?php echo BASE_URL; ?>api/payments.php?page=${page}&limit=${limit}`, {
                credentials: 'same-origin'
            });
            const result = await response.json();
            
            if (result.success && result.data && result.data.payments) {
                // Transform API data to match table structure
                allTransactions = result.data.payments.map(payment => {
                    // Handle payment_date - could be datetime or date string
                    let paymentDate = 'N/A';
                    let paymentTime = 'N/A';
                    if (payment.payment_date) {
                        if (payment.payment_date.includes(' ')) {
                            const dateTimeParts = payment.payment_date.split(' ');
                            paymentDate = dateTimeParts[0];
                            paymentTime = dateTimeParts[1] || 'N/A';
                        } else {
                            paymentDate = payment.payment_date;
                        }
                    }
                    
                    return {
                        id: payment.id,
                        paymentId: payment.payment_id || 'N/A',
                        patientName: `${payment.patient_first_name || ''} ${payment.patient_last_name || ''}`.trim() || 'N/A',
                        service: payment.service_type || 'N/A',
                        bookingId: payment.booking_id || 'N/A',
                        date: paymentDate,
                        time: paymentTime,
                        method: formatPaymentMethod(payment.payment_method),
                        refNo: payment.reference_number || '-',
                        amount: parseFloat(payment.amount || 0),
                        status: payment.status || 'completed'
                    };
                });
                
                totalTransactions = result.data.pagination?.total || allTransactions.length;
                totalPages = result.data.pagination?.pages || 1;
                currentPage = result.data.pagination?.page || 1;
                
                // Sort by date (newest first)
                allTransactions.sort((a, b) => {
                    const dateA = new Date(a.date);
                    const dateB = new Date(b.date);
                    return dateB - dateA;
                });
                
                return allTransactions;
            } else {
                console.error('Failed to fetch payments:', result.message || 'Unknown error');
                allTransactions = [];
                return [];
            }
        } catch (error) {
            console.error('Error fetching payments:', error);
            allTransactions = [];
            return [];
        }
    }

    // Format payment method for display
    function formatPaymentMethod(method) {
        if (!method) return 'Cash';
        const methodMap = {
            'cash': 'Cash',
            'credit_card': 'Credit Card',
            'debit_card': 'Debit Card',
            'gcash': 'GCash',
            'paymaya': 'PayMaya',
            'bank_transfer': 'Bank Transfer',
            'check': 'Check'
        };
        return methodMap[method.toLowerCase()] || method.charAt(0).toUpperCase() + method.slice(1);
    }

    // Format status for display
    function formatStatus(status) {
        if (!status) return 'Completed';
        const statusMap = {
            'pending': 'Pending',
            'completed': 'Completed',
            'refunded': 'Refunded',
            'cancelled': 'Cancelled'
        };
        return statusMap[status.toLowerCase()] || status.charAt(0).toUpperCase() + status.slice(1);
    }

    function getFilteredTransactions() {
        const searchInput = document.querySelector('input[placeholder="Search payments..."]');
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        
        if (!searchTerm) {
            return allTransactions;
        }
        
        return allTransactions.filter(transaction => 
            transaction.patientName.toLowerCase().includes(searchTerm) ||
            transaction.service.toLowerCase().includes(searchTerm) ||
            transaction.bookingId.toLowerCase().includes(searchTerm) ||
            transaction.refNo.toLowerCase().includes(searchTerm) ||
            transaction.method.toLowerCase().includes(searchTerm) ||
            (transaction.paymentId && transaction.paymentId.toLowerCase().includes(searchTerm))
        );
    }

    function calculateEarnings(transactions) {
        const total = transactions.reduce((sum, t) => sum + t.amount, 0);
        const cash = transactions.filter(t => t.method === 'Cash').reduce((sum, t) => sum + t.amount, 0);
        const digital = transactions.filter(t => t.method === 'GCash' || t.method === 'Bank Transfer' || t.method === 'Bank').reduce((sum, t) => sum + t.amount, 0);
        
        return { total, cash, digital };
    }

    // Render transactions table
    function renderTransactionsTable() {
        const tbody = document.getElementById('transactionsBody');
        const countEl = document.getElementById('transactionsCount');
        const filteredTransactions = getFilteredTransactions();
        
        if (!tbody) return;
        
        if (filteredTransactions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="11" class="px-6 py-12 text-center text-text-sub">
                        <div class="flex flex-col items-center gap-2">
                            <span class="material-symbols-outlined text-4xl text-slate-400">receipt_long</span>
                            <p class="text-sm font-medium">No transactions found</p>
                        </div>
                    </td>
                </tr>
            `;
            if (countEl) {
                countEl.textContent = 'No transactions';
            }
            return;
        }
        
        tbody.innerHTML = filteredTransactions.map(transaction => {
            const statusClass = transaction.status === 'completed' 
                ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400'
                : transaction.status === 'pending'
                ? 'bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400'
                : transaction.status === 'refunded'
                ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400'
                : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400';
            
            // Format time for display (HH:MM:SS to HH:MM)
            const formattedTime = transaction.time && transaction.time !== 'N/A' 
                ? transaction.time.substring(0, 5) 
                : 'N/A';
            
            return `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                    <td class="px-6 py-4 text-sm text-text-sub font-mono font-semibold">
                        ${transaction.paymentId}
                    </td>
                    <td class="px-6 py-4 text-sm font-medium text-text-main dark:text-white">
                        ${transaction.patientName}
                    </td>
                    <td class="px-6 py-4 text-sm text-text-sub">
                        ${transaction.service}
                    </td>
                    <td class="px-6 py-4 text-sm text-text-sub font-mono">
                        ${transaction.bookingId}
                    </td>
                    <td class="px-6 py-4 text-sm text-text-sub">
                        ${formatDate(transaction.date)}
                    </td>
                    <td class="px-6 py-4 text-sm text-text-sub font-mono">
                        ${formattedTime}
                    </td>
                    <td class="px-6 py-4 text-sm text-text-sub">
                        ${transaction.method}
                    </td>
                    <td class="px-6 py-4 text-sm text-text-sub font-mono">
                        ${transaction.refNo}
                    </td>
                    <td class="px-6 py-4 text-sm font-bold text-text-main dark:text-white text-right">
                        ₱ ${transaction.amount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${statusClass}">
                            ${formatStatus(transaction.status)}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <a href="InvoicePayment.php?id=${transaction.id}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary hover:bg-primary-dark text-white text-xs font-semibold rounded-lg transition-colors shadow-sm hover:shadow-md">
                            <span class="material-symbols-outlined text-[16px]">receipt</span>
                            Invoice
                        </a>
                    </td>
                </tr>
            `;
        }).join('');
        
        if (countEl) {
            countEl.textContent = `${filteredTransactions.length} transaction${filteredTransactions.length !== 1 ? 's' : ''}`;
        }
    }

    // Format date for display
    function formatDate(dateString) {
        if (!dateString || dateString === 'N/A') return 'N/A';
        try {
            const date = new Date(dateString);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        } catch (e) {
            return dateString;
        }
    }

    async function updateStatCards() {
        try {
            // Fetch statistics from API
            const response = await fetch('<?php echo BASE_URL; ?>api/payments.php?stats=true', {
                credentials: 'same-origin'
            });
            const result = await response.json();
            
            if (result.success && result.data) {
                const stats = result.data;
                
                // Update Total Revenue (first card)
                const totalRevenueEl = document.querySelectorAll('.grid .relative h3')[0];
                if (totalRevenueEl) {
                    totalRevenueEl.textContent = `₱ ${parseFloat(stats.total_revenue || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
                }
                
                // Update Today's Revenue (second card)
                const todayRevenueEl = document.querySelectorAll('.grid .relative h3')[1];
                if (todayRevenueEl) {
                    todayRevenueEl.textContent = `₱ ${parseFloat(stats.today_revenue || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}`;
                }
                
                // Update Total Payments (third card) - show count, not amount
                const totalPaymentsEl = document.querySelectorAll('.grid .relative h3')[2];
                if (totalPaymentsEl) {
                    totalPaymentsEl.textContent = `${parseInt(stats.total_payments || 0).toLocaleString('en-US')}`;
                }
            } else {
                console.error('Failed to fetch statistics:', result.message || 'Unknown error');
                // Set default values on error
                const cards = document.querySelectorAll('.grid .relative h3');
                cards.forEach(card => {
                    if (!card.textContent.trim()) {
                        card.textContent = '₱ 0';
                    }
                });
            }
        } catch (error) {
            console.error('Error fetching statistics:', error);
            // Set default values on error
            const cards = document.querySelectorAll('.grid .relative h3');
            cards.forEach(card => {
                if (!card.textContent.trim()) {
                    card.textContent = '₱ 0';
                }
            });
        }
    }


    // Convert image to base64
    function getImageAsBase64(url) {
        return new Promise((resolve) => {
            // Try fetch first (works for local files in some contexts)
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
                        // Fallback to canvas method
                        tryCanvasMethod();
                    };
                    reader.readAsDataURL(blob);
                })
                .catch(() => {
                    // Fallback to canvas method
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
                // Try with crossOrigin for external images
                img.crossOrigin = 'Anonymous';
                img.src = url;
            }
        });
    }
    
    // Format date for PDF display
    function formatDateForPDF(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
            const months = ['January', 'February', 'March', 'April', 'May', 'June', 
                          'July', 'August', 'September', 'October', 'November', 'December'];
            const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            const dayName = dayNames[date.getDay()];
            return `${dayName}, ${months[date.getMonth()]} ${date.getDate()}, ${date.getFullYear()}`;
        } catch (e) {
            return dateString;
        }
    }
    
    // Format filter period for display
    function formatFilterPeriod() {
        return 'All Transactions';
    }
    
    // Export Report functionality
    async function exportPaymentReportToPDF() {
        try {
            // Ensure we have the latest data
            if (allTransactions.length === 0) {
                await fetchPayments();
            }
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            const filteredTransactions = getFilteredTransactions();
            const earnings = calculateEarnings(filteredTransactions);
            
            if (filteredTransactions.length === 0) {
                alert('No transactions found.');
                return;
            }
            
            // Load logo - try multiple paths
            let logoData = null;
            const logoPaths = [
                'DRCGLogo2.png',
                './DRCGLogo2.png',
                '../DRCGLogo2.png'
            ];
            
            // First try to get from existing DOM image
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
            
            // Try different paths if not found
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
                    // Get image dimensions to maintain aspect ratio
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
            doc.text('Payment Report', pageWidth - margin, yPos, { align: 'right' });
            
            yPos += 8;
            doc.setFontSize(10);
            doc.text(`Generated: ${new Date().toLocaleString()}`, pageWidth - margin, yPos, { align: 'right' });
            doc.setTextColor(0, 0, 0);
            
            // Summary Section
            yPos += 15;
            doc.setFontSize(16);
            doc.setFont(undefined, 'bold');
            doc.text('Summary', margin, yPos);
            
            yPos += 8;
            doc.setDrawColor(200, 200, 200);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            
            yPos += 8;
            doc.setFontSize(11);
            doc.setFont(undefined, 'normal');
            
            const summaryLines = [
                ['Total Earnings:', 'PHP ' + earnings.total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })],
                ['Cash Payments:', 'PHP ' + earnings.cash.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })],
                ['Digital Payments:', 'PHP ' + earnings.digital.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })]
            ];
            
            summaryLines.forEach(([label, value]) => {
                doc.setFont(undefined, 'bold');
                doc.text(label, margin, yPos);
                doc.setFont(undefined, 'normal');
                const labelWidth = doc.getTextWidth(label);
                doc.text(value, margin + labelWidth + 5, yPos);
                yPos += 7;
            });
            
            // Transactions Section - Table Format
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
            doc.text('All Transactions', margin, yPos);
            
            yPos += 8;
            doc.setDrawColor(200, 200, 200);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            
            yPos += 10;
            
            if (filteredTransactions.length === 0) {
                doc.setFontSize(10);
                doc.setFont(undefined, 'italic');
                doc.setTextColor(150, 150, 150);
                doc.text('No transactions available.', margin, yPos);
                doc.setTextColor(0, 0, 0);
            } else {
                // Table header - calculate column positions based on page width
                const availableWidth = pageWidth - (margin * 2);
                const colWidths = {
                    date: availableWidth * 0.15,      // 15%
                    patient: availableWidth * 0.25,     // 25%
                    method: availableWidth * 0.15,     // 15%
                    reference: availableWidth * 0.15,  // 15%
                    status: availableWidth * 0.10,     // 10%
                    amount: availableWidth * 0.20      // 20%
                };
                const colPositions = {
                    date: margin,
                    patient: margin + colWidths.date,
                    method: margin + colWidths.date + colWidths.patient,
                    reference: margin + colWidths.date + colWidths.patient + colWidths.method,
                    status: margin + colWidths.date + colWidths.patient + colWidths.method + colWidths.reference,
                    amount: margin + colWidths.date + colWidths.patient + colWidths.method + colWidths.reference + colWidths.status
                };
                
                // Draw table header
                doc.setFontSize(9);
                doc.setFont(undefined, 'bold');
                doc.setTextColor(255, 255, 255);
                doc.setFillColor(59, 130, 246); // Blue background
                doc.rect(margin, yPos - 5, pageWidth - (margin * 2), 8, 'F');
                doc.text('Date', colPositions.date + 2, yPos);
                doc.text('Patient', colPositions.patient + 2, yPos);
                doc.text('Method', colPositions.method + 2, yPos);
                doc.text('Reference', colPositions.reference + 2, yPos);
                doc.text('Status', colPositions.status + 2, yPos);
                doc.text('Amount', colPositions.amount + 2, yPos);
                doc.setTextColor(0, 0, 0);
                yPos += 10;
                
                // Draw table rows
                doc.setFontSize(8);
                doc.setFont(undefined, 'normal');
                let rowNum = 0;
                
                // Sort transactions by date (newest first)
                const sortedTransactions = [...filteredTransactions].sort((a, b) => {
                    const dateA = new Date(a.date);
                    const dateB = new Date(b.date);
                    return dateB - dateA;
                });
                
                sortedTransactions.forEach((t, index) => {
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
                        doc.text('Date', colPositions.date + 2, yPos);
                        doc.text('Patient', colPositions.patient + 2, yPos);
                        doc.text('Method', colPositions.method + 2, yPos);
                        doc.text('Reference', colPositions.reference + 2, yPos);
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
                    
                    // Date
                    let dateText = t.date || 'N/A';
                    const maxDateWidth = colWidths.date - 4;
                    if (doc.getTextWidth(dateText) > maxDateWidth) {
                        while (doc.getTextWidth(dateText + '...') > maxDateWidth && dateText.length > 0) {
                            dateText = dateText.substring(0, dateText.length - 1);
                        }
                        dateText += '...';
                    }
                    doc.text(dateText, colPositions.date + 2, yPos);
                    
                    // Patient name (truncate if too long)
                    let patientName = t.patientName || 'N/A';
                    const maxPatientWidth = colWidths.patient - 4;
                    if (doc.getTextWidth(patientName) > maxPatientWidth) {
                        while (doc.getTextWidth(patientName + '...') > maxPatientWidth && patientName.length > 0) {
                            patientName = patientName.substring(0, patientName.length - 1);
                        }
                        patientName += '...';
                    }
                    doc.text(patientName, colPositions.patient + 2, yPos);
                    
                    // Payment Method
                    let method = t.method || 'N/A';
                    const maxMethodWidth = colWidths.method - 4;
                    if (doc.getTextWidth(method) > maxMethodWidth) {
                        while (doc.getTextWidth(method + '...') > maxMethodWidth && method.length > 0) {
                            method = method.substring(0, method.length - 1);
                        }
                        method += '...';
                    }
                    doc.text(method, colPositions.method + 2, yPos);
                    
                    // Reference Number
                    let refNo = t.refNo || 'N/A';
                    const maxRefWidth = colWidths.reference - 4;
                    if (doc.getTextWidth(refNo) > maxRefWidth) {
                        while (doc.getTextWidth(refNo + '...') > maxRefWidth && refNo.length > 0) {
                            refNo = refNo.substring(0, refNo.length - 1);
                        }
                        refNo += '...';
                    }
                    doc.text(refNo, colPositions.reference + 2, yPos);
                    
                    // Status
                    let status = t.status ? t.status.charAt(0).toUpperCase() + t.status.slice(1) : 'N/A';
                    const maxStatusWidth = colWidths.status - 4;
                    if (doc.getTextWidth(status) > maxStatusWidth) {
                        while (doc.getTextWidth(status + '...') > maxStatusWidth && status.length > 0) {
                            status = status.substring(0, status.length - 1);
                        }
                        status += '...';
                    }
                    doc.text(status, colPositions.status + 2, yPos);
                    
                    // Amount - use PHP instead of peso sign
                    const amount = parseFloat(t.amount || 0).toFixed(2);
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
            const fileName = `Payment_Report_${new Date().toISOString().split('T')[0]}`;
            const pdfBlob = doc.output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            
            const exportReportBtn = document.getElementById('exportReportBtn');
            const originalHTML = exportReportBtn ? exportReportBtn.innerHTML : '';
            
            // Always open preview first
            const previewWindow = window.open(pdfUrl, '_blank', 'width=800,height=600');
            
            if (previewWindow) {
                if (exportReportBtn) {
                    exportReportBtn.innerHTML = '<span class="material-symbols-outlined">visibility</span> Previewing...';
                    exportReportBtn.disabled = true;
                    
                    // Wait a bit for the preview to open, then offer download option
                    setTimeout(() => {
                        // Reset button state
                        exportReportBtn.innerHTML = originalHTML;
                        exportReportBtn.disabled = false;
                        
                        // Offer download after preview is shown
                        setTimeout(() => {
                            const userWantsDownload = confirm('PDF is now open in a new window for preview. Would you like to download it as well?');
                            
                            if (userWantsDownload) {
                                const downloadLink = document.createElement('a');
                                downloadLink.href = pdfUrl;
                                downloadLink.download = `${fileName}.pdf`;
                                downloadLink.style.display = 'none';
                                document.body.appendChild(downloadLink);
                                downloadLink.click();
                                document.body.removeChild(downloadLink);
                                
                                if (exportReportBtn) {
                                    exportReportBtn.innerHTML = '<span class="material-symbols-outlined">check</span> Downloaded!';
                                    exportReportBtn.classList.add('text-green-500');
                                    
                                    setTimeout(() => {
                                        exportReportBtn.innerHTML = originalHTML;
                                        exportReportBtn.classList.remove('text-green-500');
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
                doc.save(`${fileName}.pdf`);
                if (exportReportBtn) {
                    exportReportBtn.innerHTML = '<span class="material-symbols-outlined">check</span> Downloaded!';
                    exportReportBtn.classList.add('text-green-500');
                    setTimeout(() => {
                        exportReportBtn.innerHTML = originalHTML;
                        exportReportBtn.classList.remove('text-green-500');
                    }, 2000);
                }
                URL.revokeObjectURL(pdfUrl);
            }
            
        } catch (error) {
            console.error('Error exporting PDF:', error);
            alert('Error generating PDF. Please try again.');
            const exportReportBtn = document.getElementById('exportReportBtn');
            if (exportReportBtn) {
                const originalHTML = exportReportBtn.innerHTML;
                exportReportBtn.innerHTML = '<span class="material-symbols-outlined text-[18px]">download</span> Export Data';
            }
        }
    }
    
    // Event listener for export button
    document.getElementById('exportReportBtn')?.addEventListener('click', exportPaymentReportToPDF);

    // Initialize transactions on page load
    async function initializeTransactions() {
        await fetchPayments();
        renderTransactionsTable();
    }

    // Search functionality
    const searchInput = document.querySelector('input[placeholder="Search payments..."]');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            renderTransactionsTable();
        });
    }

    // Initialize on page load
    updateStatCards();
    initializeTransactions();
    loadUnpaidBookings(); // Load unpaid bookings for patient search

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
</script>