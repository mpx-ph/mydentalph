<?php
require_once __DIR__ . '/config/config.php';
$staff_nav_active = 'users';
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
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<!-- Scrollable Content -->
<div class="p-10 space-y-10">
<!-- Page Header -->
<section class="flex flex-col gap-4 mb-4">
<div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
<span class="w-12 h-[1.5px] bg-primary"></span> USER MANAGEMENT
                </div>
<div class="flex items-end justify-between">
<div>
<h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                            User <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Management</span>
</h2>
<p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                            Manage practitioner access and administrative permissions for your clinic.
                        </p>
</div>
<div class="flex items-center gap-4">
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-base">search</span>
<input id="userSearchInput" class="pl-10 pr-4 py-3 w-72 bg-white border border-slate-200 text-slate-600 text-sm font-semibold rounded-xl focus:ring-primary/20 focus:border-primary transition-all outline-none" placeholder="Search name, email or role..." type="text"/>
</div>
<div class="relative">
<select id="roleFilterSelect" class="appearance-none bg-white border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl px-5 py-3 pr-10 focus:ring-primary/20 focus:border-primary transition-all outline-none">
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
<section class="elevated-card rounded-3xl overflow-hidden">
<div class="p-8 border-b border-slate-100 flex justify-between items-center bg-white">
<div>
<h3 class="text-2xl font-bold font-headline text-on-background">User Registry</h3>
<p class="text-[11px] text-on-surface-variant/60 font-black uppercase tracking-widest mt-1">Practitioner profiles and access logs</p>
</div>
<div class="relative">
<select id="statusFilterSelect" class="appearance-none bg-white border border-slate-200 text-slate-600 text-[10px] font-bold uppercase tracking-widest rounded-xl px-5 py-2.5 pr-10 focus:ring-primary/20 focus:border-primary transition-all outline-none">
<option value="">All Status</option>
<option value="active">Active</option>
<option value="inactive">Suspended</option>
</select>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">expand_more</span>
</div>
</div>
<div class="overflow-x-auto">
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
<div class="p-6 bg-slate-50/30 border-t border-slate-100 flex items-center justify-between">
<p id="recordsSummary" class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing 0 of 0 users</p>
</div>
</section>
</div>
<div class="staff-modal-overlay fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" id="editUserModal">
<div class="staff-modal-panel bg-white rounded-2xl shadow-xl max-w-xl w-full p-6 border border-slate-200">
<div class="flex items-center justify-between mb-6">
<h2 class="text-2xl font-bold text-slate-900">Update User Details</h2>
<button class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors" id="closeEditModal" type="button">
<span class="material-symbols-outlined text-[24px]">close</span>
</button>
</div>
<form class="space-y-4" id="editUserForm">
<input id="editUserId" type="hidden"/>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editFirstName">First name</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editFirstName" required type="text"/>
</div>
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editLastName">Last name</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editLastName" required type="text"/>
</div>
</div>
<div class="grid grid-cols-2 gap-3">
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editEmail">Email</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editEmail" required type="email"/>
</div>
<div>
<label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1" for="editUsername">Username</label>
<input class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm" id="editUsername" type="text"/>
</div>
</div>
<div class="grid grid-cols-2 gap-3">
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
<div class="flex justify-end gap-3 pt-3">
<button class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-semibold text-sm" id="cancelEditBtn" type="button">Cancel</button>
<button class="px-4 py-2.5 bg-primary hover:bg-primary/90 text-white rounded-xl font-semibold text-sm" type="submit">Save Changes</button>
</div>
</form>
</div>
</div>
<!-- Site Footer -->
<footer class="mt-auto px-10 py-8 border-t border-slate-100 flex justify-between items-center text-[11px] font-bold text-slate-400 uppercase tracking-widest">
<p>© 2024 Precision Dental Clinic System. All clinical data encrypted.</p>
<div class="flex gap-8">
<a class="hover:text-primary transition-colors" href="#">Privacy Protocol</a>
<a class="hover:text-primary transition-colors" href="#">System Status</a>
<a class="hover:text-primary transition-colors" href="#">Terms of Service</a>
</div>
</footer>
</main>
<!-- Floating Action Button -->
<button class="fixed bottom-8 right-8 w-14 h-14 bg-primary text-white rounded-2xl shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all z-50">
<span class="material-symbols-outlined text-2xl">add</span>
</button>
<script src="<?php echo htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8'); ?>js/staff-ui-dialogs.js"></script>
<script>
const API_USERS_URL = <?php echo json_encode(rtrim((string) dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api/users.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
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

function renderUsers(users) {
    if (!Array.isArray(users) || users.length === 0) {
        usersTableBody.innerHTML = '<tr><td class="px-6 py-10 text-center text-slate-500 font-medium" colspan="5">No users found.</td></tr>';
        recordsSummary.textContent = 'Showing 0 of 0 users';
        return;
    }

    usersTableBody.innerHTML = users.map(function (user) {
        const fullName = (String(user.first_name || '') + ' ' + String(user.last_name || '')).trim() || String(user.email || 'User');
        const initials = initialsFromName(user.first_name, user.last_name, user.email);
        const isActive = String(user.status || '').toLowerCase() === 'active';
        return ''
            + '<tr class="hover:bg-slate-50/30 transition-colors group">'
            + '<td class="px-8 py-6"><div class="flex items-center gap-4">'
            + '<div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center text-primary font-bold text-xs">' + escapeHtml(initials) + '</div>'
            + '<div><p class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">' + escapeHtml(fullName) + '</p>'
            + '<p class="text-[10px] text-slate-500 font-medium mt-0.5">' + escapeHtml(user.email || '') + '</p></div></div></td>'
            + '<td class="px-6 py-6">' + roleBadge(user.user_type) + '</td>'
            + '<td class="px-6 py-6 text-sm font-semibold text-slate-700">' + escapeHtml(formatLastLogin(user.last_login)) + '</td>'
            + '<td class="px-6 py-6"><label class="inline-flex items-center cursor-pointer">'
            + '<input class="sr-only peer user-status-toggle" data-user-id="' + escapeHtml(user.id) + '" type="checkbox" ' + (isActive ? 'checked' : '') + '>'
            + '<div class="relative w-11 h-6 bg-slate-200 rounded-full peer-checked:bg-primary after:content-[\'\'] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border after:border-slate-300 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>'
            + '<span class="ms-3 text-xs font-medium text-slate-600 status-label">' + (isActive ? 'Active' : 'Suspended') + '</span></label></td>'
            + '<td class="px-8 py-6 text-right"><button class="inline-flex items-center gap-1.5 px-3.5 py-2 border border-slate-200 text-slate-600 hover:text-primary hover:border-primary/30 rounded-xl transition-all text-xs font-bold uppercase tracking-wider edit-user-btn" data-user-id="' + escapeHtml(user.id) + '"><span class="material-symbols-outlined text-[16px]">edit_square</span>Update</button></td>'
            + '</tr>';
    }).join('');

    recordsSummary.textContent = 'Showing ' + users.length + ' of ' + users.length + ' users';

    usersTableBody.querySelectorAll('.user-status-toggle').forEach(function (toggle) {
        toggle.addEventListener('change', handleStatusToggle);
    });
}

async function loadUsers() {
    usersTableBody.innerHTML = '<tr><td class="px-6 py-10 text-center text-slate-500 font-medium" colspan="5">Loading users...</td></tr>';
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
        usersTableBody.innerHTML = '<tr><td class="px-6 py-10 text-center text-red-500 font-medium" colspan="5">' + escapeHtml(error.message || 'Unable to load users.') + '</td></tr>';
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
        const row = toggle.closest('tr');
        const label = row ? row.querySelector('.status-label') : null;
        if (label) label.textContent = isActive ? 'Active' : 'Suspended';
    } catch (error) {
        toggle.checked = !isActive;
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

usersTableBody.addEventListener('click', function (event) {
    const editBtn = event.target.closest('.edit-user-btn');
    if (editBtn) {
        openEditModal(String(editBtn.dataset.userId || ''));
    }
});

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