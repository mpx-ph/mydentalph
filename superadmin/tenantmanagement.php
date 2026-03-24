<?php
require_once __DIR__ . '/require_superadmin.php';
require_once __DIR__ . '/../db.php';

function tenant_tm_build_query(array $base, array $overrides = []): string {
    $merged = array_merge($base, $overrides);
    $parts = [];
    foreach ($merged as $k => $v) {
        if ($v === '' || $v === null) {
            continue;
        }
        if ($k === 'page' && (int) $v === 1) {
            continue;
        }
        $parts[$k] = $v;
    }
    return http_build_query($parts);
}

function tenant_tm_url(array $base, array $overrides = []): string {
    $q = tenant_tm_build_query($base, $overrides);
    return $q === '' ? 'tenantmanagement.php' : ('tenantmanagement.php?' . $q);
}

function tenant_tm_last_activity(?string $ts): string {
    if ($ts === null || $ts === '') {
        return '—';
    }
    $t = strtotime($ts);
    if ($t === false) {
        return '—';
    }
    $diff = time() - $t;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ($m === 1 ? ' min ago' : ' mins ago');
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ($h === 1 ? ' hour ago' : ' hours ago');
    }
    if ($diff < 86400 * 60) {
        $d = (int) floor($diff / 86400);
        return $d . ($d === 1 ? ' day ago' : ' days ago');
    }
    return date('M j, Y', $t);
}

function tenant_tm_initials(string $name): string {
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    $sub = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $up = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        $a = $sub($parts[0], 0, 1);
        $b = $sub($parts[count($parts) - 1], 0, 1);
        return $up($a . $b);
    }
    return $up($sub($name, 0, min(2, $len)));
}

$filterBase = [
    'status' => isset($_GET['status']) ? (string) $_GET['status'] : '',
    'plan' => isset($_GET['plan']) ? (string) $_GET['plan'] : '',
    'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : '',
];
$page = max(1, (int) (isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 10;

$totalTenants = 0;
$activeTenants = 0;
$inactiveTenants = 0;
$suspendedTenants = 0;
$plans = [];
$tenants = [];
$tenantWorkforce = [];
$totalRows = 0;
$totalPages = 1;
$dbError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tenant_action'], $_POST['tenant_id'])) {
    $action = (string) $_POST['tenant_action'];
    $tid = (string) $_POST['tenant_id'];
    $map = [
        'publish' => 'active',
        'unpublish' => 'inactive',
        'suspend' => 'suspended',
    ];
    if (isset($map[$action]) && $tid !== '' && strlen($tid) <= 20) {
        try {
            $chk = $pdo->prepare('SELECT 1 FROM tbl_tenants WHERE tenant_id = ? LIMIT 1');
            $chk->execute([$tid]);
            if ($chk->fetchColumn()) {
                $u = $pdo->prepare('UPDATE tbl_tenants SET subscription_status = ? WHERE tenant_id = ?');
                $u->execute([$map[$action], $tid]);
            }
        } catch (Throwable $e) {
            // ignore; redirect still shows list
        }
    }
    $redir = tenant_tm_url($filterBase, ['page' => $page]);
    header('Location: ' . $redir, true, 303);
    exit;
}

try {
    $totalTenants = (int) $pdo->query('SELECT COUNT(*) FROM tbl_tenants')->fetchColumn();
    $activeTenants = (int) $pdo->query("SELECT COUNT(*) FROM tbl_tenants WHERE subscription_status = 'active'")->fetchColumn();
    $inactiveTenants = (int) $pdo->query("SELECT COUNT(*) FROM tbl_tenants WHERE subscription_status = 'inactive'")->fetchColumn();
    $suspendedTenants = (int) $pdo->query("SELECT COUNT(*) FROM tbl_tenants WHERE subscription_status = 'suspended'")->fetchColumn();

    $plans = $pdo->query('SELECT plan_id, plan_name FROM tbl_subscription_plans ORDER BY plan_name ASC')->fetchAll(PDO::FETCH_ASSOC);

    $where = ['1=1'];
    $params = [];

    $allowedStatus = ['active', 'inactive', 'suspended'];
    if ($filterBase['status'] !== '' && in_array($filterBase['status'], $allowedStatus, true)) {
        $where[] = 't.subscription_status = ?';
        $params[] = $filterBase['status'];
    }

    if ($filterBase['plan'] !== '' && ctype_digit($filterBase['plan'])) {
        $where[] = 'EXISTS (SELECT 1 FROM tbl_tenant_subscriptions tsf WHERE tsf.tenant_id = t.tenant_id AND tsf.plan_id = ?)';
        $params[] = (int) $filterBase['plan'];
    }

    if ($filterBase['q'] !== '') {
        $like = '%' . $filterBase['q'] . '%';
        $where[] = '(t.clinic_name LIKE ? OR t.tenant_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "
        SELECT COUNT(*)
        FROM tbl_tenants t
        LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
        WHERE {$whereSql}
    ";
    $cstmt = $pdo->prepare($countSql);
    $cstmt->execute($params);
    $totalRows = (int) $cstmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $listSql = "
        SELECT
            t.tenant_id,
            t.clinic_name,
            t.subscription_status,
            u.full_name AS owner_name,
            u.email AS owner_email,
            u.photo AS owner_photo,
            u.last_login AS owner_last_login,
            sp.plan_name
        FROM tbl_tenants t
        LEFT JOIN tbl_users u ON u.user_id = t.owner_user_id
        LEFT JOIN tbl_tenant_subscriptions ts ON ts.id = (
            SELECT MAX(ts2.id) FROM tbl_tenant_subscriptions ts2 WHERE ts2.tenant_id = t.tenant_id
        )
        LEFT JOIN tbl_subscription_plans sp ON sp.plan_id = ts.plan_id
        WHERE {$whereSql}
        ORDER BY t.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $lstmt = $pdo->prepare($listSql);
    $lstmt->execute($params);
    $tenants = $lstmt->fetchAll(PDO::FETCH_ASSOC);

    $workforceSql = "
        SELECT
            t.tenant_id,
            t.clinic_name,
            COALESCE(SUM(CASE WHEN u.role = 'staff' THEN 1 ELSE 0 END), 0) AS staff_count,
            COALESCE(SUM(CASE WHEN u.role = 'dentist' THEN 1 ELSE 0 END), 0) AS doctor_count
        FROM tbl_tenants t
        LEFT JOIN tbl_users u ON u.tenant_id = t.tenant_id
        GROUP BY t.tenant_id, t.clinic_name
        ORDER BY t.clinic_name ASC
    ";
    $tenantWorkforce = $pdo->query($workforceSql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

if (!isset($offset)) {
    $offset = ($page - 1) * $perPage;
}
$rangeStart = $totalRows === 0 ? 0 : $offset + 1;
$rangeEnd = $totalRows === 0 ? 0 : min($totalRows, $offset + count($tenants));
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Tenant Management | Clinical Precision</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet"/>
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
                    },
                    fontFamily: {
                        "headline": ["Plus Jakarta Sans", "Inter", "sans-serif"],
                        "body": ["Plus Jakarta Sans", "Inter", "sans-serif"],
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
        .export-modal-backdrop {
            background: rgba(19, 28, 37, 0.35);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }
        .export-modal-panel {
            background: rgba(255, 255, 255, 0.92);
            color: #131c25;
            border: 1px solid rgba(224, 233, 246, 0.85);
            box-shadow: 0 30px 80px -25px rgba(19, 28, 37, 0.25);
        }
        .export-option-card {
            background: rgba(237, 244, 255, 0.7);
            border: 1px solid rgba(192, 199, 212, 0.45);
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-surface antialiased min-h-screen">
<?php
$superadmin_nav = 'tenantmanagement';
require __DIR__ . '/superadmin_sidebar.php';
ob_start();
?>
<form method="get" action="tenantmanagement.php" class="relative w-full max-w-md group">
<?php if ($filterBase['status'] !== ''): ?>
<input type="hidden" name="status" value="<?php echo htmlspecialchars($filterBase['status'], ENT_QUOTES, 'UTF-8'); ?>"/>
<?php endif; ?>
<?php if ($filterBase['plan'] !== ''): ?>
<input type="hidden" name="plan" value="<?php echo htmlspecialchars($filterBase['plan'], ENT_QUOTES, 'UTF-8'); ?>"/>
<?php endif; ?>
<span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant group-focus-within:text-primary transition-colors text-xl pointer-events-none">search</span>
<input name="q" value="<?php echo htmlspecialchars($filterBase['q'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-surface-container-low/50 border-none focus:ring-2 focus:ring-primary/20 rounded-2xl pl-11 pr-4 py-2.5 text-sm transition-all placeholder:text-on-surface-variant/50" placeholder="Search tenants, clinics, or email..." type="search" autocomplete="off"/>
</form>
<?php
$superadmin_header_center = ob_get_clean();
require __DIR__ . '/superadmin_header.php';
?>
<!-- Main Content Area -->
<main class="ml-64 pt-20 min-h-screen">
<div class="pt-8 px-10 pb-16 space-y-10 relative">
<!-- Decorative blur shape -->
<div class="absolute top-40 right-10 w-96 h-96 bg-primary/5 rounded-full blur-[100px] -z-10"></div>
<!-- Header Section -->
<section class="flex flex-col md:flex-row md:items-end justify-between gap-4">
<div>
<h2 class="text-4xl font-extrabold font-headline tracking-tight text-on-surface">Tenant Management</h2>
<p class="text-on-surface-variant mt-2 font-medium">View and manage clinic tenant accounts across the network.</p>
</div>
<div class="flex items-center gap-3">
<button id="open-tenant-export-modal" type="button" class="bg-primary text-white px-7 py-2.5 rounded-2xl text-sm font-bold primary-glow flex items-center gap-2 hover:translate-y-[-2px] hover:brightness-110 active:translate-y-0 transition-all">
<span class="material-symbols-outlined text-lg">picture_as_pdf</span>
                    PDF Export
                </button>
</div>
</section>
<?php if ($dbError !== null): ?>
<div class="rounded-[2rem] bg-error/10 border border-error/20 px-8 py-4 text-error text-sm font-medium">
    Could not load tenant data. Please try again or check the database connection.
</div>
<?php endif; ?>
<!-- Metrics Bento Grid (Styled like SCREEN_2 cards) -->
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">corporate_fare</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Total Tenants</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($totalTenants); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-blue-50 text-primary rounded-xl shadow-sm">
<span class="material-symbols-outlined">check_circle</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Active Tenants</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($activeTenants); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-slate-200">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-slate-100 text-slate-600 rounded-xl shadow-sm">
<span class="material-symbols-outlined">pause_circle</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Inactive Account</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($inactiveTenants); ?></h3>
</div>
<div class="bg-white/60 backdrop-blur-md p-8 rounded-[2rem] editorial-shadow group hover:-translate-y-1 transition-all border-r-4 border-error/20">
<div class="flex justify-between items-start mb-4">
<div class="p-2.5 bg-error-container/10 text-error rounded-xl shadow-sm">
<span class="material-symbols-outlined">warning</span>
</div>
</div>
<p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-widest opacity-60">Suspended Accounts</p>
<h3 class="text-3xl font-extrabold text-on-surface mt-1.5 font-headline"><?php echo number_format($suspendedTenants); ?></h3>
</div>
</section>
<!-- Main Data Table Container (Glassmorphism & Style from SCREEN_2) -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<!-- Table Controls -->
<div class="px-8 py-6 flex flex-wrap items-center justify-between gap-4 border-b border-white/50">
<form method="get" action="tenantmanagement.php" class="flex flex-wrap items-center gap-4 flex-1 min-w-0">
<?php if ($filterBase['q'] !== ''): ?>
<input type="hidden" name="q" value="<?php echo htmlspecialchars($filterBase['q'], ENT_QUOTES, 'UTF-8'); ?>"/>
<?php endif; ?>
<div class="relative group">
<select name="status" onchange="this.form.submit()" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option value=""<?php echo $filterBase['status'] === '' ? ' selected' : ''; ?>>All Status</option>
<option value="active"<?php echo $filterBase['status'] === 'active' ? ' selected' : ''; ?>>Active</option>
<option value="inactive"<?php echo $filterBase['status'] === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
<option value="suspended"<?php echo $filterBase['status'] === 'suspended' ? ' selected' : ''; ?>>Suspended</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">expand_more</span>
</div>
<div class="relative group">
<select name="plan" onchange="this.form.submit()" class="appearance-none bg-surface-container-low/50 border-none rounded-xl px-6 pr-12 py-2.5 text-sm font-bold text-on-surface cursor-pointer hover:bg-white/80 focus:ring-2 focus:ring-primary/20 transition-all">
<option value=""<?php echo $filterBase['plan'] === '' ? ' selected' : ''; ?>>All Plans</option>
<?php foreach ($plans as $p): ?>
<option value="<?php echo (int) $p['plan_id']; ?>"<?php echo $filterBase['plan'] === (string) $p['plan_id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($p['plan_name'], ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-on-surface-variant text-xl">filter_list</span>
</div>
</form>
<div class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">
                    Showing <span class="text-primary opacity-100"><?php echo $totalRows === 0 ? '0' : number_format($rangeStart) . '–' . number_format($rangeEnd); ?></span> of <?php echo number_format($totalRows); ?> tenants
                </div>
</div>
<!-- Table Content -->
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Tenant Name</th>
<th class="px-8 py-5">Clinic Name</th>
<th class="px-8 py-5">Status</th>
<th class="px-8 py-5">Subscription</th>
<th class="px-8 py-5">Last Activity</th>
<th class="px-10 py-5 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if ($dbError !== null): ?>
<tr>
<td colspan="6" class="px-10 py-12 text-center text-sm text-error font-medium">Unable to load tenants.</td>
</tr>
<?php elseif (empty($tenants)): ?>
<tr>
<td colspan="6" class="px-10 py-12 text-center text-sm text-on-surface-variant font-medium">No tenants match your filters.</td>
</tr>
<?php else: ?>
<?php foreach ($tenants as $row):
    $st = (string) ($row['subscription_status'] ?? '');
    $ownerName = trim((string) ($row['owner_name'] ?? ''));
    $displayName = $ownerName !== '' ? $ownerName : '—';
    $email = trim((string) ($row['owner_email'] ?? ''));
    $photo = trim((string) ($row['owner_photo'] ?? ''));
    $planName = trim((string) ($row['plan_name'] ?? ''));
    if ($st === 'active') {
        $badge = '<span class="px-3 py-1.5 bg-green-50 text-green-600 rounded-xl text-[10px] font-bold uppercase tracking-wider">Active</span>';
    } elseif ($st === 'inactive') {
        $badge = '<span class="px-3 py-1.5 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-bold uppercase tracking-wider">Inactive</span>';
    } elseif ($st === 'suspended') {
        $badge = '<span class="px-3 py-1.5 bg-error-container/20 text-error rounded-xl text-[10px] font-bold uppercase tracking-wider">Suspended</span>';
    } else {
        $badge = '<span class="px-3 py-1.5 bg-slate-100 text-slate-500 rounded-xl text-[10px] font-bold uppercase tracking-wider">' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $canPublish = $st !== 'active';
    $canUnpublish = $st === 'active';
    $canSuspend = $st !== 'suspended';
    ?>
<tr class="hover:bg-primary/5 transition-colors group">
<td class="px-10 py-5">
<div class="flex items-center gap-4">
<?php if ($photo !== ''): ?>
<img alt="" class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-sm" src="<?php echo htmlspecialchars($photo, ENT_QUOTES, 'UTF-8'); ?>"/>
<?php else: ?>
<div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-primary text-xs font-bold border-2 border-white shadow-sm"><?php echo htmlspecialchars(tenant_tm_initials($displayName === '—' ? (string) $row['clinic_name'] : $displayName), ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<div>
<p class="text-sm font-bold text-on-surface"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></p>
<p class="text-[10px] text-on-surface-variant font-medium"><?php echo $email !== '' ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '—'; ?></p>
</div>
</div>
</td>
<td class="px-8 py-5">
<span class="text-sm font-semibold text-on-surface-variant"><?php echo htmlspecialchars((string) ($row['clinic_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
</td>
<td class="px-8 py-5">
<?php echo $badge; ?>
</td>
<td class="px-8 py-5">
<?php if ($planName !== ''): ?>
<span class="flex items-center gap-2 text-sm font-bold text-primary">
<span class="material-symbols-outlined text-lg">verified</span>
<?php echo htmlspecialchars($planName, ENT_QUOTES, 'UTF-8'); ?>
</span>
<?php else: ?>
<span class="text-sm font-medium text-on-surface-variant/60">—</span>
<?php endif; ?>
</td>
<td class="px-8 py-5 text-xs font-medium text-on-surface-variant"><?php echo tenant_tm_last_activity($row['owner_last_login'] ?? null); ?></td>
<td class="px-10 py-5 text-right">
<div class="flex justify-end flex-wrap gap-1.5">
<form method="post" action="<?php echo htmlspecialchars(tenant_tm_url($filterBase, ['page' => $page]), ENT_QUOTES, 'UTF-8'); ?>" class="inline">
<input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars((string) $row['tenant_id'], ENT_QUOTES, 'UTF-8'); ?>"/>
<button type="submit" name="tenant_action" value="publish" <?php echo $canPublish ? '' : 'disabled'; ?> class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wide border border-outline-variant/30 <?php echo $canPublish ? 'text-primary hover:bg-primary/10' : 'opacity-40 cursor-not-allowed'; ?>"><span class="material-symbols-outlined text-base">publish</span> Publish</button>
</form>
<form method="post" action="<?php echo htmlspecialchars(tenant_tm_url($filterBase, ['page' => $page]), ENT_QUOTES, 'UTF-8'); ?>" class="inline">
<input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars((string) $row['tenant_id'], ENT_QUOTES, 'UTF-8'); ?>"/>
<button type="submit" name="tenant_action" value="unpublish" <?php echo $canUnpublish ? '' : 'disabled'; ?> class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wide border border-outline-variant/30 <?php echo $canUnpublish ? 'text-on-surface-variant hover:bg-white/80' : 'opacity-40 cursor-not-allowed'; ?>"><span class="material-symbols-outlined text-base">unpublished</span> Unpublish</button>
</form>
<form method="post" action="<?php echo htmlspecialchars(tenant_tm_url($filterBase, ['page' => $page]), ENT_QUOTES, 'UTF-8'); ?>" class="inline">
<input type="hidden" name="tenant_id" value="<?php echo htmlspecialchars((string) $row['tenant_id'], ENT_QUOTES, 'UTF-8'); ?>"/>
<button type="submit" name="tenant_action" value="suspend" <?php echo $canSuspend ? '' : 'disabled'; ?> class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-[10px] font-bold uppercase tracking-wide border border-outline-variant/30 <?php echo $canSuspend ? 'text-error hover:bg-error/10' : 'opacity-40 cursor-not-allowed'; ?>"><span class="material-symbols-outlined text-base">block</span> Suspend</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<!-- Pagination (Matches SCREEN_2 Button Style) -->
<div class="px-10 py-8 flex flex-wrap items-center justify-between gap-4 border-t border-white/50">
<?php if ($page > 1): ?>
<a href="<?php echo htmlspecialchars(tenant_tm_url($filterBase, ['page' => $page - 1]), ENT_QUOTES, 'UTF-8'); ?>" class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </a>
<?php else: ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant text-sm font-bold rounded-xl border border-white/60 shadow-sm flex items-center gap-2 opacity-40 cursor-not-allowed">
<span class="material-symbols-outlined text-lg">chevron_left</span> Previous
                </span>
<?php endif; ?>
<p class="text-sm font-bold text-on-surface order-first sm:order-none w-full sm:w-auto text-center sm:text-left">Page <?php echo (int) $page; ?> of <?php echo (int) $totalPages; ?></p>
<?php if ($page < $totalPages): ?>
<a href="<?php echo htmlspecialchars(tenant_tm_url($filterBase, ['page' => $page + 1]), ENT_QUOTES, 'UTF-8'); ?>" class="px-5 py-2.5 bg-white/60 text-on-surface-variant text-sm font-bold rounded-xl border border-white hover:bg-white transition-all shadow-sm flex items-center gap-2">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
                </a>
<?php else: ?>
<span class="px-5 py-2.5 bg-white/40 text-on-surface-variant text-sm font-bold rounded-xl border border-white/60 shadow-sm flex items-center gap-2 opacity-40 cursor-not-allowed">
                    Next <span class="material-symbols-outlined text-lg">chevron_right</span>
                </span>
<?php endif; ?>
</div>
</div>
<!-- Clinic Workforce Table -->
<div class="bg-white/70 backdrop-blur-xl rounded-[2.5rem] editorial-shadow overflow-hidden">
<div class="px-8 py-6 border-b border-white/50 flex items-center justify-between gap-4">
<div>
<h4 class="text-xl font-extrabold font-headline text-on-surface">Clinic Workforce</h4>
<p class="text-sm text-on-surface-variant mt-1 font-medium">Staff and doctor headcount per tenant clinic.</p>
</div>
<span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70">All Tenants</span>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left">
<thead>
<tr class="text-[10px] font-bold uppercase tracking-[0.15em] text-on-surface-variant/60">
<th class="px-10 py-5">Tenant ID</th>
<th class="px-8 py-5">Clinic Name</th>
<th class="px-8 py-5">Staff Count</th>
<th class="px-10 py-5">Doctor Count</th>
</tr>
</thead>
<tbody class="divide-y divide-white/40">
<?php if ($dbError !== null): ?>
<tr>
<td colspan="4" class="px-10 py-12 text-center text-sm text-error font-medium">Unable to load clinic workforce data.</td>
</tr>
<?php elseif (empty($tenantWorkforce)): ?>
<tr>
<td colspan="4" class="px-10 py-12 text-center text-sm text-on-surface-variant font-medium">No clinic workforce records found.</td>
</tr>
<?php else: ?>
<?php foreach ($tenantWorkforce as $wf): ?>
<tr class="hover:bg-primary/5 transition-colors">
<td class="px-10 py-5 text-sm font-semibold text-on-surface"><?php echo htmlspecialchars((string) $wf['tenant_id'], ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-8 py-5 text-sm font-medium text-on-surface-variant"><?php echo htmlspecialchars((string) $wf['clinic_name'], ENT_QUOTES, 'UTF-8'); ?></td>
<td class="px-8 py-5">
<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-blue-50 text-primary text-xs font-bold">
<span class="material-symbols-outlined text-base">groups</span>
<?php echo number_format((int) $wf['staff_count']); ?>
</span>
</td>
<td class="px-10 py-5">
<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50 text-emerald-700 text-xs font-bold">
<span class="material-symbols-outlined text-base">stethoscope</span>
<?php echo number_format((int) $wf['doctor_count']); ?>
</span>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>
<!-- Tenant Management Export Modal -->
<div id="tenant-export-modal" class="fixed inset-0 z-[70] hidden export-modal-backdrop items-center justify-center p-4 sm:p-8">
<div class="export-modal-panel w-full max-w-3xl rounded-[2rem] overflow-hidden">
<div class="px-8 py-6 border-b border-outline-variant/40 flex items-start justify-between gap-4">
<div>
<h3 class="text-2xl font-extrabold tracking-tight">
<span class="text-on-surface">Export</span> <span class="text-primary">Options</span>
</h3>
<p class="mt-2 text-xs font-bold uppercase tracking-[0.2em] text-on-surface-variant/60">Select sections to include in your PDF</p>
</div>
<button id="close-tenant-export-modal" type="button" class="w-14 h-14 rounded-2xl bg-surface-container-low hover:bg-white transition-colors flex items-center justify-center text-on-surface-variant">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form action="tenantmanagement_export_pdf.php" method="get" target="_blank" class="max-h-[70vh] overflow-y-auto p-8 space-y-7">
<input type="hidden" name="status" value="<?php echo htmlspecialchars($filterBase['status'], ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="plan" value="<?php echo htmlspecialchars($filterBase['plan'], ENT_QUOTES, 'UTF-8'); ?>"/>
<input type="hidden" name="q" value="<?php echo htmlspecialchars($filterBase['q'], ENT_QUOTES, 'UTF-8'); ?>"/>
<div>
<h4 class="text-sm font-bold uppercase tracking-[0.16em] text-on-surface-variant/70 mb-4">Report sections</h4>
<div class="space-y-4">
<label class="export-option-card rounded-3xl p-5 flex items-center gap-3 cursor-pointer">
<input type="hidden" name="include_summary" value="0"/>
<input type="checkbox" name="include_summary" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Summary metrics (total, active, inactive, suspended)</span>
</label>
<label class="export-option-card rounded-3xl p-5 flex items-center gap-3 cursor-pointer">
<input type="hidden" name="include_tenant_directory" value="0"/>
<input type="checkbox" name="include_tenant_directory" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Tenant directory <span class="text-on-surface-variant font-semibold text-sm">(all rows matching current filters)</span></span>
</label>
<label class="export-option-card rounded-3xl p-5 flex items-center gap-3 cursor-pointer">
<input type="hidden" name="include_workforce" value="0"/>
<input type="checkbox" name="include_workforce" value="1" checked class="w-5 h-5 rounded border-outline-variant bg-white text-primary focus:ring-primary/30"/>
<span class="font-extrabold text-on-surface">Clinic workforce (staff &amp; doctor counts)</span>
</label>
</div>
</div>
<p class="text-xs text-on-surface-variant font-medium">Filters from this page (status, plan, search) apply to the tenant directory in the PDF.</p>
<div class="pt-2 flex justify-end gap-3">
<button type="button" id="cancel-tenant-export-modal" class="px-6 py-3 rounded-2xl text-sm font-bold text-on-surface-variant bg-surface-container-low hover:bg-white transition-colors">Cancel</button>
<button type="submit" class="px-7 py-3 rounded-2xl text-sm font-bold text-white bg-primary hover:brightness-110 transition-colors">Preview PDF</button>
</div>
</form>
</div>
</div>
<script>
(function () {
    var openBtn = document.getElementById('open-tenant-export-modal');
    var closeBtn = document.getElementById('close-tenant-export-modal');
    var cancelBtn = document.getElementById('cancel-tenant-export-modal');
    var modal = document.getElementById('tenant-export-modal');
    if (!openBtn || !modal) return;
    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
    }
    openBtn.addEventListener('click', openModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });
})();
</script>
</body></html>