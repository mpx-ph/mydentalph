<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auditlogs_tz_helper.php';

@date_default_timezone_set('Asia/Manila');

$auditLogStorageTz = new DateTimeZone('+00:00');

$totalLogs = 0;
$loginEvents = 0;
$logoutEvents = 0;
$totalEventRows = 0;
$eventRows = [];
$dbError = null;
$perPage = 10;
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$totalPages = 1;
$showingFrom = 0;
$showingTo = 0;
$paginationItems = [];
$clinicOptions = [];

$userTypeRaw = strtolower(trim((string) ($_GET['user_type'] ?? 'all')));
$allowedUserTypes = ['all', 'super_admin', 'tenant', 'staff', 'doctor'];
$selectedUserType = in_array($userTypeRaw, $allowedUserTypes, true) ? $userTypeRaw : 'all';

$actionRaw = strtolower(trim((string) ($_GET['action_type'] ?? 'all')));
$allowedActions = ['all', 'login', 'logout', 'subscriptions'];
$selectedActionType = in_array($actionRaw, $allowedActions, true) ? $actionRaw : 'all';

$clinicFilterRaw = trim((string) ($_GET['clinic_id'] ?? 'all'));
$selectedClinicId = $clinicFilterRaw !== '' ? $clinicFilterRaw : 'all';

$searchTerm = trim((string) ($_GET['q'] ?? ''));

$fromDateRaw = trim((string) ($_GET['from_date'] ?? ''));
$selectedFromDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDateRaw) ? $fromDateRaw : '';

$toDateRaw = trim((string) ($_GET['to_date'] ?? ''));
$selectedToDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDateRaw) ? $toDateRaw : '';

$basePath = strtok($_SERVER['REQUEST_URI'] ?? 'auditlogs.php', '?');
$basePath = is_string($basePath) && $basePath !== '' ? $basePath : 'auditlogs.php';
$queryParams = $_GET;
unset($queryParams['page']);
$buildPageUrl = static function (int $page) use ($basePath, $queryParams): string {
    $params = $queryParams;
    $params['page'] = max(1, $page);
    return $basePath . '?' . http_build_query($params);
};

try {
    // Rows use MySQL CURRENT_TIMESTAMP in the connection's zone (usually SYSTEM) — infer BEFORE SET +08.
    $auditLogStorageTz = auditlogs_infer_mysql_storage_timezone($pdo);

    try {
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (Throwable $e) {
        // Hosting may block SET time_zone; page still uses Manila for PHP display.
    }

    $totalLogs = (int) $pdo->query('SELECT COUNT(*) FROM tbl_audit_logs')->fetchColumn();

    $loginStmt = $pdo->query("
        SELECT COUNT(*)
        FROM tbl_audit_logs
        WHERE LOWER(action) LIKE '%login%'
    ");
    $loginEvents = (int) $loginStmt->fetchColumn();

    $logoutStmt = $pdo->query("
        SELECT COUNT(*)
        FROM tbl_audit_logs
        WHERE LOWER(action) LIKE '%logout%'
    ");
    $logoutEvents = (int) $logoutStmt->fetchColumn();

    $clinicStmt = $pdo->query("
        SELECT tenant_id, clinic_name
        FROM tbl_tenants
        ORDER BY clinic_name ASC
    ");
    $clinicOptions = $clinicStmt->fetchAll(PDO::FETCH_ASSOC);
    $validClinicIds = [];
    foreach ($clinicOptions as $clinicRow) {
        $tenantId = trim((string) ($clinicRow['tenant_id'] ?? ''));
        if ($tenantId !== '') {
            $validClinicIds[$tenantId] = true;
        }
    }
    if ($selectedClinicId !== 'all' && !isset($validClinicIds[$selectedClinicId])) {
        $selectedClinicId = 'all';
    }

    $whereClauses = [];
    $queryBindings = [];

    if ($selectedActionType === 'login') {
        $whereClauses[] = "LOWER(l.action) LIKE :action_login";
        $queryBindings[':action_login'] = '%login%';
    } elseif ($selectedActionType === 'logout') {
        $whereClauses[] = "LOWER(l.action) LIKE :action_logout";
        $queryBindings[':action_logout'] = '%logout%';
    } elseif ($selectedActionType === 'subscriptions') {
        $whereClauses[] = "LOWER(l.action) LIKE :action_subscription";
        $queryBindings[':action_subscription'] = '%subscription%';
    } else {
        $whereClauses[] = "(LOWER(l.action) LIKE :action_login OR LOWER(l.action) LIKE :action_logout OR LOWER(l.action) LIKE :action_subscription)";
        $queryBindings[':action_login'] = '%login%';
        $queryBindings[':action_logout'] = '%logout%';
        $queryBindings[':action_subscription'] = '%subscription%';
    }

    if ($selectedUserType === 'super_admin') {
        $whereClauses[] = "u.role = :role_superadmin";
        $queryBindings[':role_superadmin'] = 'superadmin';
    } elseif ($selectedUserType === 'tenant') {
        $whereClauses[] = "(u.role = :role_tenant_owner OR u.role = :role_manager)";
        $queryBindings[':role_tenant_owner'] = 'tenant_owner';
        $queryBindings[':role_manager'] = 'manager';
    } elseif ($selectedUserType === 'staff') {
        $whereClauses[] = "u.role = :role_staff";
        $queryBindings[':role_staff'] = 'staff';
    } elseif ($selectedUserType === 'doctor') {
        $whereClauses[] = "u.role = :role_doctor";
        $queryBindings[':role_doctor'] = 'dentist';
    }

    if ($selectedClinicId !== 'all') {
        $whereClauses[] = "l.tenant_id = :clinic_id";
        $queryBindings[':clinic_id'] = $selectedClinicId;
    }

    if ($selectedFromDate !== '') {
        $whereClauses[] = "l.created_at >= :from_date_start";
        $queryBindings[':from_date_start'] = $selectedFromDate . ' 00:00:00';
    }

    if ($selectedToDate !== '') {
        $whereClauses[] = "l.created_at <= :to_date_end";
        $queryBindings[':to_date_end'] = $selectedToDate . ' 23:59:59';
    }

    if ($searchTerm !== '') {
        $whereClauses[] = "(u.full_name LIKE :search_term OR u.email LIKE :search_term OR l.user_id LIKE :search_term OR l.action LIKE :search_term)";
        $queryBindings[':search_term'] = '%' . $searchTerm . '%';
    }

    $eventsWhereSql = implode(' AND ', $whereClauses);

    $countEventsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM tbl_audit_logs l
        LEFT JOIN tbl_users u ON u.user_id = l.user_id
        WHERE {$eventsWhereSql}
    ");
    foreach ($queryBindings as $bindingKey => $bindingValue) {
        $countEventsStmt->bindValue($bindingKey, $bindingValue);
    }
    $countEventsStmt->execute();
    $totalEventRows = (int) $countEventsStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalEventRows / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    $eventsStmt = $pdo->prepare("
        SELECT
            l.log_id,
            l.user_id,
            l.action,
            l.ip_address,
            l.created_at,
            u.full_name,
            u.email,
            u.role,
            t.clinic_name
        FROM tbl_audit_logs l
        LEFT JOIN tbl_users u ON u.user_id = l.user_id
        LEFT JOIN tbl_tenants t ON t.tenant_id = l.tenant_id
        WHERE {$eventsWhereSql}
        ORDER BY l.created_at DESC, l.log_id DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($queryBindings as $bindingKey => $bindingValue) {
        $eventsStmt->bindValue($bindingKey, $bindingValue);
    }
    $eventsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $eventsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $eventsStmt->execute();
    $eventRows = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($totalEventRows > 0) {
        $showingFrom = $offset + 1;
        $showingTo = $offset + count($eventRows);
    }

    if ($totalPages <= 7) {
        for ($page = 1; $page <= $totalPages; $page++) {
            $paginationItems[] = $page;
        }
    } else {
        $paginationItems[] = 1;
        $startPage = max(2, $currentPage - 1);
        $endPage = min($totalPages - 1, $currentPage + 1);

        if ($startPage > 2) {
            $paginationItems[] = '...';
        }
        for ($page = $startPage; $page <= $endPage; $page++) {
            $paginationItems[] = $page;
        }
        if ($endPage < $totalPages - 1) {
            $paginationItems[] = '...';
        }
        $paginationItems[] = $totalPages;
    }
} catch (Throwable $e) {
    $dbError = 'Unable to load audit logs right now.';
}
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Audit Logs | Clinical Precision</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0066ff",
                        "on-surface": "#131c25",
                        "on-surface-variant": "#404752",
                        "outline-variant": "#c0c7d4",
                        "error": "#ba1a1a",
                        "surface-container-low": "#edf4ff",
                        "surface-container-high": "#e0e9f6",
                        "surface-container-highest": "#dae3f0",
                        "background": "#f7f9ff",
                        "tertiary": "#8e4a00",
                        "error-container": "#ffdad6",
                    },
                    fontFamily: {
                        "headline": ["Manrope", "Plus Jakarta Sans", "sans-serif"],
                        "body": ["Manrope", "Inter", "sans-serif"],
                        "label": ["Inter", "sans-serif"]
                    },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "3xl": "1.5rem", "full": "9999px" },
                },
            },
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .editorial-shadow {
            box-shadow: 0 12px 40px -10px rgba(19, 28, 37, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(0, 102, 255, 0.3);
        }
        .primary-glow {
            box-shadow: 0 8px 25px -5px rgba(0, 102, 255, 0.4);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image: 
                radial-gradient(at 0% 0%, hsla(210,100%,98%,1) 0, transparent 50%), 
                radial-gradient(at 50% 0%, hsla(217,100%,94%,1) 0, transparent 50%), 
                radial-gradient(at 100% 0%, hsla(210,100%,98%,1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        @media (max-width: 1023px) {
            #superadmin-sidebar {
                transform: translateX(-100%);
                transition: transform 220ms ease;
                z-index: 60;
            }
            body.sa-mobile-sidebar-open #superadmin-sidebar {
                transform: translateX(0);
            }
            .sa-top-header {
                left: 0;
                width: 100% !important;
                padding-left: 5.5rem;
                padding-right: 1rem;
            }
            #sa-mobile-sidebar-toggle {
                top: 1rem;
                left: 0.75rem;
                width: 2.75rem;
                height: 2.75rem;
                transition: left 220ms ease, background-color 220ms ease, color 220ms ease;
            }
            body.sa-mobile-sidebar-open #sa-mobile-sidebar-toggle {
                left: calc(16rem - 3.25rem);
                background: rgba(255, 255, 255, 0.98);
                color: #0066ff;
            }
            #sa-mobile-sidebar-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(19, 28, 37, 0.45);
                backdrop-filter: blur(2px);
                -webkit-backdrop-filter: blur(2px);
                z-index: 55;
                opacity: 0;
                pointer-events: none;
                transition: opacity 220ms ease;
            }
            body.sa-mobile-sidebar-open #sa-mobile-sidebar-backdrop {
                opacity: 1;
                pointer-events: auto;
            }
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface antialiased min-h-screen">
<?php
$superadmin_nav = 'auditlogs';
$superadmin_header_center = '';
require __DIR__ . '/superadmin_sidebar.php';
require __DIR__ . '/superadmin_header.php';
?>
<button id="sa-mobile-sidebar-toggle" type="button" class="fixed top-6 left-4 z-[65] lg:hidden w-10 h-10 rounded-xl bg-white/90 border border-white text-primary shadow-md flex items-center justify-center" aria-controls="superadmin-sidebar" aria-expanded="false" aria-label="Open navigation menu">
<span class="material-symbols-outlined text-[20px]">menu</span>
</button>
<div id="sa-mobile-sidebar-backdrop" class="lg:hidden" aria-hidden="true"></div>
<!-- Main Content Area -->
<main class="ml-0 lg:ml-64 pt-20 min-h-screen">
<div class="pt-6 sm:pt-8 px-4 sm:px-6 lg:px-10 pb-12 sm:pb-16 space-y-8 sm:space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Header Section -->
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-3xl sm:text-4xl font-extrabold font-headline tracking-tight text-on-surface">Audit Logs</h2>
<p class="text-on-surface-variant mt-2 font-medium">Track and monitor system activities across all clinic modules.</p>
</div>
<div class="flex items-center gap-3 w-full md:w-auto">
<a href="auditlogs_export_pdf.php" target="_blank" rel="noopener noreferrer" class="inline-flex bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow items-center justify-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all no-underline w-full sm:w-auto">
<span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                    PDF Export
                </a>
</div>
</section>
<!-- Metrics Grid -->
<section class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">history</span>
</div>
<span class="text-[10px] font-extrabold text-green-600 bg-green-50 px-2 py-1 rounded-lg uppercase">+12%</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Logs</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($totalLogs); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-error/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-error-container/10 text-error rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">login</span>
</div>
<span class="text-[10px] font-extrabold text-error bg-error-container px-2 py-1 rounded-lg uppercase">Live Data</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Login Events</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($loginEvents); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">logout</span>
</div>
<span class="text-[10px] font-extrabold text-primary bg-primary/5 px-2 py-1 rounded-lg uppercase">Live Data</span>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Logout Events</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($logoutEvents); ?></h3>
</div>
</section>
<!-- Filters Panel -->
<section class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow px-4 sm:px-6 lg:px-8 py-6 space-y-4">
<form method="get" action="<?php echo htmlspecialchars($basePath); ?>" class="space-y-4">
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
<div class="space-y-1.5">
<p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/70">User Type</p>
<div class="relative group">
<select name="user_type" class="w-full bg-surface-container-low/60 border border-white/80 rounded-xl px-4 py-2.5 text-sm font-semibold text-on-surface cursor-pointer hover:bg-white/90 focus:ring-2 focus:ring-primary/20 transition-all">
<option value="all" <?php echo $selectedUserType === 'all' ? 'selected' : ''; ?>>ALL USERS</option>
<option value="super_admin" <?php echo $selectedUserType === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
<option value="tenant" <?php echo $selectedUserType === 'tenant' ? 'selected' : ''; ?>>Tenant</option>
<option value="staff" <?php echo $selectedUserType === 'staff' ? 'selected' : ''; ?>>Staff</option>
<option value="doctor" <?php echo $selectedUserType === 'doctor' ? 'selected' : ''; ?>>Doctor</option>
</select>
</div>
</div>
<div class="space-y-1.5">
<p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/70">Action</p>
<div class="relative group">
<select name="action_type" class="w-full bg-surface-container-low/60 border border-white/80 rounded-xl px-4 py-2.5 text-sm font-semibold text-on-surface cursor-pointer hover:bg-white/90 focus:ring-2 focus:ring-primary/20 transition-all">
<option value="all" <?php echo $selectedActionType === 'all' ? 'selected' : ''; ?>>ALL ACTIONS</option>
<option value="login" <?php echo $selectedActionType === 'login' ? 'selected' : ''; ?>>Login</option>
<option value="logout" <?php echo $selectedActionType === 'logout' ? 'selected' : ''; ?>>Logout</option>
<option value="subscriptions" <?php echo $selectedActionType === 'subscriptions' ? 'selected' : ''; ?>>Subscriptions</option>
</select>
</div>
</div>
<div class="space-y-1.5">
<p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/70">Clinic</p>
<div class="relative group">
<select name="clinic_id" class="w-full bg-surface-container-low/60 border border-white/80 rounded-xl px-4 py-2.5 text-sm font-semibold text-on-surface cursor-pointer hover:bg-white/90 focus:ring-2 focus:ring-primary/20 transition-all">
<option value="all">All Clinics</option>
<?php foreach ($clinicOptions as $clinicOption): ?>
<?php
    $clinicTenantId = (string) ($clinicOption['tenant_id'] ?? '');
    $clinicName = trim((string) ($clinicOption['clinic_name'] ?? ''));
    if ($clinicName === '') {
        $clinicName = $clinicTenantId !== '' ? $clinicTenantId : 'Unnamed Clinic';
    }
?>
<option value="<?php echo htmlspecialchars($clinicTenantId); ?>" <?php echo $selectedClinicId === $clinicTenantId ? 'selected' : ''; ?>><?php echo htmlspecialchars($clinicName); ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="space-y-1.5">
<p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/70">From Date</p>
<div class="relative group">
<input name="from_date" type="date" value="<?php echo htmlspecialchars($selectedFromDate); ?>" class="w-full bg-surface-container-low/60 border border-white/80 rounded-xl px-4 py-2.5 text-sm font-semibold text-on-surface hover:bg-white/90 focus:ring-2 focus:ring-primary/20 transition-all"/>
</div>
</div>
<div class="space-y-1.5">
<p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-on-surface-variant/70">To Date</p>
<div class="relative group">
<input name="to_date" type="date" value="<?php echo htmlspecialchars($selectedToDate); ?>" class="w-full bg-surface-container-low/60 border border-white/80 rounded-xl px-4 py-2.5 text-sm font-semibold text-on-surface hover:bg-white/90 focus:ring-2 focus:ring-primary/20 transition-all"/>
</div>
</div>
</div>
<div class="flex flex-col lg:flex-row lg:items-center gap-3">
<div class="relative w-full group">
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-[20px]">search</span>
<input name="q" value="<?php echo htmlspecialchars($searchTerm); ?>" class="w-full bg-surface-container-low/60 border border-white/80 rounded-xl pl-11 pr-4 py-2.5 text-sm font-medium text-on-surface placeholder:text-on-surface-variant/55 hover:bg-white/90 focus:ring-2 focus:ring-primary/20 transition-all" placeholder="Search by username or email..." type="text"/>
</div>
<button type="submit" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white border border-primary/70 hover:brightness-110 text-sm font-semibold transition-all whitespace-nowrap">
<span class="material-symbols-outlined text-[18px]">filter_alt</span>
                    Apply
                </button>
<a href="<?php echo htmlspecialchars($basePath); ?>" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white/80 text-on-surface-variant border border-white/90 hover:bg-white text-sm font-semibold transition-all whitespace-nowrap no-underline">
<span class="material-symbols-outlined text-[18px]">restart_alt</span>
                    Reset
                </a>
</div>
</form>
</section>
<!-- Table Container -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<!-- Table Content -->
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-8 py-5">User</th>
<th class="px-6 py-5">User Role</th>
<th class="px-6 py-5">Clinic</th>
<th class="px-8 py-5">Action</th>
<th class="px-8 py-5">Date &amp; Time</th>
<th class="px-8 py-5">Status</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if ($dbError !== null): ?>
<tr>
<td class="px-8 py-5 text-sm text-error font-bold" colspan="6"><?php echo htmlspecialchars($dbError); ?></td>
</tr>
<?php elseif (empty($eventRows)): ?>
<tr>
<td class="px-8 py-5 text-sm text-on-surface-variant font-bold" colspan="6">No audit log events found for the selected filters.</td>
</tr>
<?php else: ?>
<?php foreach ($eventRows as $row): ?>
<?php
    $action = (string) ($row['action'] ?? '');
    $lowerAction = strtolower($action);
    $isLogin = strpos($lowerAction, 'login') !== false;
    $isLogout = strpos($lowerAction, 'logout') !== false;
    $isSubscription = strpos($lowerAction, 'subscription') !== false;
    if ($isSubscription) {
        $statusLabel = 'Subscriptions';
        $statusClasses = 'bg-blue-50 text-blue-600';
        $dotClass = 'bg-blue-600';
    } elseif ($isLogout) {
        $statusLabel = 'Logout';
        $statusClasses = 'bg-amber-50 text-amber-600';
        $dotClass = 'bg-amber-600';
    } else {
        $statusLabel = 'Login';
        $statusClasses = 'bg-green-50 text-green-600';
        $dotClass = 'bg-green-600';
    }
    $displayName = trim((string) ($row['full_name'] ?? ''));
    if ($displayName === '') {
        $displayName = trim((string) ($row['user_id'] ?? ''));
    }
    if ($displayName === '') {
        $displayName = 'System';
    }
    $emailAddress = trim((string) ($row['email'] ?? ''));
    $userRoleRaw = strtolower(trim((string) ($row['role'] ?? '')));
    if ($userRoleRaw === 'superadmin') {
        $userRoleLabel = 'Super Admin';
    } elseif ($userRoleRaw === 'tenant_owner' || $userRoleRaw === 'manager') {
        $userRoleLabel = 'Tenant';
    } elseif ($userRoleRaw === 'staff') {
        $userRoleLabel = 'Staff';
    } elseif ($userRoleRaw === 'dentist') {
        $userRoleLabel = 'Doctor';
    } elseif ($userRoleRaw !== '') {
        $userRoleLabel = ucwords(str_replace('_', ' ', $userRoleRaw));
    } else {
        $userRoleLabel = 'N/A';
    }
    $clinicName = trim((string) ($row['clinic_name'] ?? ''));
    if ($clinicName === '') {
        $clinicName = 'N/A';
    }

    $createdAtRaw = trim((string) ($row['created_at'] ?? ''));
    // If MySQL returns microseconds, strip them so DateTime parsing is predictable.
    $createdAtRaw = preg_replace('/\.\d+$/', '', $createdAtRaw);

    $fmt = auditlogs_format_created_at_manila($createdAtRaw, $auditLogStorageTz);
    $createdAtDate = $fmt['date'];
    $createdAtTime = $fmt['time'];
?>
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-8 py-5">
<div>
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($displayName); ?></p>
<?php if ($emailAddress !== ''): ?>
<p class="text-[10px] text-on-surface-variant/75 font-semibold"><?php echo htmlspecialchars($emailAddress); ?></p>
<?php endif; ?>
<p class="text-[10px] text-on-surface-variant font-bold uppercase tracking-wider">
<?php echo htmlspecialchars((string) ($row['ip_address'] ?: 'Unknown IP')); ?>
</p>
</div>
</td>
<td class="px-6 py-5">
<span class="inline-flex items-center rounded-lg px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider bg-surface-container-low text-on-surface-variant"><?php echo htmlspecialchars($userRoleLabel); ?></span>
</td>
<td class="px-6 py-5">
<span class="text-sm font-semibold text-on-surface"><?php echo htmlspecialchars($clinicName); ?></span>
</td>
<td class="px-8 py-5">
<span class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($action); ?></span>
</td>
<td class="px-8 py-5">
<div class="text-xs">
<p class="text-on-surface font-black"><?php echo htmlspecialchars($createdAtDate); ?></p>
<p class="text-on-surface-variant font-bold"><?php echo htmlspecialchars($createdAtTime); ?></p>
</div>
</td>
<td class="px-8 py-5">
<span class="px-3 py-1.5 <?php echo $statusClasses; ?> rounded-xl text-[10px] font-bold uppercase tracking-wider flex items-center w-fit gap-1.5">
<span class="w-1.5 h-1.5 rounded-full <?php echo $dotClass; ?>"></span> <?php echo $statusLabel; ?>
</span>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<!-- Pagination -->
<div class="px-4 sm:px-6 lg:px-10 py-6 border-t border-white/50 space-y-4">
<div class="flex flex-wrap items-center justify-between gap-3">
<?php $isFirstPage = $currentPage <= 1; ?>
<?php $isLastPage = $currentPage >= $totalPages; ?>
<?php if ($isFirstPage): ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant/50 text-sm font-bold rounded-xl border border-white/70 shadow-sm flex items-center gap-2 cursor-not-allowed">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
</span>
<?php else: ?>
<a href="<?php echo htmlspecialchars($buildPageUrl($currentPage - 1)); ?>" class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2 no-underline">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
</a>
<?php endif; ?>
<div class="flex items-center gap-2">
<?php foreach ($paginationItems as $pageItem): ?>
<?php if ($pageItem === '...'): ?>
<span class="px-2 opacity-40">...</span>
<?php elseif ((int) $pageItem === $currentPage): ?>
<span class="w-10 h-10 bg-primary text-white rounded-xl font-bold text-sm active-glow inline-flex items-center justify-center"><?php echo (int) $pageItem; ?></span>
<?php else: ?>
<a href="<?php echo htmlspecialchars($buildPageUrl((int) $pageItem)); ?>" class="w-10 h-10 bg-white/40 text-on-surface-variant hover:bg-white rounded-xl font-bold text-sm transition-all inline-flex items-center justify-center no-underline"><?php echo (int) $pageItem; ?></a>
<?php endif; ?>
<?php endforeach; ?>
</div>
<?php if ($isLastPage): ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant/50 text-sm font-bold rounded-xl border border-white/70 shadow-sm flex items-center gap-2 cursor-not-allowed">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
</span>
<?php else: ?>
<a href="<?php echo htmlspecialchars($buildPageUrl($currentPage + 1)); ?>" class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2 no-underline">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
</a>
<?php endif; ?>
</div>
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60 text-center sm:text-right">
                    Showing <span class="text-primary opacity-100"><?php echo $totalEventRows === 0 ? '0' : (number_format($showingFrom) . '-' . number_format($showingTo)); ?></span> of <?php echo number_format($totalEventRows); ?> results
                </div>
</div>
</div>
</div>
</main>
<script>
(function () {
    var toggleBtn = document.getElementById('sa-mobile-sidebar-toggle');
    var backdrop = document.getElementById('sa-mobile-sidebar-backdrop');
    var mqDesktop = window.matchMedia('(min-width: 1024px)');
    if (!toggleBtn || !backdrop) return;

    function setOpen(isOpen) {
        document.body.classList.toggle('sa-mobile-sidebar-open', isOpen);
        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        toggleBtn.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
        var icon = toggleBtn.querySelector('.material-symbols-outlined');
        if (icon) icon.textContent = isOpen ? 'close' : 'menu';
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    toggleBtn.addEventListener('click', function () {
        setOpen(!document.body.classList.contains('sa-mobile-sidebar-open'));
    });
    backdrop.addEventListener('click', function () {
        setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sa-mobile-sidebar-open')) {
            setOpen(false);
        }
    });

    function closeOnDesktop() {
        if (mqDesktop.matches) setOpen(false);
    }
    if (typeof mqDesktop.addEventListener === 'function') {
        mqDesktop.addEventListener('change', closeOnDesktop);
    } else if (typeof mqDesktop.addListener === 'function') {
        mqDesktop.addListener(closeOnDesktop);
    }
})();
</script>
</body></html>