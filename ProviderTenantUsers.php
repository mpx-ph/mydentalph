<?php
declare(strict_types=1);
require_once __DIR__ . '/provider_tenant_lite_bootstrap.php';

// Dynamic roster must always reflect the database; avoid cached HTML after adds/removals.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

$provider_nav_active = 'users';

$owner_prefill_full = trim($display_name !== '' ? $display_name : (string) ($_SESSION['full_name'] ?? $_SESSION['name'] ?? ''));
$owner_prefill_parts = preg_split('/\s+/', $owner_prefill_full, -1, PREG_SPLIT_NO_EMPTY);
$owner_prefill_first = '';
$owner_prefill_last = '';
if (is_array($owner_prefill_parts) && $owner_prefill_parts !== []) {
    $owner_prefill_first = (string) array_shift($owner_prefill_parts);
    $owner_prefill_last = trim(implode(' ', $owner_prefill_parts));
}
$owner_prefill_email = trim((string) ($_SESSION['email'] ?? ''));
if ($owner_prefill_email === '') {
    try {
        $st = $pdo->prepare('SELECT email FROM tbl_users WHERE user_id = ? LIMIT 1');
        $st->execute([$user_id]);
        $er = $st->fetch(PDO::FETCH_ASSOC);
        if (is_array($er)) {
            $owner_prefill_email = trim((string) ($er['email'] ?? ''));
        }
    } catch (Throwable $e) {
        // keep empty
    }
}
$add_user_owner_prefill_json = json_encode(
    [
        'first' => $owner_prefill_first,
        'last' => $owner_prefill_last,
        'email' => $owner_prefill_email,
    ],
    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
);
if ($add_user_owner_prefill_json === false) {
    $add_user_owner_prefill_json = '{"first":"","last":"","email":""}';
}

require_once __DIR__ . '/provider_tenant_plan_and_site_context.inc.php';
require_once __DIR__ . '/provider_tenant_header_context.inc.php';

/**
 * @return list<array<string, mixed>>
 */
function provider_tenant_fetch_team_members(PDO $pdo, string $tenant_id): array
{
    try {
        // Dentist profile is resolved via scalar subqueries (ORDER BY dentist_id LIMIT 1) so multiple
        // tbl_dentists rows for the same email cannot multiply tbl_users rows (which looked like stale rows).
        $st = $pdo->prepare(
            'SELECT u.user_id, u.email, u.full_name, u.role, u.status, u.last_active, u.last_login, u.updated_at,
                    s.profile_image,
                    COALESCE(
                        NULLIF(TRIM(s.first_name), \'\'),
                        NULLIF(TRIM((
                            SELECT d.first_name FROM tbl_dentists d
                            WHERE d.tenant_id = u.tenant_id
                              AND u.role = \'dentist\'
                              AND LOWER(TRIM(COALESCE(d.email, \'\'))) = LOWER(TRIM(COALESCE(u.email, \'\')))
                            ORDER BY d.dentist_id ASC
                            LIMIT 1
                        )), \'\')
                    ) AS staff_first,
                    COALESCE(
                        NULLIF(TRIM(s.last_name), \'\'),
                        NULLIF(TRIM((
                            SELECT d.last_name FROM tbl_dentists d
                            WHERE d.tenant_id = u.tenant_id
                              AND u.role = \'dentist\'
                              AND LOWER(TRIM(COALESCE(d.email, \'\'))) = LOWER(TRIM(COALESCE(u.email, \'\')))
                            ORDER BY d.dentist_id ASC
                            LIMIT 1
                        )), \'\')
                    ) AS staff_last
             FROM tbl_users u
             LEFT JOIN tbl_staffs s ON s.tenant_id = u.tenant_id AND s.user_id = u.user_id
             WHERE u.tenant_id = ?
               AND u.role NOT IN (\'client\', \'superadmin\')
             ORDER BY u.full_name ASC'
        );
        $st->execute([$tenant_id]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        $byUserId = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $uid = trim((string) ($row['user_id'] ?? ''));
            if ($uid === '') {
                continue;
            }
            if (!isset($byUserId[$uid])) {
                $byUserId[$uid] = $row;
            }
        }
        return array_values($byUserId);
    } catch (Throwable $e) {
        return [];
    }
}

function provider_tenant_user_role_label(string $role): string
{
    return match ($role) {
        'tenant_owner' => 'Clinic owner',
        'manager' => 'Manager',
        'staff' => 'Staff',
        'dentist' => 'Doctor',
        default => ucwords(str_replace('_', ' ', $role)),
    };
}

function provider_tenant_format_last_activity(?string $lastActive, ?string $lastLogin, ?string $updatedAt): string
{
    $raw = trim((string) ($lastActive ?? ''));
    if ($raw === '') {
        $raw = trim((string) ($lastLogin ?? ''));
    }
    if ($raw === '') {
        $raw = trim((string) ($updatedAt ?? ''));
    }
    if ($raw === '') {
        return '—';
    }
    $t = strtotime($raw);
    if ($t === false) {
        return '—';
    }
    return date('M j, Y g:i A', $t);
}

function provider_tenant_user_initials(string $fullName): string
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return '?';
    }
    $parts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($parts) || $parts === []) {
        return strtoupper(substr($fullName, 0, 2));
    }
    $a = strtoupper(substr($parts[0], 0, 1));
    $b = isset($parts[1]) ? strtoupper(substr($parts[1], 0, 1)) : strtoupper(substr($parts[0], 1, 1));
    $out = $a . ($b !== '' ? $b : '');
    return strlen($out) > 2 ? substr($out, 0, 2) : $out;
}

function provider_tenant_profile_image_url(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }
    if ($path[0] === '/') {
        return $path;
    }
    return '/' . ltrim($path, '/');
}

$team_members_all = provider_tenant_fetch_team_members($pdo, $tenant_id);
$team_total_all = count($team_members_all);
$team_active_all = 0;
foreach ($team_members_all as $tm) {
    if (($tm['status'] ?? '') === 'active') {
        $team_active_all++;
    }
}

$filter_role = strtolower(trim((string) ($_GET['role'] ?? 'all')));
$filter_status = strtolower(trim((string) ($_GET['status'] ?? 'all')));
$allowed_filter_roles = ['all', 'tenant_owner', 'manager', 'staff', 'dentist'];
if (!in_array($filter_role, $allowed_filter_roles, true)) {
    $filter_role = 'all';
}
$allowed_filter_status = ['all', 'active', 'inactive', 'suspended'];
if (!in_array($filter_status, $allowed_filter_status, true)) {
    $filter_status = 'all';
}

$team_members = array_values(array_filter($team_members_all, static function (array $row) use ($filter_role, $filter_status): bool {
    if ($filter_role !== 'all' && (string) ($row['role'] ?? '') !== $filter_role) {
        return false;
    }
    if ($filter_status !== 'all' && strtolower((string) ($row['status'] ?? '')) !== $filter_status) {
        return false;
    }
    return true;
}));

$team_active_count = 0;
foreach ($team_members as $tm) {
    if (($tm['status'] ?? '') === 'active') {
        $team_active_count++;
    }
}
$team_total = count($team_members);
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>MyDental | Users</title>
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&amp;family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
<!-- Material Symbols -->
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "surface-variant": "#f1f5f9",
                        "on-background": "#101922",
                        "surface": "#ffffff",
                        "outline-variant": "#e2e8f0",
                        "primary": "#2b8beb",
                        "on-surface-variant": "#475569",
                        "background": "#f8fafc",
                        "surface-container-low": "#edf4ff",
                        "surface-container-lowest": "#ffffff",
                        "tertiary": "#8e4a00",
                        "tertiary-container": "#ffdcc3",
                        "error": "#ba1a1a"
                    },
                    fontFamily: {
                        "headline": ["Manrope", "sans-serif"],
                        "body": ["Manrope", "sans-serif"],
                        "editorial": ["Playfair Display", "serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "1rem",
                        "2xl": "1.5rem",
                        "3xl": "2.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        html {
            scrollbar-gutter: stable;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .sidebar-glass {
            background: rgba(252, 253, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-right: 1px solid rgba(224, 233, 246, 0.5);
        }
        .mesh-bg {
            background-color: #f7f9ff;
            background-image:
                radial-gradient(at 0% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%),
                radial-gradient(at 50% 0%, hsla(217, 100%, 94%, 1) 0, transparent 50%),
                radial-gradient(at 100% 0%, hsla(210, 100%, 98%, 1) 0, transparent 50%);
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .active-glow {
            box-shadow: 0 0 20px -5px rgba(43, 139, 235, 0.4);
        }
        .provider-nav-link:not(.provider-nav-link--active):hover {
            transform: translateX(4px);
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .provider-card-lift {
            transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease;
        }
        .provider-card-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -12px rgba(15, 23, 42, 0.12);
        }
        @keyframes provider-modal-in {
            from { opacity: 0; transform: scale(0.94) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .provider-modal-panel {
            animation: provider-modal-in 0.4s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .provider-modal-backdrop {
            animation: provider-page-in 0.35s ease forwards;
        }
        body { font-family: 'Manrope', sans-serif; }
        .add-user-modal-scroll {
            scrollbar-width: thin;
            scrollbar-color: rgba(43, 139, 235, 0.35) transparent;
        }
        .add-user-modal-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .add-user-modal-scroll::-webkit-scrollbar-thumb {
            background: rgba(43, 139, 235, 0.35);
            border-radius: 9999px;
        }
        .add-user-switch-track {
            width: 2.75rem;
            height: 1.5rem;
            border-radius: 9999px;
            background: #e2e8f0;
            transition: background 0.2s ease;
        }
        .add-user-switch-thumb {
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 9999px;
            background: #fff;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
            transition: transform 0.2s cubic-bezier(0.22, 1, 0.36, 1);
        }
        input.add-user-switch-input:checked + .add-user-switch-track {
            background: #2b8beb;
        }
        input.add-user-switch-input:checked + .add-user-switch-track .add-user-switch-thumb {
            transform: translateX(1.25rem);
        }
        input.add-user-switch-input:focus-visible + .add-user-switch-track {
            outline: 2px solid rgba(43, 139, 235, 0.45);
            outline-offset: 2px;
        }
        .add-user-otp-input {
            letter-spacing: 0.4em;
            font-variant-numeric: tabular-nums;
            text-indent: 0.15em;
        }
    </style>
</head>
<body class="mesh-bg font-body text-on-background min-h-screen selection:bg-primary/10">
<?php include __DIR__ . '/provider_tenant_sidebar.inc.php'; ?>
<?php include __DIR__ . '/provider_tenant_top_header.inc.php'; ?>
<main class="ml-64 pt-[4.5rem] sm:pt-24 min-h-screen provider-page-enter">
<div class="pt-4 sm:pt-6 px-6 lg:px-10 pb-20 space-y-8">
<section class="flex flex-col gap-6">
<div class="flex flex-col gap-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]"><span class="w-12 h-[1.5px] bg-primary"></span> Team Management</div>
<div class="flex justify-between items-end">
<div>
<h2 class="font-headline font-extrabold tracking-tighter leading-tight text-on-background text-6xl">Team <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span></h2>
<p class="font-body text-xl font-medium text-slate-600 max-w-3xl leading-relaxed mt-6">Manage practitioner access and administrative permissions for your clinic.</p>
</div>
<button type="button" id="add-user-open" class="bg-primary text-white px-8 py-4 rounded-2xl font-black text-xs uppercase tracking-widest hover:shadow-xl hover:shadow-primary/25 transition-all duration-300 active:scale-95 hover:scale-[1.02] flex items-center gap-2">
<span class="material-symbols-outlined text-base">person_add</span>
                        Add New User
                    </button>
</div>
</div>
<form class="flex flex-wrap gap-4 items-center justify-between pt-8 border-t border-slate-100" method="get" action="">
<div class="flex flex-wrap gap-4">
<div class="relative">
<label class="sr-only" for="filter-role">Role</label>
<select id="filter-role" name="role" class="appearance-none bg-slate-50 border border-slate-200 rounded-2xl px-8 py-3.5 pr-12 text-on-background text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 cursor-pointer transition-all" onchange="this.form.submit()">
<option value="all"<?php echo $filter_role === 'all' ? ' selected' : ''; ?>>All roles</option>
<option value="tenant_owner"<?php echo $filter_role === 'tenant_owner' ? ' selected' : ''; ?>>Clinic owner</option>
<option value="manager"<?php echo $filter_role === 'manager' ? ' selected' : ''; ?>>Manager</option>
<option value="staff"<?php echo $filter_role === 'staff' ? ' selected' : ''; ?>>Staff</option>
<option value="dentist"<?php echo $filter_role === 'dentist' ? ' selected' : ''; ?>>Doctor</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">expand_more</span>
</div>
<div class="relative">
<label class="sr-only" for="filter-status">Status</label>
<select id="filter-status" name="status" class="appearance-none bg-slate-50 border border-slate-200 rounded-2xl px-8 py-3.5 pr-12 text-on-background text-[10px] font-black uppercase tracking-widest focus:ring-2 focus:ring-primary/20 cursor-pointer transition-all" onchange="this.form.submit()">
<option value="all"<?php echo $filter_status === 'all' ? ' selected' : ''; ?>>All statuses</option>
<option value="active"<?php echo $filter_status === 'active' ? ' selected' : ''; ?>>Active</option>
<option value="inactive"<?php echo $filter_status === 'inactive' ? ' selected' : ''; ?>>Inactive</option>
<option value="suspended"<?php echo $filter_status === 'suspended' ? ' selected' : ''; ?>>Suspended</option>
</select>
<span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-primary text-lg">filter_list</span>
</div>
</div>
<div class="bg-primary/5 px-6 py-2.5 rounded-full border border-primary/10">
<p class="text-primary text-[10px] font-black uppercase tracking-widest">
                        Showing <span class="text-slate-900"><?php echo (int) $team_total; ?></span> of <span class="text-slate-900"><?php echo (int) $team_total_all; ?></span> · <span class="text-slate-900"><?php echo (int) $team_active_count; ?></span> active in view · <span class="text-slate-900"><?php echo (int) $team_active_all; ?></span> active clinic-wide
                    </p>
</div>
</form>
</section>
<!-- Table Card -->
<div class="elevated-card provider-card-lift rounded-3xl overflow-hidden">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50 border-b border-slate-100">
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Practitioner</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Security Role</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Clinic Status</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60">Last Activity</th>
<th class="px-10 py-6 text-[10px] font-black uppercase tracking-widest text-on-surface-variant/60 text-right">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100">
<?php if ($team_total_all === 0) { ?>
<tr>
<td colspan="5" class="px-10 py-16 text-center text-on-surface-variant font-medium">No team members yet. Use <span class="text-primary font-bold">Add New User</span> to invite staff.</td>
</tr>
<?php } elseif ($team_total === 0) { ?>
<tr>
<td colspan="5" class="px-10 py-16 text-center text-on-surface-variant font-medium">No team members match these filters. <a class="text-primary font-bold hover:underline" href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] ?? 'ProviderTenantUsers.php', ENT_QUOTES, 'UTF-8'); ?>">Clear filters</a></td>
</tr>
<?php } else { ?>
<?php foreach ($team_members as $row) {
    $uid = (string) ($row['user_id'] ?? '');
    $email = (string) ($row['email'] ?? '');
    $fullName = trim((string) ($row['full_name'] ?? ''));
    if ($fullName === '') {
        $sf = trim((string) ($row['staff_first'] ?? ''));
        $sl = trim((string) ($row['staff_last'] ?? ''));
        $fullName = trim($sf . ' ' . $sl) ?: '—';
    }
    $role = (string) ($row['role'] ?? '');
    $roleLabel = provider_tenant_user_role_label($role);
    $status = strtolower((string) ($row['status'] ?? ''));
    $isActive = $status === 'active';
    $lastLine = provider_tenant_format_last_activity(
        isset($row['last_active']) ? (string) $row['last_active'] : null,
        isset($row['last_login']) ? (string) $row['last_login'] : null,
        isset($row['updated_at']) ? (string) $row['updated_at'] : null
    );
    $imgUrl = provider_tenant_profile_image_url(isset($row['profile_image']) ? (string) $row['profile_image'] : null);
    $initials = provider_tenant_user_initials($fullName);
    $roleBadgeClass = ($role === 'tenant_owner' || $role === 'dentist')
        ? 'bg-primary/10 text-primary'
        : 'bg-slate-100 text-on-surface-variant';
    $statusDotClass = $isActive ? 'bg-green-500 animate-pulse' : ($status === 'suspended' ? 'bg-rose-500' : 'bg-amber-400');
    $statusLabel = $isActive ? 'Active' : ucfirst($status !== '' ? $status : 'Unknown');
    ?>
<tr class="group hover:bg-slate-50/50 transition-colors duration-200">
<td class="px-10 py-8">
<div class="flex items-center gap-4">
<div class="w-12 h-12 rounded-2xl bg-primary/10 overflow-hidden ring-2 ring-primary/5 transition-transform duration-300 group-hover:scale-105 group-hover:ring-primary/20 flex items-center justify-center shrink-0">
<?php if ($imgUrl !== null) { ?>
<img class="w-full h-full object-cover" src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" alt=""/>
<?php } else { ?>
<span class="text-sm font-black text-primary"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
<?php } ?>
</div>
<div class="min-w-0">
<div class="font-headline font-extrabold text-slate-900 truncate"><?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?></div>
<div class="text-[11px] font-medium text-on-surface-variant/70 mt-0.5 truncate"><?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?></div>
</div>
</div>
</td>
<td class="px-10 py-8">
<span class="<?php echo $roleBadgeClass; ?> text-[9px] font-black px-3 py-1.5 rounded-lg uppercase tracking-widest inline-block"><?php echo htmlspecialchars($roleLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</td>
<td class="px-10 py-8">
<div class="flex items-center gap-2">
<span class="w-2 h-2 rounded-full <?php echo $statusDotClass; ?>"></span>
<span class="text-[10px] font-black text-on-surface-variant/70 uppercase tracking-widest"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
</div>
</td>
<td class="px-10 py-8">
<div class="text-[11px] font-black text-on-surface-variant/70 uppercase tracking-widest"><?php echo htmlspecialchars($lastLine, ENT_QUOTES, 'UTF-8'); ?></div>
</td>
<td class="px-10 py-8 text-right">
<div class="flex justify-end gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-600 hover:border-primary/30 hover:text-primary flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110 hover:shadow-md" title="Edit (coming soon)" aria-label="Edit">
<span class="material-symbols-outlined text-xl">edit</span>
</button>
<button type="button" class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-rose-500 hover:bg-rose-50 hover:border-rose-200 flex items-center justify-center transition-all duration-200 shadow-sm hover:scale-110" title="Remove (coming soon)" aria-label="Remove">
<span class="material-symbols-outlined text-xl">delete</span>
</button>
</div>
</td>
</tr>
<?php } ?>
<?php } ?>
</tbody>
</table>
<div class="px-10 py-6 bg-slate-50 border-t border-slate-100">
<p class="text-[10px] font-black uppercase tracking-[0.2em] text-on-surface-variant/70">
                    <span class="text-slate-900"><?php echo (int) $team_total; ?></span> team member<?php echo $team_total === 1 ? '' : 's'; ?> for this clinic
                </p>
</div>
</div>
</div>
<!-- Footer Status -->
<footer class="mt-auto p-8 flex justify-center sticky bottom-0 z-10 pointer-events-none">
<div class="elevated-card pointer-events-auto px-10 py-4 rounded-full border border-slate-200/50 shadow-2xl flex items-center gap-10 text-[10px] font-black text-on-surface-variant/70 uppercase tracking-[0.2em]">
<div class="flex items-center gap-3 text-primary">
<span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                System Log: Real-time
            </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">schedule</span>
                Last Login: 10:24 AM
            </div>
<div class="h-4 w-px bg-slate-200"></div>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm">location_on</span>
                IP: 192.168.1.1
            </div>
</div>
</footer>
</div>
</main>
<div id="add-user-modal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4 sm:p-6" aria-hidden="true">
<div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm provider-modal-backdrop" data-modal-dismiss></div>
<div class="relative w-full max-w-xl max-h-[min(92vh,46rem)] flex flex-col rounded-3xl bg-background shadow-2xl border border-slate-200/80 overflow-hidden provider-modal-panel" role="dialog" aria-modal="true" aria-labelledby="add-user-title">
<div class="shrink-0 px-8 pt-8 pb-5 bg-white border-b border-slate-100/90 relative pr-16">
<button type="button" class="absolute top-6 right-6 flex h-10 w-10 items-center justify-center rounded-full border border-slate-200/80 bg-white text-on-surface-variant hover:border-primary/30 hover:text-primary transition-all shadow-sm" data-modal-dismiss aria-label="Close">
<span class="material-symbols-outlined text-xl">close</span>
</button>
<p class="text-[10px] font-black uppercase tracking-[0.28em] text-primary flex items-center gap-3"><span class="w-8 h-px bg-primary/40"></span> Team onboarding</p>
<h3 id="add-user-title" class="font-headline mt-3 text-2xl sm:text-3xl font-extrabold tracking-tight text-on-background leading-tight">Add <span class="font-editorial italic font-normal text-primary">team member</span></h3>
<p class="mt-2 text-[10px] font-bold uppercase tracking-[0.2em] text-on-surface-variant/70 leading-relaxed max-w-lg">Onboard a clinical specialist with tailored sidebar access. Provisioning hooks in later.</p>
</div>
<div class="add-user-modal-scroll flex-1 overflow-y-auto px-8 py-6 space-y-6">
<div class="rounded-2xl border border-primary/15 bg-surface-container-low/80 px-4 py-4 sm:px-5 sm:py-4 flex items-center gap-4">
<div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary">
<span class="material-symbols-outlined text-[22px]">person</span>
</div>
<div class="min-w-0 flex-1">
<p class="text-[11px] font-black uppercase tracking-widest text-on-background">Use my owner credentials</p>
<p class="text-[10px] font-semibold uppercase tracking-wider text-on-surface-variant/80 mt-1 leading-snug">Register yourself as staff using your current session (no separate password).</p>
</div>
<label class="shrink-0 cursor-pointer flex items-center">
<input type="checkbox" id="add-user-owner-mode" class="add-user-switch-input sr-only" autocomplete="off"/>
<span class="add-user-switch-track relative flex items-center p-0.5">
<span class="add-user-switch-thumb"></span>
</span>
</label>
</div>
<div id="add-user-new-member-fields" class="space-y-5">
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">First name</label>
<input type="text" id="add-user-first" class="add-user-identity-field w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-medium text-on-background placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all read-only:bg-slate-50 read-only:text-on-surface-variant read-only:cursor-default" placeholder="Given name" autocomplete="given-name"/>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Last name</label>
<input type="text" id="add-user-last" class="add-user-identity-field w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-medium text-on-background placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all read-only:bg-slate-50 read-only:text-on-surface-variant read-only:cursor-default" placeholder="Family name" autocomplete="family-name"/>
</div>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Professional email</label>
<input type="email" id="add-user-email" class="add-user-identity-field w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-sm font-medium text-on-background placeholder:text-slate-400 focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all read-only:bg-slate-50 read-only:text-on-surface-variant read-only:cursor-default" placeholder="name@clinic.com" autocomplete="email"/>
</div>
<div id="add-user-password-wrap" class="rounded-2xl border border-slate-200/90 bg-white elevated-card p-5 space-y-4">
<div>
<p class="text-[10px] font-black uppercase tracking-widest text-on-surface-variant/70">Sign-in password</p>
<p class="font-headline text-lg font-extrabold text-on-background mt-1">Set initial access password</p>
<p class="text-xs text-on-surface-variant mt-1.5 leading-relaxed">They’ll use this for first login. Share it through a secure channel outside the app.</p>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Password</label>
<div class="relative">
<span class="material-symbols-outlined pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-lg">key</span>
<input type="password" id="add-user-password" class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 pl-11 pr-11 py-3.5 text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Initial password" autocomplete="new-password"/>
<button type="button" class="add-user-pw-toggle absolute right-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-xl text-on-surface-variant hover:bg-slate-100 transition-colors" aria-label="Show password" data-target="add-user-password">
<span class="material-symbols-outlined text-lg">visibility_off</span>
</button>
</div>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Confirm</label>
<div class="relative">
<span class="material-symbols-outlined pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-variant/50 text-lg">check_circle</span>
<input type="password" id="add-user-password-confirm" class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 pl-11 pr-11 py-3.5 text-sm font-medium focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all" placeholder="Repeat password" autocomplete="new-password"/>
<button type="button" class="add-user-pw-toggle absolute right-2 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-xl text-on-surface-variant hover:bg-slate-100 transition-colors" aria-label="Show password" data-target="add-user-password-confirm">
<span class="material-symbols-outlined text-lg">visibility_off</span>
</button>
</div>
</div>
</div>
<div class="pt-1">
<div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest">
<span class="text-on-surface-variant/70">Password strength</span>
<span id="add-user-pw-strength-label" class="text-on-surface-variant">Waiting</span>
</div>
<div class="mt-2 h-1.5 rounded-full bg-slate-200 overflow-hidden">
<div id="add-user-pw-strength-bar" class="h-full rounded-full bg-primary/30 w-0 transition-all duration-300 ease-out"></div>
</div>
<p class="text-[9px] font-bold uppercase tracking-wider text-on-surface-variant/65 mt-3 leading-relaxed">At least 12 characters with uppercase, lowercase, a number, and a special character.</p>
</div>
</div>
</div>
<div>
<label class="block text-[10px] font-black uppercase tracking-widest text-on-surface-variant/80 mb-2">Clinic role</label>
<div class="relative">
<select id="add-user-role" class="appearance-none w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 pr-12 text-sm font-semibold text-on-background focus:ring-2 focus:ring-primary/20 focus:border-primary transition-all cursor-pointer">
<option>Manager</option>
<option>Staff</option>
<option>Doctor</option>
</select>
<span class="material-symbols-outlined pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-primary text-xl">expand_more</span>
</div>
</div>
</div>
<div class="shrink-0 flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 px-8 py-5 bg-white border-t border-slate-100">
<button type="button" class="text-center sm:text-left text-[11px] font-black uppercase tracking-[0.2em] text-primary hover:text-primary/80 transition-colors py-2" data-modal-dismiss>Cancel</button>
<button type="button" id="add-user-submit" class="w-full sm:w-auto rounded-2xl bg-primary text-white px-8 py-4 text-[11px] font-black uppercase tracking-[0.18em] shadow-lg shadow-primary/25 hover:brightness-110 transition-all hover:scale-[1.02] active:scale-[0.98]">
Send verification code
</button>
</div>
</div>
</div>
<div id="add-user-verify-modal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4 sm:p-6" aria-hidden="true">
<div class="absolute inset-0 bg-slate-900/55 backdrop-blur-sm provider-modal-backdrop" data-verify-dismiss="1"></div>
<div class="relative w-full max-w-md flex flex-col rounded-3xl shadow-2xl border border-slate-200/80 overflow-hidden provider-modal-panel" role="dialog" aria-modal="true" aria-labelledby="add-user-verify-title">
<div class="bg-white px-8 pt-10 pb-8 text-center relative">
<button type="button" class="absolute top-5 right-5 flex h-10 w-10 items-center justify-center rounded-full border border-slate-200/80 bg-white text-on-surface-variant hover:border-primary/30 hover:text-primary transition-all shadow-sm" data-verify-dismiss="1" aria-label="Close">
<span class="material-symbols-outlined text-xl">close</span>
</button>
<div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary/15 text-primary mb-5">
<span class="material-symbols-outlined text-3xl">mark_email_read</span>
</div>
<h3 id="add-user-verify-title" class="font-headline text-2xl sm:text-[1.65rem] font-extrabold tracking-tight text-on-background uppercase">
Verify <span class="font-editorial italic font-normal normal-case text-primary">email</span>
</h3>
<p class="mt-3 text-sm text-on-surface-variant leading-relaxed px-1">An authorization code was sent to <strong class="text-on-background" id="add-user-verify-email-display">—</strong></p>
</div>
<div class="bg-slate-100/90 px-8 py-8 space-y-5">
<p class="text-center text-[10px] font-black uppercase tracking-[0.25em] text-on-surface-variant/70">Enter 6-digit code</p>
<div id="add-user-verify-error" class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 text-center" role="alert"></div>
<input type="text" id="add-user-otp" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" class="add-user-otp-input w-full rounded-full border-2 border-primary bg-white px-6 py-4 text-center text-xl font-extrabold tracking-widest text-on-background placeholder:text-slate-300 focus:ring-4 focus:ring-primary/15 focus:outline-none transition-all" placeholder="• • • • • •"/>
<button type="button" id="add-user-verify-submit" class="w-full rounded-full bg-primary text-on-background py-4 text-[11px] font-black uppercase tracking-[0.12em] shadow-lg shadow-primary/30 hover:brightness-110 transition-all active:scale-[0.99]">
Verify &amp; create account
</button>
<button type="button" id="add-user-verify-resend" class="w-full text-center text-[11px] font-black uppercase tracking-[0.2em] text-on-surface-variant hover:text-primary transition-colors py-2">
Resend code
</button>
<button type="button" id="add-user-verify-cancel" class="w-full text-center text-[11px] font-black uppercase tracking-[0.2em] text-rose-600 hover:text-rose-700 transition-colors pt-1">
Cancel
</button>
</div>
</div>
</div>
<div id="add-user-invite-toast" class="fixed bottom-8 left-1/2 z-[120] hidden -translate-x-1/2 max-w-md rounded-2xl border border-primary/20 bg-white px-8 py-4 shadow-xl shadow-slate-900/10 text-sm font-semibold text-on-background text-center" role="status"></div>
<?php include __DIR__ . '/provider_tenant_profile_modal.inc.php'; ?>
<script type="application/json" id="add-user-owner-prefill"><?php echo $add_user_owner_prefill_json; ?></script>
<script>
(function () {
  var modal = document.getElementById('add-user-modal');
  var openBtn = document.getElementById('add-user-open');
  if (!modal || !openBtn) return;

  var ownerPrefill = { first: '', last: '', email: '' };
  var prefillEl = document.getElementById('add-user-owner-prefill');
  if (prefillEl) {
    try {
      ownerPrefill = JSON.parse(prefillEl.textContent || '{}');
    } catch (e) {
      ownerPrefill = { first: '', last: '', email: '' };
    }
  }

  var ownerCb = document.getElementById('add-user-owner-mode');
  var passwordWrap = document.getElementById('add-user-password-wrap');
  var pwInput = document.getElementById('add-user-password');
  var pwConfirm = document.getElementById('add-user-password-confirm');
  var strengthLabel = document.getElementById('add-user-pw-strength-label');
  var strengthBar = document.getElementById('add-user-pw-strength-bar');
  var firstInput = document.getElementById('add-user-first');
  var lastInput = document.getElementById('add-user-last');
  var emailInput = document.getElementById('add-user-email');
  var roleSelect = document.getElementById('add-user-role');
  var submitBtn = document.getElementById('add-user-submit');
  var verifyModal = document.getElementById('add-user-verify-modal');
  var otpInput = document.getElementById('add-user-otp');
  var verifyEmailDisplay = document.getElementById('add-user-verify-email-display');
  var verifyError = document.getElementById('add-user-verify-error');
  var verifySubmitBtn = document.getElementById('add-user-verify-submit');
  var verifyResendBtn = document.getElementById('add-user-verify-resend');
  var verifyCancelBtn = document.getElementById('add-user-verify-cancel');
  var inviteToast = document.getElementById('add-user-invite-toast');

  var draftFirst = '';
  var draftLast = '';
  var draftEmail = '';

  function abandonInvite() {
    fetch('ProviderTenantStaffInviteApi.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'abandon' }),
      credentials: 'same-origin'
    }).catch(function () {});
  }

  function postInvite(body) {
    return fetch('ProviderTenantStaffInviteApi.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
      credentials: 'same-origin'
    }).then(function (r) {
      return r.json().then(function (j) {
        return { ok: r.ok, status: r.status, j: j };
      });
    });
  }

  function hideVerifyError() {
    if (!verifyError) return;
    verifyError.classList.add('hidden');
    verifyError.textContent = '';
  }

  function showVerifyError(msg) {
    if (!verifyError) return;
    verifyError.textContent = msg;
    verifyError.classList.remove('hidden');
  }

  function hideVerifyModal() {
    if (!verifyModal) return;
    verifyModal.classList.add('hidden');
    verifyModal.classList.remove('flex');
    verifyModal.setAttribute('aria-hidden', 'true');
    if (otpInput) otpInput.value = '';
    hideVerifyError();
  }

  function isVerifyOpen() {
    return verifyModal && !verifyModal.classList.contains('hidden');
  }

  function openVerifyLayer(email) {
    if (!verifyModal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    if (verifyEmailDisplay) verifyEmailDisplay.textContent = email || '—';
    hideVerifyError();
    if (otpInput) {
      otpInput.value = '';
      setTimeout(function () { otpInput.focus(); }, 100);
    }
    verifyModal.classList.remove('hidden');
    verifyModal.classList.add('flex');
    verifyModal.setAttribute('aria-hidden', 'false');
  }

  function closeVerifyGoBack() {
    abandonInvite();
    hideVerifyModal();
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
  }

  function showInviteToast(msg) {
    if (!inviteToast) return;
    inviteToast.textContent = msg;
    inviteToast.classList.remove('hidden');
    setTimeout(function () {
      inviteToast.classList.add('hidden');
    }, 5000);
  }

  function openModal() {
    abandonInvite();
    hideVerifyModal();
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    if (ownerCb && ownerCb.checked) {
      setOwnerMode(true);
    }
  }
  function closeModal() {
    abandonInvite();
    hideVerifyModal();
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    draftFirst = '';
    draftLast = '';
    draftEmail = '';
    if (ownerCb) ownerCb.checked = false;
    setOwnerMode(false);
    if (firstInput) firstInput.value = '';
    if (lastInput) lastInput.value = '';
    if (emailInput) emailInput.value = '';
    if (pwInput) pwInput.value = '';
    if (pwConfirm) pwConfirm.value = '';
    if (strengthLabel) strengthLabel.textContent = 'Waiting';
    if (strengthBar) {
      strengthBar.style.width = '0%';
      strengthBar.className = 'h-full rounded-full bg-primary/30 w-0 transition-all duration-300 ease-out';
    }
  }

  function applyOwnerIdentityFields() {
    if (!firstInput || !lastInput || !emailInput) return;
    firstInput.value = ownerPrefill.first || '';
    lastInput.value = ownerPrefill.last || '';
    emailInput.value = ownerPrefill.email || '';
    firstInput.readOnly = true;
    lastInput.readOnly = true;
    emailInput.readOnly = true;
  }

  function clearOwnerIdentityFields() {
    if (!firstInput || !lastInput || !emailInput) return;
    firstInput.readOnly = false;
    lastInput.readOnly = false;
    emailInput.readOnly = false;
    firstInput.value = draftFirst;
    lastInput.value = draftLast;
    emailInput.value = draftEmail;
  }

  function setOwnerMode(on) {
    if (on) {
      draftFirst = firstInput ? firstInput.value : '';
      draftLast = lastInput ? lastInput.value : '';
      draftEmail = emailInput ? emailInput.value : '';
      applyOwnerIdentityFields();
    } else {
      clearOwnerIdentityFields();
    }

    if (!passwordWrap) return;
    passwordWrap.classList.toggle('hidden', on);
    passwordWrap.setAttribute('aria-hidden', on ? 'true' : 'false');
    if (pwInput) {
      pwInput.disabled = on;
      pwInput.required = false;
    }
    if (pwConfirm) {
      pwConfirm.disabled = on;
      pwConfirm.required = false;
    }
    if (on) {
      if (strengthLabel) strengthLabel.textContent = '—';
      if (strengthBar) {
        strengthBar.style.width = '0%';
        strengthBar.className = 'h-full rounded-full w-0 transition-all duration-300 ease-out bg-slate-200';
      }
    } else if (pwInput) {
      pwInput.required = true;
      if (pwConfirm) pwConfirm.required = true;
      updatePasswordStrength();
    }
  }

  function scorePassword(pw) {
    if (!pw) return { score: 0, label: 'Waiting', width: 0, color: 'bg-slate-200' };
    var len = pw.length;
    var hasLower = /[a-z]/.test(pw);
    var hasUpper = /[A-Z]/.test(pw);
    var hasNum = /\d/.test(pw);
    var hasSpec = /[^A-Za-z0-9]/.test(pw);
    var rules = (len >= 12 ? 1 : 0) + (hasLower ? 1 : 0) + (hasUpper ? 1 : 0) + (hasNum ? 1 : 0) + (hasSpec ? 1 : 0);
    if (rules <= 2) return { score: 1, label: 'Weak', width: 33, color: 'bg-rose-400' };
    if (rules <= 4) return { score: 2, label: 'Good', width: 66, color: 'bg-amber-400' };
    return { score: 3, label: 'Strong', width: 100, color: 'bg-primary' };
  }

  function updatePasswordStrength() {
    if (!strengthLabel || !strengthBar || !pwInput || ownerCb && ownerCb.checked) return;
    var r = scorePassword(pwInput.value);
    strengthLabel.textContent = r.label;
    strengthLabel.className = r.score === 3 ? 'text-primary font-black' : 'text-on-surface-variant';
    strengthBar.style.width = r.width + '%';
    strengthBar.className = 'h-full rounded-full transition-all duration-300 ease-out ' + r.color;
  }

  if (ownerCb) {
    ownerCb.addEventListener('change', function () {
      setOwnerMode(ownerCb.checked);
    });
    setOwnerMode(ownerCb.checked);
  }

  modal.querySelectorAll('.add-user-pw-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-target');
      var input = id ? document.getElementById(id) : null;
      var icon = btn.querySelector('.material-symbols-outlined');
      if (!input || !icon) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      icon.textContent = show ? 'visibility' : 'visibility_off';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  if (pwInput) pwInput.addEventListener('input', updatePasswordStrength);

  if (otpInput) {
    otpInput.addEventListener('input', function () {
      otpInput.value = otpInput.value.replace(/\D/g, '').slice(0, 6);
      hideVerifyError();
    });
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', function () {
      var owner = ownerCb && ownerCb.checked;
      var fn = firstInput ? firstInput.value.trim() : '';
      var ln = lastInput ? lastInput.value.trim() : '';
      var em = emailInput ? emailInput.value.trim() : '';
      var role = roleSelect ? roleSelect.value : 'Staff';
      if (!fn || !ln || !em) {
        alert('Please enter first name, last name, and professional email.');
        return;
      }
      var pw = pwInput ? pwInput.value : '';
      if (!owner) {
        if (!pwConfirm || pw !== pwConfirm.value) {
          alert('Passwords do not match.');
          return;
        }
      }
      submitBtn.disabled = true;
      postInvite({
        action: 'send_code',
        owner_mode: owner,
        first_name: fn,
        last_name: ln,
        email: em,
        role: role,
        password: owner ? '' : pw
      })
        .then(function (res) {
          if (res.ok && res.j && res.j.ok) {
            openVerifyLayer(res.j.email || em);
          } else {
            var err = (res.j && res.j.error) ? res.j.error : 'Could not send code.';
            alert(err);
          }
        })
        .catch(function () {
          alert('Network error. Please try again.');
        })
        .then(function () {
          submitBtn.disabled = false;
        });
    });
  }

  if (verifySubmitBtn) {
    verifySubmitBtn.addEventListener('click', function () {
      var code = otpInput ? otpInput.value.replace(/\D/g, '') : '';
      if (code.length !== 6) {
        showVerifyError('Enter the 6-digit code from the email.');
        return;
      }
      hideVerifyError();
      verifySubmitBtn.disabled = true;
      postInvite({ action: 'verify', code: code })
        .then(function (res) {
          if (res.ok && res.j && res.j.ok) {
            hideVerifyModal();
            closeModal();
            window.location.reload();
          } else {
            showVerifyError((res.j && res.j.error) ? res.j.error : 'Verification failed.');
          }
        })
        .catch(function () {
          showVerifyError('Network error. Please try again.');
        })
        .then(function () {
          verifySubmitBtn.disabled = false;
        });
    });
  }

  if (verifyResendBtn) {
    verifyResendBtn.addEventListener('click', function () {
      verifyResendBtn.disabled = true;
      hideVerifyError();
      postInvite({ action: 'resend' })
        .then(function (res) {
          if (res.ok && res.j && res.j.ok) {
            if (verifyEmailDisplay && res.j.email) verifyEmailDisplay.textContent = res.j.email;
          } else {
            showVerifyError((res.j && res.j.error) ? res.j.error : 'Could not resend.');
          }
        })
        .catch(function () {
          showVerifyError('Network error.');
        })
        .then(function () {
          verifyResendBtn.disabled = false;
        });
    });
  }

  if (verifyCancelBtn) {
    verifyCancelBtn.addEventListener('click', closeVerifyGoBack);
  }
  if (verifyModal) {
    verifyModal.querySelectorAll('[data-verify-dismiss]').forEach(function (el) {
      el.addEventListener('click', closeVerifyGoBack);
    });
  }

  openBtn.addEventListener('click', openModal);
  modal.querySelectorAll('[data-modal-dismiss]').forEach(function (el) {
    el.addEventListener('click', closeModal);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (isVerifyOpen()) {
      closeVerifyGoBack();
      return;
    }
    if (!modal.classList.contains('hidden')) closeModal();
  });
})();
</script>
</body></html>