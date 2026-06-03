<?php
require_once __DIR__ . '/config/config.php';
$staff_nav_active = 'users';
// Dentist role restriction: redirect to dashboard
if (session_status() === PHP_SESSION_NONE) { clinic_session_start(); }
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
?>
<!DOCTYPE html>

<html class="light" lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>User Management | Precision Dental</title>
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
        .staff-modal-overlay:not(.hidden) {
            animation: staff-modal-fade-in 0.25s ease forwards;
        }
        .staff-modal-panel {
            animation: staff-modal-panel-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes staff-modal-fade-in {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes staff-modal-panel-in {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
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
<!-- SideNavBar Component -->
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<!-- Main Wrapper -->
<main class="flex-1 flex flex-col min-w-0 ml-0 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<div class="pt-4 sm:pt-6 px-4 sm:px-6 lg:px-10 pb-12 sm:pb-16 space-y-6 sm:space-y-8">
<!-- Page Header -->
<section class="flex flex-col gap-3 sm:gap-4">
<div class="text-primary font-bold text-[10px] sm:text-xs uppercase flex items-center gap-3 sm:gap-4 tracking-[0.25em] sm:tracking-[0.3em]">
<span class="w-8 sm:w-12 h-[1.5px] bg-primary"></span> USER MANAGEMENT
                </div>
<div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
<div>
<h2 class="font-headline text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight sm:tracking-tighter leading-tight text-on-background">
                            User <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
</h2>
<p class="font-body text-base sm:text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3 sm:mt-4">
                            Manage practitioner access and administrative permissions for your clinic.
                        </p>
</div>
<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-4 w-full lg:w-auto">
<div class="relative w-full sm:flex-1 lg:w-72">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base">search</span>
<input id="userSearchInput" class="w-full pl-10 pr-4 py-3 bg-white border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="Search name, email or role..." type="text"/>
</div>
<div class="relative w-full sm:w-auto">
<select id="roleFilterSelect" class="w-full appearance-none bg-white border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl px-5 py-3 pr-10 focus:ring-primary/20 focus:border-primary transition-all outline-none">
<option value="">All Roles</option>
<option value="manager">Manager</option>
<option value="doctor">Doctor</option>
<option value="staff">Staff</option>
<option value="client">Client</option>
</select>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">expand_more</span>
</div>
</div>
</div>
</section>
<!-- User Registry Table -->
<section id="usersListSection" class="elevated-card rounded-3xl overflow-hidden">
<div class="p-4 sm:p-6 lg:p-8 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white">
<div>
<h3 class="text-xl sm:text-2xl font-bold font-headline text-on-background">User Registry</h3>
<p class="text-[10px] sm:text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Practitioner profiles and access logs</p>
</div>
<div class="relative w-full sm:w-auto">
<select id="statusFilterSelect" class="w-full appearance-none bg-white border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl px-5 py-2.5 pr-10 focus:ring-primary/20 focus:border-primary transition-all outline-none">
<option value="">All Status</option>
<option value="active">Active</option>
<option value="inactive">Suspended</option>
</select>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">expand_more</span>
</div>
</div>
<div id="usersMobileList" class="lg:hidden divide-y divide-slate-100 px-4 sm:px-6">
<p class="py-10 text-center text-slate-500 font-medium text-sm">Loading users...</p>
</div>
<div class="hidden lg:block overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/50">
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Name &amp; Profile</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Role</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Last Login</th>
<th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</th>
<th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Action</th>
</tr>
</thead>
<tbody id="usersTableBody" class="divide-y divide-slate-100">
<tr>
<td class="px-6 py-10 text-center text-slate-500 font-medium" colspan="5">Loading users...</td>
</tr>
</tbody>
</table>
</div>
<!-- Pagination Footer -->
<div class="p-4 sm:p-6 bg-slate-50/30 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
<p id="recordsSummary" class="text-[10px] sm:text-[11px] font-bold text-slate-500 uppercase tracking-widest text-center sm:text-left">Showing 0 of 0 users</p>
</div>
</section>
<div class="h-10"></div>
</div>
<div class="staff-modal-overlay fixed inset-0 bg-black/50 z-50 hidden items-end sm:items-center justify-center p-0 sm:p-4" id="editUserModal">
<div class="staff-modal-panel bg-white rounded-t-3xl sm:rounded-2xl shadow-xl max-w-xl w-full p-5 sm:p-6 border border-slate-200 max-h-[92vh] overflow-y-auto">
<div class="flex items-center justify-between mb-5 sm:mb-6">
<h2 class="text-xl sm:text-2xl font-bold text-slate-900">Update User Details</h2>
<button class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" id="closeEditModal" type="button">
<span class="material-symbols-outlined text-[24px]">close</span>
</button>
</div>
<form class="space-y-4" id="editUserForm">
<input id="editUserId" type="hidden"/>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editFirstName">First name</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editFirstName" required type="text"/>
</div>
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editLastName">Last name</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editLastName" required type="text"/>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editEmail">Email</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editEmail" required type="email"/>
</div>
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editUsername">Username</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editUsername" type="text"/>
</div>
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editRole">Role</label>
<select class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editRole">
<option value="manager">Manager</option>
<option value="doctor">Doctor</option>
<option value="staff">Staff</option>
<option value="client">Client</option>
</select>
</div>
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editContact">Contact number</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editContact" type="text"/>
</div>
</div>
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editPassword">New password (optional)</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editPassword" placeholder="Leave blank to keep current password" type="password"/>
</div>
<div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3 pt-3">
<button class="w-full sm:w-auto px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold text-sm" id="cancelEditBtn" type="button">Cancel</button>
<button class="w-full sm:w-auto px-4 py-2.5 bg-primary hover:bg-primary/90 text-white rounded-xl font-semibold text-sm" type="submit">Save Changes</button>
</div>
</form>
</div>
</div>
<!-- Site Footer -->
<footer class="mt-auto px-4 sm:px-10 py-6 sm:py-8 border-t border-slate-100 flex flex-col sm:flex-row gap-4 sm:justify-between sm:items-center text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-widest">
<p class="text-center sm:text-left">© 2024 Precision Dental Clinic System. All clinical data encrypted.</p>
<div class="flex flex-wrap justify-center sm:justify-end gap-4 sm:gap-8">
<a class="hover:text-primary transition-colors" href="#">Privacy Protocol</a>
<a class="hover:text-primary transition-colors" href="#">System Status</a>
<a class="hover:text-primary transition-colors" href="#">Terms of Service</a>
</div>
</footer>
</main>
<!-- Floating Action Button -->
<button class="fixed bottom-5 right-4 sm:bottom-8 sm:right-8 w-12 h-12 sm:w-14 sm:h-14 bg-primary text-white rounded-2xl shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all z-50">
<span class="material-symbols-outlined text-2xl">add</span>
</button>
<script src="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>js/staff-ui-dialogs.js"></script>
<script>
const API_USERS_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/users.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const usersListSection = document.getElementById('usersListSection');
const usersMobileList = document.getElementById('usersMobileList');
const usersTableBody = document.getElementById('usersTableBody');
const recordsSummary = document.getElementById('recordsSummary');
const userSearchInput = document.getElementById('userSearchInput');
const roleFilterSelect = document.getElementById('roleFilterSelect');
const statusFilterSelect = document.getElementById('statusFilterSelect');
const editUserModal = document.getElementById('editUserModal');
const editUserForm = document.getElementById('editUserForm');

let usersData = [];
let searchTimer = null;

function escapeHtml(text) {
    return String(text || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function formatLastLogin(lastLogin) {
    if (!lastLogin) return 'Never';
    const date = new Date(lastLogin);
    if (Number.isNaN(date.getTime())) return 'Never';
    const now = new Date();
    const diffMin = Math.floor((now - date) / 60000);
    if (diffMin < 1) return 'Just now';
    if (diffMin < 60) return diffMin + ' mins ago';
    const diffHour = Math.floor(diffMin / 60);
    if (diffHour < 24) return diffHour + ' hours ago';
    const diffDay = Math.floor(diffHour / 24);
    if (diffDay < 7) return diffDay + ' days ago';
    return date.toLocaleDateString();
}

function roleBadge(userType) {
    const label = (userType || '').toLowerCase();
    const map = {
        manager: 'bg-purple-50 text-purple-700',
        doctor: 'bg-blue-50 text-blue-700',
        staff: 'bg-slate-100 text-slate-700',
        client: 'bg-emerald-50 text-emerald-700'
    };
    const cls = map[label] || 'bg-slate-100 text-slate-700';
    return '<span class="px-3 py-1 ' + cls + ' text-[10px] font-bold rounded-full uppercase tracking-wider">' + escapeHtml(label || 'user') + '</span>';
}

function initialsFromName(firstName, lastName, email) {
    const initials = ((firstName || '').charAt(0) + (lastName || '').charAt(0)).toUpperCase();
    if (initials.trim() !== '') return initials;
    return String(email || 'U').charAt(0).toUpperCase();
}

function userStatusToggleHtml(user) {
    const isActive = String(user.status || '').toLowerCase() === 'active';
    return ''
        + '<label class="inline-flex items-center cursor-pointer">'
        + '<input class="sr-only peer user-status-toggle" data-user-id="' + escapeHtml(user.id) + '" type="checkbox" ' + (isActive ? 'checked' : '') + '>'
        + '<div class="relative w-11 h-6 bg-slate-200 rounded-full peer-checked:bg-primary after:content-[\'\'] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-slate-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>'
        + '<span class="ms-3 text-xs font-medium text-slate-600 status-label">' + (isActive ? 'Active' : 'Suspended') + '</span>'
        + '</label>';
}

function buildUserTableRowHtml(user) {
    const fullName = (String(user.first_name || '') + ' ' + String(user.last_name || '')).trim() || String(user.email || 'User');
    const initials = initialsFromName(user.first_name, user.last_name, user.email);
    return ''
        + '<tr class="hover:bg-slate-50/30 transition-colors group" data-user-id="' + escapeHtml(user.id) + '">'
        + '<td class="px-8 py-6"><div class="flex items-center gap-4">'
        + '<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">' + escapeHtml(initials) + '</div>'
        + '<div><p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">' + escapeHtml(fullName) + '</p>'
        + '<p class="text-[10px] text-slate-500 font-medium mt-0.5">' + escapeHtml(user.email || '') + '</p></div></div></td>'
        + '<td class="px-6 py-6">' + roleBadge(user.user_type) + '</td>'
        + '<td class="px-6 py-6 text-sm font-semibold text-slate-700">' + escapeHtml(formatLastLogin(user.last_login)) + '</td>'
        + '<td class="px-6 py-6">' + userStatusToggleHtml(user) + '</td>'
        + '<td class="px-8 py-6 text-right"><button type="button" class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-slate-200 text-slate-600 hover:text-primary hover:border-primary/30 rounded-xl transition-all text-xs font-bold uppercase tracking-wider edit-user-btn" data-user-id="' + escapeHtml(user.id) + '"><span class="material-symbols-outlined text-[16px]">edit_square</span>Update</button></td>'
        + '</tr>';
}

function buildUserMobileCardHtml(user) {
    const fullName = (String(user.first_name || '') + ' ' + String(user.last_name || '')).trim() || String(user.email || 'User');
    const initials = initialsFromName(user.first_name, user.last_name, user.email);
    return ''
        + '<article class="py-4 first:pt-5 last:pb-5" data-user-id="' + escapeHtml(user.id) + '">'
        + '<div class="flex items-start gap-3">'
        + '<div class="w-10 h-10 shrink-0 rounded-xl bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">' + escapeHtml(initials) + '</div>'
        + '<div class="min-w-0 flex-1">'
        + '<p class="text-sm font-bold text-slate-900 leading-snug">' + escapeHtml(fullName) + '</p>'
        + '<p class="text-[10px] text-slate-500 font-medium mt-0.5 break-all">' + escapeHtml(user.email || '') + '</p>'
        + '<div class="mt-2">' + roleBadge(user.user_type) + '</div>'
        + '</div></div>'
        + '<dl class="mt-3 space-y-2 text-xs">'
        + '<div class="flex justify-between gap-3"><dt class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Last login</dt><dd class="font-semibold text-slate-700">' + escapeHtml(formatLastLogin(user.last_login)) + '</dd></div>'
        + '<div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100"><dt class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Status</dt><dd>' + userStatusToggleHtml(user) + '</dd></div>'
        + '</dl>'
        + '<button type="button" class="mt-3 w-full inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 border border-slate-200 text-slate-600 hover:text-primary hover:border-primary/30 rounded-xl transition-all text-xs font-bold uppercase tracking-wider edit-user-btn" data-user-id="' + escapeHtml(user.id) + '"><span class="material-symbols-outlined text-[16px]">edit_square</span>Update</button>'
        + '</article>';
}

function setUsersListMessage(message, tone) {
    const msgClass = tone === 'error' ? 'text-red-500' : 'text-slate-500';
    if (usersMobileList) {
        usersMobileList.innerHTML = '<p class="py-10 text-center font-medium text-sm ' + msgClass + '">' + escapeHtml(message) + '</p>';
    }
    if (usersTableBody) {
        usersTableBody.innerHTML = '<tr><td class="px-6 py-10 text-center font-medium ' + msgClass + '" colspan="5">' + escapeHtml(message) + '</td></tr>';
    }
}

function renderUsers(users) {
    if (!Array.isArray(users) || users.length === 0) {
        setUsersListMessage('No users found.', 'empty');
        recordsSummary.textContent = 'Showing 0 of 0 users';
        return;
    }

    if (usersMobileList) {
        usersMobileList.innerHTML = users.map(buildUserMobileCardHtml).join('');
    }
    if (usersTableBody) {
        usersTableBody.innerHTML = users.map(buildUserTableRowHtml).join('');
    }

    recordsSummary.textContent = 'Showing ' + users.length + ' of ' + users.length + ' users';
}

async function loadUsers() {
    setUsersListMessage('Loading users...', 'loading');
    const params = new URLSearchParams();
    const search = (userSearchInput.value || '').trim();
    const role = roleFilterSelect.value;
    const status = statusFilterSelect.value;
    if (search) params.append('search', search);
    if (role) params.append('user_type', role);
    if (status) params.append('status', status);
    params.append('limit', '100');

    try {
        const res = await fetch(API_USERS_URL + '?' + params.toString(), { credentials: 'include' });
        const data = await res.json();
        if (!res.ok || !data.success || !data.data || !Array.isArray(data.data.users)) {
            throw new Error(data.message || 'Unable to load users.');
        }
        usersData = data.data.users;
        renderUsers(usersData);
    } catch (error) {
        setUsersListMessage(error.message || 'Unable to load users.', 'error');
        recordsSummary.textContent = 'Showing 0 of 0 users';
    }
}

async function handleStatusToggle(event) {
    const toggle = event.currentTarget;
    const userId = String(toggle.dataset.userId || '');
    const isActive = toggle.checked;
    try {
        const res = await fetch(API_USERS_URL, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id: userId, status: isActive ? 'active' : 'inactive' })
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Failed to update status.');
        }
        if (usersListSection) {
            usersListSection.querySelectorAll('[data-user-id="' + CSS.escape(userId) + '"] .user-status-toggle').forEach(function (otherToggle) {
                otherToggle.checked = isActive;
            });
            usersListSection.querySelectorAll('[data-user-id="' + CSS.escape(userId) + '"] .status-label').forEach(function (label) {
                label.textContent = isActive ? 'Active' : 'Suspended';
            });
        }
    } catch (error) {
        if (usersListSection) {
            usersListSection.querySelectorAll('[data-user-id="' + CSS.escape(userId) + '"] .user-status-toggle').forEach(function (otherToggle) {
                otherToggle.checked = !isActive;
            });
        } else {
            toggle.checked = !isActive;
        }
        void staffUiAlert({ message: error.message || 'Failed to update user status.', variant: 'error', title: 'Status update failed' });
    }
}

function openEditModal(userId) {
    const user = usersData.find(function (item) { return String(item.id) === String(userId); });
    if (!user) return;
    document.getElementById('editUserId').value = String(user.id);
    document.getElementById('editFirstName').value = user.first_name || '';
    document.getElementById('editLastName').value = user.last_name || '';
    document.getElementById('editEmail').value = user.email || '';
    document.getElementById('editUsername').value = user.username || '';
    document.getElementById('editRole').value = user.user_type || 'staff';
    document.getElementById('editContact').value = user.contact_number || '';
    document.getElementById('editPassword').value = '';
    editUserModal.classList.remove('hidden');
    editUserModal.classList.add('flex');
}

function closeEditModal() {
    editUserModal.classList.add('hidden');
    editUserModal.classList.remove('flex');
}

if (usersListSection) {
    usersListSection.addEventListener('click', function (event) {
        const editBtn = event.target.closest('.edit-user-btn');
        if (editBtn) {
            openEditModal(String(editBtn.dataset.userId || ''));
        }
    });
    usersListSection.addEventListener('change', function (event) {
        const toggle = event.target;
        if (toggle && toggle.classList && toggle.classList.contains('user-status-toggle')) {
            handleStatusToggle(event);
        }
    });
}

document.getElementById('closeEditModal').addEventListener('click', closeEditModal);
document.getElementById('cancelEditBtn').addEventListener('click', closeEditModal);
editUserModal.addEventListener('click', function (event) {
    if (event.target === editUserModal) closeEditModal();
});

editUserForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    const payload = {
        id: String(document.getElementById('editUserId').value || '').trim(),
        first_name: document.getElementById('editFirstName').value.trim(),
        last_name: document.getElementById('editLastName').value.trim(),
        email: document.getElementById('editEmail').value.trim(),
        username: document.getElementById('editUsername').value.trim(),
        user_type: document.getElementById('editRole').value,
        contact_number: document.getElementById('editContact').value.trim()
    };
    const password = document.getElementById('editPassword').value.trim();
    if (password) payload.password = password;

    try {
        const res = await fetch(API_USERS_URL, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.message || 'Failed to update user.');
        }
        closeEditModal();
        await loadUsers();
    } catch (error) {
        void staffUiAlert({ message: error.message || 'Failed to update user.', variant: 'error', title: 'Could not update user' });
    }
});

function triggerLoad() {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = window.setTimeout(loadUsers, 300);
}

userSearchInput.addEventListener('input', triggerLoad);
roleFilterSelect.addEventListener('change', loadUsers);
statusFilterSelect.addEventListener('change', loadUsers);
loadUsers();
</script>
</body></html>