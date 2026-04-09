<?php
$staff_nav_active = 'payments';
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
$userId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
$paymentSuccess = '';
$paymentError = '';
$selectedMethod = strtolower(trim((string) ($_POST['payment_method'] ?? 'cash')));
$allowedMethods = [
    'gcash' => 'GCash',
    'cash' => 'Cash',
    'bank_transfer' => 'Bank Transfer',
    'credit_card' => 'Credit Card',
];
if (!isset($allowedMethods[$selectedMethod])) {
    $selectedMethod = 'cash';
}

$summaryTotalRevenue = 0.0;
$summaryTodayRevenue = 0.0;
$summaryTotalPayments = 0;
$recentPayments = [];
$transactionCandidates = [];
$supportsPaymentTypeColumn = false;
$supportsAppointmentUpdatedAtColumn = false;
$formSelectedBookingId = trim((string) ($_POST['selected_booking_id'] ?? ''));
$formPatientQuery = trim((string) ($_POST['patient_query'] ?? ''));
$formAmount = trim((string) ($_POST['amount'] ?? ''));
$formPaymentDate = trim((string) ($_POST['payment_date'] ?? date('Y-m-d')));
$formNotes = trim((string) ($_POST['notes'] ?? ''));

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
        $paymentTypeColumnStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_payments'
              AND COLUMN_NAME = 'payment_type'
            LIMIT 1
        ");
        $paymentTypeColumnStmt->execute();
        $supportsPaymentTypeColumn = (bool) $paymentTypeColumnStmt->fetchColumn();

        $appointmentUpdatedAtColumnStmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'tbl_appointments'
              AND COLUMN_NAME = 'updated_at'
            LIMIT 1
        ");
        $appointmentUpdatedAtColumnStmt->execute();
        $supportsAppointmentUpdatedAtColumn = (bool) $appointmentUpdatedAtColumnStmt->fetchColumn();
    }

    if ($tenantId !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $patientQuery = trim((string) ($_POST['patient_query'] ?? ''));
        $selectedBookingId = trim((string) ($_POST['selected_booking_id'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? 0);
        $paymentDate = trim((string) ($_POST['payment_date'] ?? date('Y-m-d')));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $method = strtolower(trim((string) ($_POST['payment_method'] ?? 'cash')));

        if (!isset($allowedMethods[$method])) {
            $method = 'cash';
        }
        if ($selectedBookingId === '') {
            $paymentError = 'Please select a pending appointment transaction first.';
        } elseif ($amount <= 0) {
            $paymentError = 'Please enter a valid payment amount.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            $paymentError = 'Please provide a valid payment date.';
        } else {
            $bookingSql = "
                SELECT
                    a.booking_id,
                    a.patient_id,
                    COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
                    COALESCE(SUM(CASE WHEN py.status = 'completed' THEN py.amount ELSE 0 END), 0) AS total_paid
                FROM tbl_appointments a
                LEFT JOIN tbl_payments py
                    ON py.tenant_id = a.tenant_id
                   AND py.booking_id = a.booking_id
                WHERE a.tenant_id = ?
                  AND a.booking_id = ?
                GROUP BY a.booking_id, a.patient_id, a.total_treatment_cost
                LIMIT 1
            ";
            $bookingStmt = $pdo->prepare($bookingSql);
            $bookingStmt->execute([$tenantId, $selectedBookingId]);
            $bookingRow = $bookingStmt->fetch(PDO::FETCH_ASSOC);
            $patientId = trim((string) ($bookingRow['patient_id'] ?? ''));
            $totalCost = (float) ($bookingRow['total_treatment_cost'] ?? 0);
            $totalPaid = (float) ($bookingRow['total_paid'] ?? 0);
            $pendingBalance = max(0, $totalCost - $totalPaid);

            if ($patientId === '') {
                $paymentError = 'Selected transaction was not found.';
            } elseif ($pendingBalance <= 0) {
                $paymentError = 'Selected transaction is already fully paid.';
            } elseif ($amount > $pendingBalance) {
                $paymentError = 'Payment amount exceeds the pending balance of ₱' . number_format($pendingBalance, 2) . '.';
            } else {
                try {
                    $paymentId = 'PAY-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
                    // Keep compatibility with deployments where payment_type enum only allows downpayment/fullpayment.
                    $paymentType = ($amount + 0.009 >= $pendingBalance) ? 'fullpayment' : 'downpayment';

                    if ($supportsPaymentTypeColumn) {
                        $insertSql = "
                            INSERT INTO tbl_payments (
                                tenant_id,
                                payment_id,
                                patient_id,
                                booking_id,
                                amount,
                                payment_method,
                                payment_date,
                                notes,
                                status,
                                created_by,
                                payment_type
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, ?)
                        ";
                        $insertParams = [
                            $tenantId,
                            $paymentId,
                            $patientId,
                            $selectedBookingId,
                            $amount,
                            $method,
                            $paymentDate . ' ' . date('H:i:s'),
                            $notes !== '' ? $notes : null,
                            $userId !== '' ? $userId : null,
                            $paymentType,
                        ];
                    } else {
                        $insertSql = "
                            INSERT INTO tbl_payments (
                                tenant_id,
                                payment_id,
                                patient_id,
                                booking_id,
                                amount,
                                payment_method,
                                payment_date,
                                notes,
                                status,
                                created_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)
                        ";
                        $insertParams = [
                            $tenantId,
                            $paymentId,
                            $patientId,
                            $selectedBookingId,
                            $amount,
                            $method,
                            $paymentDate . ' ' . date('H:i:s'),
                            $notes !== '' ? $notes : null,
                            $userId !== '' ? $userId : null,
                        ];
                    }

                    $insertStmt = $pdo->prepare($insertSql);
                    $insertStmt->execute($insertParams);

                    $nextAppointmentStatus = ($amount + 0.009 >= $pendingBalance) ? 'completed' : 'confirmed';
                    $updateAppointmentSql = "
                        UPDATE tbl_appointments
                        SET status = ?" . ($supportsAppointmentUpdatedAtColumn ? ", updated_at = NOW()" : "") . "
                        WHERE tenant_id = ?
                          AND booking_id = ?
                          AND status = 'pending'
                    ";
                    $updateAppointmentStmt = $pdo->prepare($updateAppointmentSql);
                    $updateAppointmentStmt->execute([$nextAppointmentStatus, $tenantId, $selectedBookingId]);

                    $paymentSuccess = 'Payment recorded successfully.';
                    // Reset the modal form after successful submission.
                    $selectedMethod = 'cash';
                    $formSelectedBookingId = '';
                    $formPatientQuery = '';
                    $formAmount = '';
                    $formPaymentDate = date('Y-m-d');
                    $formNotes = '';
                } catch (Throwable $postError) {
                    error_log('Staff payment record submit error: ' . $postError->getMessage());
                    $paymentError = 'Unable to record payment right now. Please try again.';
                }
            }
        }
    }

    if ($tenantId !== '') {
        $today = date('Y-m-d');

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_revenue FROM tbl_payments WHERE tenant_id = ? AND status = 'completed'");
        $stmt->execute([$tenantId]);
        $summaryTotalRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0);

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS today_revenue FROM tbl_payments WHERE tenant_id = ? AND DATE(payment_date) = ? AND status = 'completed'");
        $stmt->execute([$tenantId, $today]);
        $summaryTodayRevenue = (float) ($stmt->fetch(PDO::FETCH_ASSOC)['today_revenue'] ?? 0);

        $stmt = $pdo->prepare('SELECT COUNT(*) AS total_payments FROM tbl_payments WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $summaryTotalPayments = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total_payments'] ?? 0);

        $recentSql = "
            SELECT
                py.payment_id,
                py.patient_id,
                py.amount,
                py.payment_date,
                py.payment_method,
                py.status,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name
            FROM tbl_payments py
            LEFT JOIN tbl_patients p
                ON p.tenant_id = py.tenant_id
               AND p.patient_id = py.patient_id
            WHERE py.tenant_id = ?
            ORDER BY py.payment_date DESC, py.id DESC
            LIMIT 20
        ";
        $recentStmt = $pdo->prepare($recentSql);
        $recentStmt->execute([$tenantId]);
        $recentPayments = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $transactionsSql = "
            SELECT
                a.booking_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                a.service_type,
                COALESCE(a.total_treatment_cost, 0) AS total_treatment_cost,
                COALESCE(SUM(CASE WHEN py.status = 'completed' THEN py.amount ELSE 0 END), 0) AS total_paid,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name
            FROM tbl_appointments a
            LEFT JOIN tbl_payments py
                ON py.tenant_id = a.tenant_id
               AND py.booking_id = a.booking_id
            LEFT JOIN tbl_patients p
                ON p.tenant_id = a.tenant_id
               AND p.patient_id = a.patient_id
            WHERE a.tenant_id = ?
              AND COALESCE(a.total_treatment_cost, 0) > 0
              AND LOWER(COALESCE(a.status, '')) <> 'cancelled'
            GROUP BY
                a.booking_id,
                a.patient_id,
                a.appointment_date,
                a.appointment_time,
                a.service_type,
                a.total_treatment_cost,
                p.first_name,
                p.last_name
            HAVING (COALESCE(a.total_treatment_cost, 0) - COALESCE(SUM(CASE WHEN py.status = 'completed' THEN py.amount ELSE 0 END), 0)) > 0.009
            ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.created_at DESC
            LIMIT 300
        ";
        $transactionsStmt = $pdo->prepare($transactionsSql);
        $transactionsStmt->execute([$tenantId]);
        $transactionCandidates = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="payment-transactions-' . date('Ymd-His') . '.csv"');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Payment ID', 'Patient ID', 'Patient Name', 'Amount', 'Payment Date', 'Method', 'Status']);
            foreach ($recentPayments as $row) {
                $fullName = trim(((string) ($row['patient_first_name'] ?? '')) . ' ' . ((string) ($row['patient_last_name'] ?? '')));
                fputcsv($output, [
                    (string) ($row['payment_id'] ?? ''),
                    (string) ($row['patient_id'] ?? ''),
                    $fullName !== '' ? $fullName : 'Unknown Patient',
                    number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                    (string) ($row['payment_date'] ?? ''),
                    (string) ($row['payment_method'] ?? ''),
                    (string) ($row['status'] ?? ''),
                ]);
            }
            fclose($output);
            exit;
        }
    }
} catch (Throwable $e) {
    error_log('Staff payment recording error: ' . $e->getMessage());
    if ($paymentError === '') {
        $paymentError = 'Unable to load payment data right now. Please try again.';
    }
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Clinical Precision - Payment Recording</title>
<!-- Google Fonts: Manrope & Playfair Display -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
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
        .glass-form {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.8);
            background-image: radial-gradient(circle at top right, rgba(43, 139, 235, 0.05), transparent);
        }
        .form-input-styled {
            border: 2px solid transparent;
            background: rgba(241, 245, 249, 0.8);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .form-input-styled:focus {
            border-color: #2b8beb;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(43, 139, 235, 0.1);
        }
        .payment-card {
            transition: all 0.2s ease;
        }
        .payment-card:hover {
            transform: translateY(-2px);
        }
        .payment-card.active {
            background: #2b8beb;
            color: white;
            border-color: #2b8beb;
            box-shadow: 0 8px 16px -4px rgba(43, 139, 235, 0.4);
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<!-- SideNavBar Component -->
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> PAYMENT RECORDING
            </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Payment <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Recording</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                        Record and track all clinic payment transactions
                    </p>
</div>
<button class="px-6 py-3 bg-primary text-white text-[11px] font-black uppercase tracking-widest rounded-xl shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-95 transition-all" id="open-transaction-modal" type="button">
                    New Transaction
                </button>
</div>
</section>
<!-- Summary Cards -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<!-- Total Revenue -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-600 transition-colors group-hover:bg-emerald-500 group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
</div>
<span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-3 py-1.5 rounded-full uppercase tracking-widest">+12.5%</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">₱<?php echo number_format($summaryTotalRevenue, 2); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Revenue</p>
</div>
</div>
<!-- Today's Revenue -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">today</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Today</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter">₱<?php echo number_format($summaryTodayRevenue, 2); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Today's Revenue</p>
</div>
</div>
<!-- Total Payments -->
<div class="elevated-card p-8 rounded-3xl flex flex-col justify-between hover:border-primary/30 transition-all group">
<div class="flex justify-between items-start mb-6">
<div class="w-12 h-12 bg-primary/10 rounded-2xl flex items-center justify-center text-primary transition-colors group-hover:bg-primary group-hover:text-white">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">receipt_long</span>
</div>
<span class="text-[10px] font-black text-primary bg-primary/10 px-3 py-1.5 rounded-full uppercase tracking-widest">Lifetime</span>
</div>
<div>
<p class="text-5xl font-extrabold font-headline text-on-background tracking-tighter"><?php echo number_format($summaryTotalPayments); ?></p>
<p class="text-xs font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mt-2">Total Payments</p>
</div>
</div>
</section>
<?php if ($paymentSuccess !== ''): ?>
<section>
<div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-700 px-5 py-3 text-sm font-semibold">
<?php echo htmlspecialchars($paymentSuccess, ENT_QUOTES, 'UTF-8'); ?>
</div>
</section>
<?php endif; ?>
<!-- Recent Transactions Section -->
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="p-8 border-b border-slate-100 flex justify-between items-center bg-white">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">Recent Transactions</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Latest daily transaction log</p>
</div>
<div class="flex gap-3">
<button class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2">
<span class="material-symbols-outlined text-sm">filter_list</span> Filter
                    </button>
<a class="px-5 py-2.5 border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl hover:bg-slate-50 transition-all flex items-center gap-2" href="?export=csv<?php echo $currentTenantSlug !== '' ? '&clinic_slug=' . urlencode($currentTenantSlug) : ''; ?>">
<span class="material-symbols-outlined text-sm">download</span> Export CSV
                    </a>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Patient Name</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Amount</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date &amp; Time</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Method</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<?php if (empty($recentPayments)): ?>
<tr>
<td class="px-8 py-7 text-sm font-semibold text-slate-500" colspan="6">No payment transactions yet.</td>
</tr>
<?php else: ?>
<?php foreach ($recentPayments as $payment): ?>
<?php
    $patientFirst = trim((string) ($payment['patient_first_name'] ?? ''));
    $patientLast = trim((string) ($payment['patient_last_name'] ?? ''));
    $patientName = trim($patientFirst . ' ' . $patientLast);
    if ($patientName === '') {
        $patientName = 'Unknown Patient';
    }
    $patientIdLabel = trim((string) ($payment['patient_id'] ?? ''));
    $initials = strtoupper(substr($patientFirst !== '' ? $patientFirst : $patientName, 0, 1) . substr($patientLast !== '' ? $patientLast : 'X', 0, 1));
    $amountLabel = '₱' . number_format((float) ($payment['amount'] ?? 0), 2);
    $paymentDateRaw = trim((string) ($payment['payment_date'] ?? ''));
    $dateLabel = $paymentDateRaw !== '' ? date('M d, Y', strtotime($paymentDateRaw)) : '-';
    $timeLabel = $paymentDateRaw !== '' ? date('h:i A', strtotime($paymentDateRaw)) : '-';
    $methodKey = strtolower(trim((string) ($payment['payment_method'] ?? 'cash')));
    $methodLabel = $allowedMethods[$methodKey] ?? ucfirst(str_replace('_', ' ', $methodKey));
    $statusKey = strtolower(trim((string) ($payment['status'] ?? 'pending')));
    $statusLabel = ucfirst(str_replace('_', ' ', $statusKey));
    $statusClasses = 'bg-amber-50 text-amber-600';
    $dotClass = 'bg-amber-500';
    if ($statusKey === 'completed') {
        $statusClasses = 'bg-emerald-50 text-emerald-600';
        $dotClass = 'bg-emerald-500';
    } elseif ($statusKey === 'cancelled') {
        $statusClasses = 'bg-slate-100 text-slate-600';
        $dotClass = 'bg-slate-500';
    }
?>
<tr class="hover:bg-slate-50/30 transition-colors group">
<td class="px-8 py-5">
<div class="flex items-center gap-4">
<div class="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-primary font-black text-xs"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></div>
<div>
<p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors"><?php echo htmlspecialchars($patientName, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-slate-500 font-medium mt-0.5"><?php echo $patientIdLabel !== '' ? 'ID: ' . htmlspecialchars($patientIdLabel, ENT_QUOTES, 'UTF-8') : 'ID: N/A'; ?></p>
</div>
</div>
</td>
<td class="px-6 py-5">
<p class="text-sm font-extrabold text-slate-900"><?php echo htmlspecialchars($amountLabel, ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-6 py-5">
<p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-slate-500 font-bold uppercase tracking-wide mt-0.5"><?php echo htmlspecialchars($timeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
</td>
<td class="px-6 py-5">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-slate-500 text-sm">payments</span>
<span class="text-[10px] font-bold text-slate-600 uppercase tracking-wider"><?php echo htmlspecialchars($methodLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center gap-1.5 px-3 py-1 <?php echo $statusClasses; ?> text-[10px] font-black rounded-full uppercase tracking-widest">
<span class="w-1.5 h-1.5 rounded-full <?php echo $dotClass; ?>"></span>
<?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
</span>
</td>
<td class="px-8 py-5 text-right">
<div class="flex justify-end gap-2">
<button class="p-2 hover:bg-primary/10 rounded-lg text-primary transition-colors" title="<?php echo htmlspecialchars((string) ($payment['payment_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" type="button">
<span class="material-symbols-outlined text-sm">visibility</span>
</button>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
<p class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing <?php echo number_format(count($recentPayments)); ?> of <?php echo number_format($summaryTotalPayments); ?> recent entries</p>
<div class="flex gap-2">
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</button>
<button class="w-8 h-8 rounded-lg bg-primary text-white text-xs font-black">1</button>
<button class="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</button>
</div>
</div>
</section>
</div>
</main>
<div class="fixed inset-0 z-50 hidden items-center justify-center p-6" id="transaction-modal" role="dialog" aria-modal="true" aria-labelledby="transaction-modal-title">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" id="transaction-modal-overlay"></div>
<div class="relative z-10 w-full max-w-4xl">
<div class="glass-form bg-white p-10 rounded-[2.5rem] shadow-2xl shadow-primary/20 max-h-[88vh] overflow-y-auto no-scrollbar">
<?php if ($paymentError !== ''): ?>
<div class="mb-6 rounded-2xl border border-red-200 bg-red-50 text-red-700 px-5 py-3 text-sm font-semibold">
<?php echo htmlspecialchars($paymentError, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>
<div class="flex justify-between items-start mb-10 border-b border-primary/10 pb-6">
<div>
<h3 class="text-3xl font-black font-headline text-slate-900" id="transaction-modal-title">Record New Payment</h3>
<p class="text-xs text-primary font-bold uppercase tracking-[0.2em] mt-1">Submit digital transaction receipt</p>
</div>
<button class="w-10 h-10 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors" id="close-transaction-modal" type="button">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form class="space-y-10" method="post">
<div class="space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Patient Identification</label>
<div class="relative group">
<input name="selected_booking_id" id="selected_booking_id_input" type="hidden" value="<?php echo htmlspecialchars($formSelectedBookingId, ENT_QUOTES, 'UTF-8'); ?>"/>
<input name="patient_query" id="patient_query_input" type="hidden" value="<?php echo htmlspecialchars($formPatientQuery, ENT_QUOTES, 'UTF-8'); ?>"/>
<button id="open-transaction-selector-modal" type="button" class="w-full px-6 py-4 form-input-styled rounded-2xl text-left text-base font-semibold outline-none inline-flex items-center justify-between gap-3">
<span class="inline-flex items-center gap-3 min-w-0">
<span class="material-symbols-outlined text-slate-400">person_search</span>
<span id="selected_transaction_label" class="truncate"><?php echo htmlspecialchars($formPatientQuery !== '' ? $formPatientQuery : 'Select appointment transaction with pending balance', ENT_QUOTES, 'UTF-8'); ?></span>
</span>
<span class="material-symbols-outlined text-slate-500">keyboard_arrow_down</span>
</button>
</div>
<p class="text-[11px] font-semibold text-slate-500 ml-1">Only appointments with pending balance are listed.</p>
</div>
<div class="flex flex-col md:flex-row gap-8 items-center">
<div class="flex-1 w-full space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Payment Amount</label>
<div class="relative group">
<span class="absolute left-5 top-1/2 -translate-y-1/2 text-lg font-extrabold text-slate-500 group-focus-within:text-primary transition-colors">₱</span>
<input class="w-full pl-12 pr-6 py-4 form-input-styled rounded-2xl text-xl font-black outline-none" min="0.01" name="amount" placeholder="0.00" required step="0.01" type="number" value="<?php echo htmlspecialchars($formAmount, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
</div>
<div class="hidden md:block h-12 w-px bg-slate-200 mt-6"></div>
<div class="flex-1 w-full space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Transaction Date</label>
<div class="relative group">
<input class="w-full px-6 py-4 form-input-styled rounded-2xl text-base font-semibold outline-none" max="<?php echo date('Y-m-d'); ?>" name="payment_date" required type="date" value="<?php echo htmlspecialchars($formPaymentDate, ENT_QUOTES, 'UTF-8'); ?>"/>
</div>
</div>
</div>
<div class="space-y-4">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Payment Method</label>
<input id="payment_method_input" name="payment_method" type="hidden" value="<?php echo htmlspecialchars($selectedMethod, ENT_QUOTES, 'UTF-8'); ?>"/>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn <?php echo $selectedMethod === 'gcash' ? 'active' : ''; ?>" data-method="gcash" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">account_balance_wallet</span>
<span class="text-[11px] font-black uppercase tracking-widest">GCash</span>
</button>
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 <?php echo $selectedMethod === 'cash' ? 'active' : ''; ?>" data-method="cash" type="button">
<span class="material-symbols-outlined text-3xl" style="font-variation-settings: 'FILL' 1;">payments</span>
<span class="text-[11px] font-black uppercase tracking-widest">Cash</span>
</button>
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn <?php echo $selectedMethod === 'bank_transfer' ? 'active' : ''; ?>" data-method="bank_transfer" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">account_balance</span>
<span class="text-[11px] font-black uppercase tracking-widest">Bank</span>
</button>
<button class="payment-card p-4 rounded-2xl border-2 border-slate-100 bg-white/50 flex flex-col items-center justify-center gap-3 group/btn <?php echo $selectedMethod === 'credit_card' ? 'active' : ''; ?>" data-method="credit_card" type="button">
<span class="material-symbols-outlined text-3xl text-slate-400 group-hover/btn:text-primary transition-colors">credit_card</span>
<span class="text-[11px] font-black uppercase tracking-widest">Card</span>
</button>
</div>
</div>
<div class="space-y-3">
<label class="text-[11px] font-black uppercase tracking-widest text-slate-500 ml-1">Additional Notes</label>
<textarea class="w-full px-6 py-4 form-input-styled rounded-2xl text-sm font-medium outline-none resize-none" name="notes" placeholder="Describe the treatment or specific billing details..." rows="3"><?php echo htmlspecialchars($formNotes, ENT_QUOTES, 'UTF-8'); ?></textarea>
</div>
<div class="pt-4">
<button class="w-full py-5 bg-primary text-white font-black text-sm uppercase tracking-[0.3em] rounded-2xl shadow-2xl shadow-primary/40 hover:shadow-primary/60 hover:-translate-y-1 active:translate-y-0 active:scale-[0.99] transition-all flex items-center justify-center gap-4 relative overflow-hidden group" type="submit">
<span class="absolute inset-0 bg-white/10 translate-y-full group-hover:translate-y-0 transition-transform duration-300"></span>
<span class="material-symbols-outlined text-2xl relative" style="font-variation-settings: 'FILL' 1;">verified</span>
<span class="relative">Confirm &amp; Post Payment</span>
</button>
</div>
</form>
</div>
</div>
</div>
<div class="fixed inset-0 z-[60] hidden items-center justify-center p-6" id="transaction-selector-modal" role="dialog" aria-modal="true" aria-labelledby="transaction-selector-title">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" id="transaction-selector-overlay"></div>
<div class="relative z-10 w-full max-w-5xl">
<div class="bg-white p-6 rounded-3xl shadow-2xl border border-slate-200 max-h-[88vh] overflow-hidden flex flex-col">
<div class="flex items-center justify-between gap-4 pb-4 border-b border-slate-100">
<div>
<h3 class="text-xl font-black text-slate-900" id="transaction-selector-title">Select Pending Transaction</h3>
<p class="text-[11px] text-slate-500 font-bold uppercase tracking-widest mt-1">Appointments with unpaid balance</p>
</div>
<button class="w-9 h-9 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 inline-flex items-center justify-center" id="close-transaction-selector-modal" type="button">
<span class="material-symbols-outlined text-[18px]">close</span>
</button>
</div>
<div class="py-4 border-b border-slate-100">
<input id="transaction_selector_search" type="text" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-sm font-medium outline-none focus:border-primary focus:bg-white focus:shadow-[0_0_0_4px_rgba(43,139,235,0.1)]" placeholder="Search patient name, patient ID, booking ID, or service"/>
</div>
<div id="transaction_selector_list" class="overflow-y-auto divide-y divide-slate-100 min-h-[14rem]"></div>
<div id="transaction_selector_empty" class="hidden py-10 text-center text-sm font-semibold text-slate-500">No pending transactions found.</div>
</div>
</div>
</div>
<script>
    (function () {
        const modal = document.getElementById('transaction-modal');
        const openBtn = document.getElementById('open-transaction-modal');
        const closeBtn = document.getElementById('close-transaction-modal');
        const overlay = document.getElementById('transaction-modal-overlay');
        const hasServerError = <?php echo $paymentError !== '' ? 'true' : 'false'; ?>;
        const transactionCandidates = <?php echo json_encode($transactionCandidates, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const openSelectorBtn = document.getElementById('open-transaction-selector-modal');
        const selectorModal = document.getElementById('transaction-selector-modal');
        const selectorOverlay = document.getElementById('transaction-selector-overlay');
        const closeSelectorBtn = document.getElementById('close-transaction-selector-modal');
        const selectorSearchInput = document.getElementById('transaction_selector_search');
        const selectorList = document.getElementById('transaction_selector_list');
        const selectorEmpty = document.getElementById('transaction_selector_empty');
        const selectedBookingIdInput = document.getElementById('selected_booking_id_input');
        const patientQueryInput = document.getElementById('patient_query_input');
        const selectedTransactionLabel = document.getElementById('selected_transaction_label');
        const amountInput = document.querySelector('input[name="amount"]');

        const normalizeTransactions = transactionCandidates.map((item) => {
            const totalCost = Number(item.total_treatment_cost || 0);
            const totalPaid = Number(item.total_paid || 0);
            const pendingBalance = Math.max(0, totalCost - totalPaid);
            const firstName = String(item.patient_first_name || '').trim();
            const lastName = String(item.patient_last_name || '').trim();
            const patientName = (firstName + ' ' + lastName).trim() || 'Unknown Patient';
            const label = patientName + ' | Booking ' + (item.booking_id || '-') + ' | Pending ₱' + pendingBalance.toFixed(2);
            return {
                booking_id: String(item.booking_id || ''),
                patient_id: String(item.patient_id || ''),
                patient_name: patientName,
                service_type: String(item.service_type || '-'),
                appointment_date: String(item.appointment_date || ''),
                appointment_time: String(item.appointment_time || ''),
                total_cost: totalCost,
                total_paid: totalPaid,
                pending_balance: pendingBalance,
                label: label
            };
        }).filter((item) => item.pending_balance > 0);

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderTransactionRows(list) {
            if (!selectorList || !selectorEmpty) return;
            if (!list.length) {
                selectorList.innerHTML = '';
                selectorEmpty.classList.remove('hidden');
                return;
            }

            selectorEmpty.classList.add('hidden');
            selectorList.innerHTML = list.map((item) => {
                return '' +
                    '<div class="py-3 px-1 sm:px-2">' +
                        '<div class="rounded-2xl border border-slate-200 p-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">' +
                            '<div class="min-w-0">' +
                                '<p class="text-sm font-extrabold text-slate-900 truncate">' + escapeHtml(item.patient_name) + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Patient ID: ' + escapeHtml(item.patient_id) + ' | Booking ID: ' + escapeHtml(item.booking_id) + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Service: ' + escapeHtml(item.service_type) + '</p>' +
                                '<p class="text-xs font-semibold text-slate-500 mt-1">Date: ' + escapeHtml(item.appointment_date || '-') + ' ' + escapeHtml(item.appointment_time || '') + '</p>' +
                                '<p class="text-xs font-semibold text-slate-700 mt-1">Total: ₱' + item.total_cost.toFixed(2) + ' | Paid: ₱' + item.total_paid.toFixed(2) + ' | Pending: ₱' + item.pending_balance.toFixed(2) + '</p>' +
                            '</div>' +
                            '<button type="button" data-action="select-transaction" data-booking-id="' + escapeHtml(item.booking_id) + '" class="shrink-0 px-4 py-2.5 rounded-xl bg-primary text-white text-xs font-black uppercase tracking-widest hover:bg-primary/90 transition-colors">Select</button>' +
                        '</div>' +
                    '</div>';
            }).join('');
        }

        function openSelectorModal() {
            if (!selectorModal) return;
            selectorModal.classList.remove('hidden');
            selectorModal.classList.add('flex');
            if (selectorSearchInput) {
                selectorSearchInput.value = '';
            }
            renderTransactionRows(normalizeTransactions);
        }

        function closeSelectorModal() {
            if (!selectorModal) return;
            selectorModal.classList.add('hidden');
            selectorModal.classList.remove('flex');
        }

        const openModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        };

        const closeModal = () => {
            if (!modal) {
                return;
            }
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        };

        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }
        if (overlay) {
            overlay.addEventListener('click', closeModal);
        }
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
        if (hasServerError) {
            openModal();
        }

        if (openSelectorBtn) {
            openSelectorBtn.addEventListener('click', openSelectorModal);
        }
        if (closeSelectorBtn) {
            closeSelectorBtn.addEventListener('click', closeSelectorModal);
        }
        if (selectorOverlay) {
            selectorOverlay.addEventListener('click', closeSelectorModal);
        }
        if (selectorSearchInput) {
            selectorSearchInput.addEventListener('input', () => {
                const keyword = String(selectorSearchInput.value || '').trim().toLowerCase();
                if (!keyword) {
                    renderTransactionRows(normalizeTransactions);
                    return;
                }
                const filtered = normalizeTransactions.filter((item) => {
                    return [
                        item.patient_name,
                        item.patient_id,
                        item.booking_id,
                        item.service_type
                    ].join(' ').toLowerCase().indexOf(keyword) !== -1;
                });
                renderTransactionRows(filtered);
            });
        }
        if (selectorList) {
            selectorList.addEventListener('click', (event) => {
                const btn = event.target.closest('button[data-action="select-transaction"]');
                if (!btn) {
                    return;
                }
                const bookingId = String(btn.getAttribute('data-booking-id') || '');
                const selected = normalizeTransactions.find((item) => item.booking_id === bookingId);
                if (!selected) {
                    return;
                }
                if (selectedBookingIdInput) {
                    selectedBookingIdInput.value = selected.booking_id;
                }
                if (patientQueryInput) {
                    patientQueryInput.value = selected.label;
                }
                if (selectedTransactionLabel) {
                    selectedTransactionLabel.textContent = selected.label;
                }
                if (amountInput) {
                    amountInput.value = selected.pending_balance.toFixed(2);
                }
                closeSelectorModal();
            });
        }

        const hiddenInput = document.getElementById('payment_method_input');
        const cards = document.querySelectorAll('.payment-card[data-method]');
        cards.forEach((card) => {
            card.addEventListener('click', () => {
                cards.forEach((other) => other.classList.remove('active'));
                card.classList.add('active');
                if (hiddenInput) {
                    hiddenInput.value = card.getAttribute('data-method') || 'cash';
                }
            });
        });
    })();
</script>
</body></html>