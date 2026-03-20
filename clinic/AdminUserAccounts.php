<?php
/**
 * Admin User Accounts Page
 * Requires admin authentication
 */
$pageTitle = 'User Accounts Module - Dental Clinic Manager';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // Require admin role
require_once __DIR__ . '/includes/header.php';
?>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .material-symbols-outlined.filled {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #334155;
        }
    </style>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-8 sticky top-0 z-10 shrink-0">
<div>
<h1 class="text-2xl font-bold">User Accounts</h1>
<p class="text-sm text-slate-500 dark:text-slate-400">Manage system access, assign roles, and monitor user activity across the clinic.</p>
</div>
<div class="flex items-center gap-6">
<div class="relative hidden md:block">
<span class="material-icons-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input id="headerUserSearchInput" class="pl-10 pr-4 py-2 w-64 rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all" placeholder="Search users..." type="text"/>
</div>
</div>
</header>
<div class="flex-1 overflow-y-auto p-8">
<div class="max-w-[1600px] mx-auto w-full">
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
<div class="bg-white dark:bg-surface-dark rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex items-start justify-between relative overflow-hidden group">
<div class="relative z-10">
<p class="text-slate-500 dark:text-slate-400 text-sm font-semibold uppercase tracking-wider mb-1">Total Users</p>
<div class="flex items-baseline gap-2">
<h3 class="text-3xl font-bold text-slate-900 dark:text-white">0</h3>
</div>
</div>
<div class="size-12 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 flex items-center justify-center">
<span class="material-symbols-outlined text-[28px] filled">group</span>
</div>
<div class="absolute -right-6 -bottom-6 text-slate-50 dark:text-slate-800/50 opacity-50 group-hover:scale-110 transition-transform duration-500">
<span class="material-symbols-outlined text-[120px]">group</span>
</div>
</div>
<div class="bg-white dark:bg-surface-dark rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex items-start justify-between relative overflow-hidden group">
<div class="relative z-10">
<p class="text-slate-500 dark:text-slate-400 text-sm font-semibold uppercase tracking-wider mb-1">Active Dentists</p>
<h3 class="text-3xl font-bold text-slate-900 dark:text-white">0</h3>
</div>
<div class="size-12 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
<span class="material-symbols-outlined text-[28px] filled">dentistry</span>
</div>
<div class="absolute -right-6 -bottom-6 text-slate-50 dark:text-slate-800/50 opacity-50 group-hover:scale-110 transition-transform duration-500">
<span class="material-symbols-outlined text-[120px]">dentistry</span>
</div>
</div>
<div class="bg-white dark:bg-surface-dark rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex items-start justify-between relative overflow-hidden group">
<div class="relative z-10">
<p class="text-slate-500 dark:text-slate-400 text-sm font-semibold uppercase tracking-wider mb-1">Clients</p>
<h3 class="text-3xl font-bold text-slate-900 dark:text-white">0</h3>
</div>
<div class="size-12 rounded-xl bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400 flex items-center justify-center">
<span class="material-symbols-outlined text-[28px] filled">support_agent</span>
</div>
<div class="absolute -right-6 -bottom-6 text-slate-50 dark:text-slate-800/50 opacity-50 group-hover:scale-110 transition-transform duration-500">
<span class="material-symbols-outlined text-[120px]">support_agent</span>
</div>
</div>
</div>
<div class="bg-white dark:bg-surface-dark rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
<div class="p-5 border-b border-slate-200 dark:border-slate-700 flex flex-col lg:flex-row gap-4 justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
<div class="relative w-full lg:w-96 group">
<span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">
<span class="material-symbols-outlined">search</span>
</span>
<input id="userSearchInput" class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-shadow placeholder-slate-400 dark:text-white" placeholder="Search by name, email or role..." type="text"/>
</div>
<div class="flex w-full lg:w-auto gap-3 overflow-x-auto pb-1 lg:pb-0">
<div class="min-w-[140px]">
                    <select id="roleFilterSelect" class="w-full py-2.5 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary cursor-pointer">
                    <option value="">All Roles</option>
                    <option value="owner">Owner</option>
                    <option value="admin">Administrator</option>
                    <option value="manager">Manager</option>
                    <option value="dentist">Dentist</option>
                    <option value="assistant">Assistant</option>
                    <option value="client">Client</option>
                </select>
</div>
<div class="min-w-[140px]">
<select id="statusFilterSelect" class="w-full py-2.5 px-3 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary cursor-pointer">
<option value="all">All Status</option>
<option value="active">Active</option>
<option value="inactive">Inactive</option>
</select>
</div>
<button id="exportUsersBtn" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-full font-semibold flex items-center gap-2 transition-all shadow-lg shadow-primary/20 whitespace-nowrap" title="Export to PDF">
<span class="material-symbols-outlined text-[18px]">download</span>
                    Export PDF
</button>
</div>
</div>
<div class="overflow-x-auto">
<table class="w-full text-left border-collapse">
<thead>
<tr class="bg-slate-50/80 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-700">
<th class="py-4 pl-6 pr-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 w-[35%]">User Details</th>
<th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Role</th>
<th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Last Login</th>
<th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</th>
<th class="px-4 py-4 pr-6 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 text-right">Actions</th>
</tr>
</thead>
<tbody id="usersTableBody" class="divide-y divide-slate-100 dark:divide-slate-700/50">
</tbody>
</table>
</div>
<div class="flex items-center justify-between px-6 py-4 bg-slate-50/50 dark:bg-slate-800/30 border-t border-slate-200 dark:border-slate-700">
<p id="paginationText" class="text-sm text-slate-500 dark:text-slate-400">
                        Showing <span class="font-bold text-slate-900 dark:text-white">0</span> of <span class="font-bold text-slate-900 dark:text-white">0</span> results
                    </p>
<div class="flex items-center gap-2" id="paginationContainer">
<button id="prevPageBtn" class="p-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-500 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" disabled="">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</button>
<div id="paginationButtons" class="hidden sm:flex gap-1">
</div>
<button id="nextPageBtn" class="p-2 rounded-lg border border-slate-300 dark:border-slate-600 text-slate-500 hover:bg-white dark:hover:bg-slate-700 hover:text-primary transition-colors">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</button>
</div>
</div>
</div>
</div>
</main>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="fixed inset-0 bg-black/50 dark:bg-black/70 z-50 hidden items-center justify-center p-4">
    <div class="bg-white dark:bg-surface-dark rounded-2xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Edit User</h2>
            <button id="closeEditModal" class="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors">
                <span class="material-symbols-outlined text-[24px]">close</span>
            </button>
        </div>
        <form id="editUserForm" class="space-y-6">
            <input type="hidden" id="editUserEmailOld" />
            
            <!-- Photo Section -->
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">PHOTO</label>
                <div class="flex flex-col items-center gap-4">
                    <div class="relative">
                        <div id="editPhotoPreview" class="size-24 rounded-full bg-slate-100 dark:bg-slate-800 border-4 border-slate-200 dark:border-slate-700 overflow-hidden flex items-center justify-center">
                            <span class="material-symbols-outlined text-4xl text-slate-400">person</span>
                        </div>
                        <input type="file" id="editUserPhotoInput" accept="image/*" class="hidden" />
                    </div>
                    <div class="flex gap-3">
                        <button type="button" id="editUploadPhotoBtn" class="px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg font-semibold text-sm transition-colors">
                            <span class="material-symbols-outlined text-[18px] align-middle mr-1">upload</span>
                            Change Photo
                        </button>
                        <button type="button" id="editRemovePhotoBtn" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 rounded-lg font-semibold text-sm transition-colors hidden">
                            <span class="material-symbols-outlined text-[18px] align-middle mr-1">delete</span>
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2" for="editUserEmail">Email</label>
                <input type="email" id="editUserEmail" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" required />
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2" for="editUserPassword">Password</label>
                <input type="password" id="editUserPassword" class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-xl text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary/50 focus:border-primary transition-all" placeholder="Leave blank to keep current password" />
            </div>
            <div class="flex gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <button type="button" id="cancelEditBtn" class="flex-1 px-4 py-2.5 bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded-xl font-semibold hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">Cancel</button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-primary hover:bg-primary-hover text-white rounded-xl font-semibold transition-colors">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Store users data and pagination
    let usersData = [];
    let currentEditingUserId = null;
    let paginationData = {
        page: 1,
        limit: 20,
        total: 0,
        pages: 1
    };

    // Map database user_type to UI role
    function mapUserTypeToRole(userType) {
        // Map database user_type to display role:
        // 'admin' -> 'admin'
        // 'client' -> 'client'
        // 'doctor' -> 'dentist'
        // 'staff' -> 'assistant'
        // 'manager' -> 'manager'
        const mapping = {
            'admin': 'admin',
            'client': 'client',
            'doctor': 'dentist',
            'staff': 'assistant',
            'manager': 'manager'
        };
        return mapping[userType] || 'client';
    }

    // Map UI role to database user_type
    function mapRoleToUserType(role) {
        const mapping = {
            'admin': 'admin',
            'owner': 'admin', // Owner is treated as admin in database
            'client': 'client',
            'dentist': 'doctor', // Dentist maps to doctor in database
            'assistant': 'staff', // Assistant maps to staff in database
            'manager': 'manager'
        };
        return mapping[role] || 'client';
    }

    // Format date for display
    function formatDate(dateString) {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        return date.toLocaleDateString();
    }

    // Load users from database
    function loadUsersFromDatabase() {
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;
        
        // Get filter values
        const search = document.getElementById('userSearchInput')?.value.trim() || '';
        const roleFilter = document.getElementById('roleFilterSelect')?.value || '';
        const statusFilter = document.getElementById('statusFilterSelect')?.value || '';
        
        // Build query parameters
        const params = new URLSearchParams();
        if (search) params.append('search', search);
        if (roleFilter) {
            // Map UI role to database user_type
            const userType = mapRoleToUserType(roleFilter);
            params.append('user_type', userType);
        }
        if (statusFilter && statusFilter !== 'all') {
            params.append('status', statusFilter);
        }
        params.append('page', paginationData.page.toString());
        params.append('limit', paginationData.limit.toString());
        
        // Show loading state
        tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-slate-500 dark:text-slate-400"><span class="material-symbols-outlined animate-spin text-lg align-middle mr-2">sync</span> Loading users...</td></tr>';
        
        // Fetch users from API
        const url = '<?php echo BASE_URL; ?>api/users.php' + (params.toString() ? '?' + params.toString() : '');
        fetch(url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin' // Include cookies for session authentication
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            tbody.innerHTML = '';
            
            if (data.success && data.data) {
                usersData = data.data.users || [];
                
                // Store pagination data if available
                if (data.data.pagination) {
                    paginationData = {
                        page: data.data.pagination.page || 1,
                        limit: data.data.pagination.limit || 20,
                        total: data.data.pagination.total || 0,
                        pages: data.data.pagination.pages || 1
                    };
                } else {
                    // If no pagination data, set defaults based on users array
                    paginationData = {
                        page: 1,
                        limit: 20,
                        total: usersData.length,
                        pages: Math.ceil(usersData.length / 20) || 1
                    };
                }
                
                if (usersData.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-slate-500 dark:text-slate-400">No users found.</td></tr>';
                    updateUserCounts();
                    updatePagination();
                    return;
                }
                
                // Display each user
                usersData.forEach(user => {
                    const row = createUserRow(user);
                    tbody.appendChild(row);
                });
                
                updateUserCounts();
                updatePagination();
            } else {
                console.error('Error loading users:', data);
                const errorMsg = data.message || 'Unknown error';
                tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-red-500">Error loading users: ' + errorMsg + '</td></tr>';
                // Reset pagination on error
                paginationData = {
                    page: 1,
                    limit: 20,
                    total: 0,
                    pages: 1
                };
                updatePagination();
            }
        })
        .catch(error => {
            console.error('Error fetching users:', error);
            const tbody = document.getElementById('usersTableBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-red-500">Error loading users: ' + error.message + '. Please check your connection and refresh.</td></tr>';
            }
        });
    }

    // Create user row element
    function createUserRow(user) {
        const row = document.createElement('tr');
        row.setAttribute('data-user-id', user.id);
        row.setAttribute('data-user-email', user.email);
        row.className = 'group hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors';
        
        const fullName = `${user.first_name} ${user.last_name}`.trim() || user.email;
        const role = mapUserTypeToRole(user.user_type);
        const roleBadge = getRoleBadge(role);
        const baseUrl = '<?php echo rtrim(BASE_URL, "/"); ?>';
        const photoUrl = user.profile_image
            ? (user.profile_image.startsWith('http') ? user.profile_image : baseUrl + '/' + (user.profile_image || '').replace(/^\/+/, ''))
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=2563eb&color=fff&size=128`;
        const isActive = user.status === 'active';
        
        row.innerHTML = `
            <td class="py-4 pl-6 pr-4">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <div class="size-11 rounded-full bg-cover bg-center border-2 border-white dark:border-slate-800 shadow-sm" style='background-image: url("${photoUrl}");'></div>
                        <div class="absolute bottom-0 right-0 size-3 bg-green-500 border-2 border-white dark:border-slate-900 rounded-full"></div>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white text-sm">${fullName}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 user-email">${user.email}</div>
                    </div>
                </div>
            </td>
            <td class="px-4 py-4">
                ${roleBadge}
            </td>
            <td class="px-4 py-4">
                <div class="text-sm text-slate-600 dark:text-slate-300 font-medium">${formatDate(user.last_login)}</div>
            </td>
            <td class="px-4 py-4">
                <label class="inline-flex items-center cursor-pointer">
                    <input ${isActive ? 'checked' : ''} class="sr-only peer user-status-toggle" type="checkbox" data-user-id="${user.id}" data-email="${user.email}"/>
                    <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-primary/20 dark:peer-focus:ring-primary/40 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                    <span class="ms-3 text-xs font-medium text-slate-600 dark:text-slate-300 status-label">${isActive ? 'Active' : 'Inactive'}</span>
                </label>
            </td>
            <td class="px-4 py-4 pr-6 text-right">
                <div class="flex items-center justify-end gap-1 opacity-60 group-hover:opacity-100 transition-opacity">
                    <button class="p-2 text-slate-400 hover:text-primary hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-all edit-user-btn" data-user-id="${user.id}" data-email="${user.email}" title="Edit details">
                        <span class="material-symbols-outlined text-[20px]">edit</span>
                    </button>
                    <button class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 rounded-lg transition-all" title="Reset Password">
                        <span class="material-symbols-outlined text-[20px]">lock_reset</span>
                    </button>
                    <button class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-all delete-user-btn" data-user-id="${user.id}" data-email="${user.email}" title="Delete User">
                        <span class="material-symbols-outlined text-[20px]">delete</span>
                    </button>
                </div>
            </td>
        `;
        
        // Attach event listeners
        attachRowEventListeners(row);
        
        return row;
    }

    // Handle status toggle
    function attachStatusToggleListener(toggle) {
        toggle.addEventListener('change', function() {
            const userId = parseInt(this.dataset.userId);
            const isActive = this.checked;
            const status = isActive ? 'active' : 'inactive';
            
            // Update via API
            fetch('<?php echo BASE_URL; ?>api/users.php', {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: userId,
                    status: status
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
            const row = this.closest('tr');
            const statusLabel = row.querySelector('.status-label');
            if (statusLabel) {
                statusLabel.textContent = isActive ? 'Active' : 'Inactive';
            }
                    // Update local data
                    const user = usersData.find(u => u.id === userId);
                    if (user) {
                        user.status = status;
                    }
                    updateUserCounts();
                } else {
                    // Revert toggle on error
                    this.checked = !isActive;
                    alert('Failed to update user status: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error updating status:', error);
                this.checked = !isActive;
                alert('Error updating user status. Please try again.');
        });
    });
    }

    // Edit modal photo variables
    let editPhotoFile = null;
    let editPhotoPreviewUrl = null;

    // Handle edit button click (using event delegation for dynamically added buttons)
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-user-btn');
        if (editBtn) {
            const userId = parseInt(editBtn.dataset.userId);
            const user = usersData.find(u => u.id === userId);
            
            if (user) {
                currentEditingUserId = userId;
                document.getElementById('editUserEmailOld').value = user.email;
                document.getElementById('editUserEmail').value = user.email;
                document.getElementById('editUserPassword').value = '';
                
                // Load current photo (resolve to full URL if stored as path)
                editPhotoFile = null;
                const baseUrl = '<?php echo rtrim(BASE_URL, "/"); ?>';
                if (user.profile_image) {
                    editPhotoPreviewUrl = user.profile_image.startsWith('http')
                        ? user.profile_image
                        : baseUrl + '/' + (user.profile_image || '').replace(/^\/+/, '');
                } else {
                    editPhotoPreviewUrl = null;
                }
                const preview = document.getElementById('editPhotoPreview');
                if (editPhotoPreviewUrl) {
                    preview.style.backgroundImage = `url(${editPhotoPreviewUrl})`;
                    preview.style.backgroundSize = 'cover';
                    preview.style.backgroundPosition = 'center';
                    preview.innerHTML = '';
                    document.getElementById('editRemovePhotoBtn').classList.remove('hidden');
                } else {
                    preview.style.backgroundImage = '';
                    preview.innerHTML = '<span class="material-symbols-outlined text-4xl text-slate-400">person</span>';
                    document.getElementById('editRemovePhotoBtn').classList.add('hidden');
                }
                
                document.getElementById('editUserModal').classList.remove('hidden');
                document.getElementById('editUserModal').classList.add('flex');
            }
            }
    });

    // Edit modal photo upload functionality
    document.getElementById('editUploadPhotoBtn').addEventListener('click', function() {
        document.getElementById('editUserPhotoInput').click();
    });

    document.getElementById('editUserPhotoInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }
            editPhotoFile = file;
            const reader = new FileReader();
            reader.onload = function(e) {
                editPhotoPreviewUrl = e.target.result;
                const preview = document.getElementById('editPhotoPreview');
                preview.style.backgroundImage = `url(${editPhotoPreviewUrl})`;
                preview.style.backgroundSize = 'cover';
                preview.style.backgroundPosition = 'center';
                preview.innerHTML = '';
                document.getElementById('editRemovePhotoBtn').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });

    document.getElementById('editRemovePhotoBtn').addEventListener('click', function() {
        editPhotoFile = null;
        editPhotoPreviewUrl = null;
        const preview = document.getElementById('editPhotoPreview');
        preview.style.backgroundImage = '';
        preview.innerHTML = '<span class="material-symbols-outlined text-4xl text-slate-400">person</span>';
        document.getElementById('editUserPhotoInput').value = '';
        this.classList.add('hidden');
    });

    // Handle delete button click (using event delegation)
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete-user-btn');
        if (deleteBtn) {
            const userId = parseInt(deleteBtn.dataset.userId);
            const email = deleteBtn.dataset.email;
            const user = usersData.find(u => u.id === userId);
            const fullName = user ? `${user.first_name} ${user.last_name}`.trim() : email;
            
            if (confirm(`Are you sure you want to delete user: ${fullName} (${email})?`)) {
                // Delete via API
                fetch('<?php echo BASE_URL; ?>api/users.php', {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        id: userId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                    // Remove from UI
                        const row = deleteBtn.closest('tr[data-user-id]');
                        if (row) {
                    row.remove();
                        }
                        // Remove from local data
                        usersData = usersData.filter(u => u.id !== userId);
                    updateUserCounts();
                        alert('User deleted successfully!');
                    } else {
                        alert('Failed to delete user: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error deleting user:', error);
                    alert('Error deleting user. Please try again.');
                });
            }
        }
    });

    // Handle edit form submission
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!currentEditingUserId) {
            alert('No user selected for editing!');
            return;
        }
        
        const newEmail = document.getElementById('editUserEmail').value.trim();
        const newPassword = document.getElementById('editUserPassword').value.trim();
        
        if (!newEmail) {
            alert('Email is required!');
            return;
        }

        // Prepare update object
        const updateData = {
            id: currentEditingUserId,
            email: newEmail
        };
        
        // Update password if provided
        if (newPassword) {
            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters.');
                return;
            }
            updateData.password = newPassword;
        }

        // Note: Photo upload would need to be handled separately via file upload API
        // For now, we'll skip photo updates in the edit form
        
        // Update via API
        fetch('<?php echo BASE_URL; ?>api/users.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(updateData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload users to get updated data
                loadUsersFromDatabase();

        // Reset edit photo variables
        editPhotoFile = null;
        editPhotoPreviewUrl = null;
                currentEditingUserId = null;

        // Close modal
        document.getElementById('editUserModal').classList.add('hidden');
        document.getElementById('editUserModal').classList.remove('flex');
        
        alert('User updated successfully!');
            } else {
                alert('Failed to update user: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error updating user:', error);
            alert('Error updating user. Please try again.');
        });
    });

    // Reset edit modal photo state
    function resetEditModalPhoto() {
        editPhotoFile = null;
        editPhotoPreviewUrl = null;
        const preview = document.getElementById('editPhotoPreview');
        preview.style.backgroundImage = '';
        preview.innerHTML = '<span class="material-symbols-outlined text-4xl text-slate-400">person</span>';
        document.getElementById('editUserPhotoInput').value = '';
        document.getElementById('editRemovePhotoBtn').classList.add('hidden');
    }

    // Close modal handlers
    document.getElementById('closeEditModal').addEventListener('click', function() {
        document.getElementById('editUserModal').classList.add('hidden');
        document.getElementById('editUserModal').classList.remove('flex');
        resetEditModalPhoto();
    });

    document.getElementById('cancelEditBtn').addEventListener('click', function() {
        document.getElementById('editUserModal').classList.add('hidden');
        document.getElementById('editUserModal').classList.remove('flex');
        resetEditModalPhoto();
    });

    // Close modal on background click
    document.getElementById('editUserModal').addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
            this.classList.remove('flex');
            resetEditModalPhoto();
        }
    });

    // Update user counts
    function updateUserCounts() {
        const totalUsers = usersData.length;
        const activeUsers = usersData.filter(u => u.status === 'active').length;
        const activeDentists = usersData.filter(u => u.user_type === 'doctor' && u.status === 'active').length;
        const clientUsers = usersData.filter(u => u.user_type === 'client' && u.status === 'active').length;
        
        // Update count displays
        const totalUsersEl = document.querySelector('.grid .bg-white:first-child h3, .grid .bg-surface-dark:first-child h3');
        if (totalUsersEl) {
            totalUsersEl.textContent = totalUsers;
        }
        
        // Update active dentists (count users with user_type = 'doctor')
        const activeDentistsEl = document.querySelector('.grid .bg-white:nth-child(2) h3, .grid .bg-surface-dark:nth-child(2) h3');
        if (activeDentistsEl) {
            activeDentistsEl.textContent = activeDentists;
        }
        
        // Update clients (active client users)
        const clientsEl = document.querySelector('.grid .bg-white:nth-child(3) h3, .grid .bg-surface-dark:nth-child(3) h3');
        if (clientsEl) {
            clientsEl.textContent = clientUsers;
        }
    }
    
    // Update pagination UI
    function updatePagination() {
        // Find pagination text element by ID
        const paginationText = document.getElementById('paginationText');
        
        if (paginationText) {
            if (paginationData.total === 0) {
                paginationText.innerHTML = `Showing <span class="font-bold text-slate-900 dark:text-white">0</span> of <span class="font-bold text-slate-900 dark:text-white">0</span> results`;
            } else {
                const start = ((paginationData.page - 1) * paginationData.limit) + 1;
                const end = Math.min(paginationData.page * paginationData.limit, paginationData.total);
                paginationText.innerHTML = `Showing <span class="font-bold text-slate-900 dark:text-white">${start}-${end}</span> of <span class="font-bold text-slate-900 dark:text-white">${paginationData.total}</span> results`;
            }
        }
        
        // Update pagination buttons
        const paginationContainer = document.getElementById('paginationContainer');
        const paginationButtons = document.getElementById('paginationButtons');
        const prevBtn = document.getElementById('prevPageBtn');
        const nextBtn = document.getElementById('nextPageBtn');
        
        if (paginationButtons) {
            paginationButtons.innerHTML = '';
            
            // Show pagination only if there are multiple pages
            if (paginationContainer) {
                if (paginationData.pages <= 1) {
                    paginationContainer.style.display = 'none';
                } else {
                    paginationContainer.style.display = 'flex';
                }
            }
            
            // Generate page buttons only if there are multiple pages
            if (paginationData.pages > 1) {
                const maxVisiblePages = 5;
                let startPage = Math.max(1, paginationData.page - Math.floor(maxVisiblePages / 2));
                let endPage = Math.min(paginationData.pages, startPage + maxVisiblePages - 1);
                
                if (endPage - startPage < maxVisiblePages - 1) {
                    startPage = Math.max(1, endPage - maxVisiblePages + 1);
                }
                
                // First page and ellipsis
                if (startPage > 1) {
                    const btn = document.createElement('button');
                    btn.className = 'px-3.5 py-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-medium transition-colors';
                    btn.textContent = '1';
                    btn.onclick = () => goToPage(1);
                    paginationButtons.appendChild(btn);
                    
                    if (startPage > 2) {
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'px-2 py-2 text-slate-400 text-sm';
                        ellipsis.textContent = '...';
                        paginationButtons.appendChild(ellipsis);
                    }
                }
                
                // Page number buttons
                for (let i = startPage; i <= endPage; i++) {
                    const btn = document.createElement('button');
                    if (i === paginationData.page) {
                        btn.className = 'px-3.5 py-2 rounded-lg bg-primary text-white text-sm font-semibold shadow-sm';
                    } else {
                        btn.className = 'px-3.5 py-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-medium transition-colors';
                    }
                    btn.textContent = i.toString();
                    btn.onclick = () => goToPage(i);
                    paginationButtons.appendChild(btn);
                }
                
                // Last page and ellipsis
                if (endPage < paginationData.pages) {
                    if (endPage < paginationData.pages - 1) {
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'px-2 py-2 text-slate-400 text-sm';
                        ellipsis.textContent = '...';
                        paginationButtons.appendChild(ellipsis);
                    }
                    
                    const btn = document.createElement('button');
                    btn.className = 'px-3.5 py-2 rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-medium transition-colors';
                    btn.textContent = paginationData.pages.toString();
                    btn.onclick = () => goToPage(paginationData.pages);
                    paginationButtons.appendChild(btn);
                }
            }
        }
        
        // Update prev/next buttons
        if (prevBtn) {
            prevBtn.disabled = paginationData.page <= 1;
            prevBtn.onclick = (e) => {
                e.preventDefault();
                if (paginationData.page > 1) {
                    goToPage(paginationData.page - 1);
                }
            };
        }
        
        if (nextBtn) {
            nextBtn.disabled = paginationData.page >= paginationData.pages;
            nextBtn.onclick = (e) => {
                e.preventDefault();
                if (paginationData.page < paginationData.pages) {
                    goToPage(paginationData.page + 1);
                }
            };
        }
    }
    
    // Navigate to a specific page
    function goToPage(page) {
        if (page >= 1 && page <= paginationData.pages && page !== paginationData.page) {
            paginationData.page = page;
            loadUsersFromDatabase();
        }
    }

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
    
    // Get role badge HTML
    function getRoleBadge(role) {
        const badges = {
            'owner': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-100 dark:border-amber-800"><span class="size-1.5 rounded-full bg-amber-500"></span>Owner</span>',
            'admin': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800"><span class="size-1.5 rounded-full bg-indigo-500"></span>Admin</span>',
            'manager': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-100 dark:border-purple-800"><span class="size-1.5 rounded-full bg-purple-500"></span>Manager</span>',
            'dentist': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-100 dark:border-blue-800"><span class="size-1.5 rounded-full bg-blue-500"></span>Dentist</span>',
            'assistant': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300 border border-slate-200 dark:border-slate-600"><span class="size-1.5 rounded-full bg-slate-500"></span>Assistant</span>',
            'client': '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300 border border-green-100 dark:border-green-800"><span class="size-1.5 rounded-full bg-green-500"></span>Client</span>'
        };
        return badges[role] || badges['client'];
    }

    // Attach event listeners to a row
    function attachRowEventListeners(row) {
        // Status toggle
        const statusToggle = row.querySelector('.user-status-toggle');
        if (statusToggle) {
            attachStatusToggleListener(statusToggle);
        }
        
        // Edit and delete buttons are handled via event delegation in document click handlers
    }

    // Add search and filter event listeners
    const searchInput = document.getElementById('userSearchInput');
    const headerSearchInput = document.getElementById('headerUserSearchInput');
    const roleFilter = document.getElementById('roleFilterSelect');
    const statusFilter = document.getElementById('statusFilterSelect');
    
    let searchTimeout;
    
    // Function to trigger search
    function triggerSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            paginationData.page = 1; // Reset to first page on search
            loadUsersFromDatabase();
        }, 500); // Debounce search
    }
    
    // Sync both search inputs
    if (searchInput && headerSearchInput) {
        // Table search input
        searchInput.addEventListener('input', function() {
            headerSearchInput.value = searchInput.value;
            triggerSearch();
        });
        
        // Header search input
        headerSearchInput.addEventListener('input', function() {
            searchInput.value = headerSearchInput.value;
            triggerSearch();
        });
    } else if (searchInput) {
        searchInput.addEventListener('input', triggerSearch);
    } else if (headerSearchInput) {
        headerSearchInput.addEventListener('input', triggerSearch);
    }
    
    if (roleFilter) {
        roleFilter.addEventListener('change', function() {
            paginationData.page = 1; // Reset to first page on filter change
            loadUsersFromDatabase();
        });
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            paginationData.page = 1; // Reset to first page on filter change
            loadUsersFromDatabase();
        });
    }

    // Initialize everything on page load
    loadUserData();
    loadUsersFromDatabase();

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

    // ==================== PDF Export Functionality ====================
    
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
    
    // Export users to PDF
    async function exportUsersToPDF() {
        const exportBtn = document.getElementById('exportUsersBtn');
        
        // Prevent multiple clicks
        if (exportBtn && exportBtn.disabled) {
            return;
        }
        
        // Disable button during export
        if (exportBtn) {
            exportBtn.disabled = true;
            exportBtn.style.cursor = 'not-allowed';
            exportBtn.style.opacity = '0.6';
        }
        
        try {
            // Wait for jsPDF to be ready
            if (!window.jspdf) {
                let attempts = 0;
                const maxAttempts = 50;
                while (!window.jspdf && attempts < maxAttempts) {
                    await new Promise(resolve => setTimeout(resolve, 100));
                    attempts++;
                }
                
                if (!window.jspdf) {
                    throw new Error('PDF library failed to load. Please refresh the page.');
                }
            }
            
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Get all users data - fetch all pages if needed
            let allUsers = [];
            const originalPage = paginationData.page;
            const originalLimit = paginationData.limit;
            
            // Fetch all users by making requests for all pages
            if (paginationData.pages > 1) {
                for (let page = 1; page <= paginationData.pages; page++) {
                    const params = new URLSearchParams();
                    const search = document.getElementById('userSearchInput')?.value.trim() || '';
                    const roleFilter = document.getElementById('roleFilterSelect')?.value || '';
                    const statusFilter = document.getElementById('statusFilterSelect')?.value || '';
                    
                    if (search) params.append('search', search);
                    if (roleFilter) {
                        const userType = mapRoleToUserType(roleFilter);
                        params.append('user_type', userType);
                    }
                    if (statusFilter && statusFilter !== 'all') {
                        params.append('status', statusFilter);
                    }
                    params.append('page', page.toString());
                    params.append('limit', '1000'); // Get more per page for export
                    
                    const response = await fetch('<?php echo BASE_URL; ?>api/users.php' + (params.toString() ? '?' + params.toString() : ''), {
                        method: 'GET',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin'
                    });
                    
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.data && data.data.users) {
                            allUsers = allUsers.concat(data.data.users);
                        }
                    }
                }
            } else {
                // Use current users data
                allUsers = usersData;
            }
            
            if (allUsers.length === 0) {
                alert('No users to export.');
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.style.cursor = 'pointer';
                    exportBtn.style.opacity = '1';
                }
                return;
            }
            
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
            doc.text('User Accounts Report', pageWidth - margin, yPos, { align: 'right' });
            
            yPos += 8;
            doc.setFontSize(10);
            doc.setFont(undefined, 'normal');
            doc.setTextColor(100, 100, 100);
            doc.text(`Generated: ${new Date().toLocaleString()}`, pageWidth - margin, yPos, { align: 'right' });
            doc.setTextColor(0, 0, 0);
            
            // Calculate summary statistics
            const totalUsers = allUsers.length;
            const activeUsers = allUsers.filter(u => u.status === 'active').length;
            const inactiveUsers = allUsers.filter(u => u.status === 'inactive').length;
            
            // Count by role
            const roleCounts = {};
            allUsers.forEach(user => {
                const role = mapUserTypeToRole(user.user_type);
                roleCounts[role] = (roleCounts[role] || 0) + 1;
            });
            
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
                ['Total Users:', totalUsers.toString()],
                ['Active Users:', activeUsers.toString()],
                ['Inactive Users:', inactiveUsers.toString()],
                ['', ''], // Empty line
            ];
            
            // Add role breakdown
            Object.keys(roleCounts).sort().forEach(role => {
                summaryLines.push([`${role.charAt(0).toUpperCase() + role.slice(1)}:`, roleCounts[role].toString()]);
            });
            
            summaryLines.forEach(([label, value]) => {
                if (label) {
                    doc.setFont(undefined, 'bold');
                    doc.text(label, margin, yPos);
                    doc.setFont(undefined, 'normal');
                    const labelWidth = doc.getTextWidth(label);
                    doc.text(value, margin + labelWidth + 5, yPos);
                    yPos += 7;
                } else {
                    yPos += 3; // Empty line spacing
                }
            });
            
            // Users Table Section
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
            doc.text('User Accounts', margin, yPos);
            
            yPos += 8;
            doc.setDrawColor(200, 200, 200);
            doc.line(margin, yPos, pageWidth - margin, yPos);
            
            yPos += 10;
            
            // Table header
            const availableWidth = pageWidth - (margin * 2);
            const colWidths = {
                name: availableWidth * 0.30,
                email: availableWidth * 0.30,
                role: availableWidth * 0.15,
                status: availableWidth * 0.10,
                lastLogin: availableWidth * 0.15
            };
            const colPositions = {
                name: margin,
                email: margin + colWidths.name,
                role: margin + colWidths.name + colWidths.email,
                status: margin + colWidths.name + colWidths.email + colWidths.role,
                lastLogin: margin + colWidths.name + colWidths.email + colWidths.role + colWidths.status
            };
            
            // Draw table header
            doc.setFontSize(9);
            doc.setFont(undefined, 'bold');
            doc.setTextColor(255, 255, 255);
            doc.setFillColor(59, 130, 246);
            doc.rect(margin, yPos - 5, pageWidth - (margin * 2), 8, 'F');
            doc.text('Name', colPositions.name + 2, yPos);
            doc.text('Email', colPositions.email + 2, yPos);
            doc.text('Role', colPositions.role + 2, yPos);
            doc.text('Status', colPositions.status + 2, yPos);
            doc.text('Last Login', colPositions.lastLogin + 2, yPos);
            doc.setTextColor(0, 0, 0);
            yPos += 10;
            
            // Draw table rows
            doc.setFontSize(8);
            doc.setFont(undefined, 'normal');
            let rowNum = 0;
            
            allUsers.forEach((user) => {
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
                    doc.text('Name', colPositions.name + 2, yPos);
                    doc.text('Email', colPositions.email + 2, yPos);
                    doc.text('Role', colPositions.role + 2, yPos);
                    doc.text('Status', colPositions.status + 2, yPos);
                    doc.text('Last Login', colPositions.lastLogin + 2, yPos);
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
                
                const fullName = `${user.first_name || ''} ${user.last_name || ''}`.trim() || user.email;
                const role = mapUserTypeToRole(user.user_type);
                const status = user.status ? user.status.charAt(0).toUpperCase() + user.status.slice(1) : 'N/A';
                const lastLogin = formatDate(user.last_login);
                
                // Truncate text if too long
                let nameText = fullName;
                const maxNameWidth = colWidths.name - 4;
                if (doc.getTextWidth(nameText) > maxNameWidth) {
                    while (doc.getTextWidth(nameText + '...') > maxNameWidth && nameText.length > 0) {
                        nameText = nameText.substring(0, nameText.length - 1);
                    }
                    nameText += '...';
                }
                
                let emailText = user.email || 'N/A';
                const maxEmailWidth = colWidths.email - 4;
                if (doc.getTextWidth(emailText) > maxEmailWidth) {
                    while (doc.getTextWidth(emailText + '...') > maxEmailWidth && emailText.length > 0) {
                        emailText = emailText.substring(0, emailText.length - 1);
                    }
                    emailText += '...';
                }
                
                let roleText = role.charAt(0).toUpperCase() + role.slice(1);
                const maxRoleWidth = colWidths.role - 4;
                if (doc.getTextWidth(roleText) > maxRoleWidth) {
                    while (doc.getTextWidth(roleText + '...') > maxRoleWidth && roleText.length > 0) {
                        roleText = roleText.substring(0, roleText.length - 1);
                    }
                    roleText += '...';
                }
                
                let statusText = status;
                const maxStatusWidth = colWidths.status - 4;
                if (doc.getTextWidth(statusText) > maxStatusWidth) {
                    while (doc.getTextWidth(statusText + '...') > maxStatusWidth && statusText.length > 0) {
                        statusText = statusText.substring(0, statusText.length - 1);
                    }
                    statusText += '...';
                }
                
                let loginText = lastLogin;
                const maxLoginWidth = colWidths.lastLogin - 4;
                if (doc.getTextWidth(loginText) > maxLoginWidth) {
                    while (doc.getTextWidth(loginText + '...') > maxLoginWidth && loginText.length > 0) {
                        loginText = loginText.substring(0, loginText.length - 1);
                    }
                    loginText += '...';
                }
                
                doc.text(nameText, colPositions.name + 2, yPos);
                doc.text(emailText, colPositions.email + 2, yPos);
                doc.text(roleText, colPositions.role + 2, yPos);
                doc.text(statusText, colPositions.status + 2, yPos);
                doc.text(loginText, colPositions.lastLogin + 2, yPos);
                
                // Draw row border
                doc.setDrawColor(220, 220, 220);
                doc.line(margin, yPos + 2, pageWidth - margin, yPos + 2);
                
                yPos += 8;
                rowNum++;
            });
            
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
            
            // Generate PDF blob and open in new window for preview
            const fileName = `User_Accounts_Report_${new Date().toISOString().split('T')[0]}.pdf`;
            const pdfBlob = doc.output('blob');
            const pdfUrl = URL.createObjectURL(pdfBlob);
            
            // Always open preview first
            const previewWindow = window.open(pdfUrl, '_blank', 'width=800,height=600');
            
            if (previewWindow) {
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.style.cursor = 'pointer';
                    exportBtn.style.opacity = '1';
                }
                
                // Clean up blob URL after a delay
                setTimeout(() => {
                    URL.revokeObjectURL(pdfUrl);
                }, 10000);
            } else {
                // If popup was blocked, fall back to download
                alert('Popup was blocked. Downloading PDF instead...');
                doc.save(fileName);
                if (exportBtn) {
                    exportBtn.disabled = false;
                    exportBtn.style.cursor = 'pointer';
                    exportBtn.style.opacity = '1';
                }
                URL.revokeObjectURL(pdfUrl);
            }
            
        } catch (error) {
            console.error('Error exporting PDF:', error);
            alert('Error generating PDF: ' + (error.message || 'Unknown error'));
            if (exportBtn) {
                exportBtn.disabled = false;
                exportBtn.style.cursor = 'pointer';
                exportBtn.style.opacity = '1';
            }
        }
    }
    
    // Event listener for export button
    document.getElementById('exportUsersBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        exportUsersToPDF();
    });
</script>