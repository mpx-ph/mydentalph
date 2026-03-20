<?php
/**
 * Admin My Profile Page
 * Requires admin/staff/doctor authentication
 */
$pageTitle = 'My Profile - Admin';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// Require manager, staff, doctor login
$userType = $_SESSION['user_type'] ?? '';
if (!in_array($userType, ['manager', 'staff', 'doctor'])) {
    requireLogin('manager'); // This will redirect if not logged in
}

require_once __DIR__ . '/includes/header.php';
?>
<style>
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .dark ::-webkit-scrollbar-thumb { background: #475569; }
    .form-input:focus {
        border-color: #2b8cee !important;
        box-shadow: 0 0 0 2px rgba(43, 140, 238, 0.1) !important;
    }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200 font-display transition-colors duration-300 antialiased selection:bg-primary/20 selection:text-primary">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>
<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<div class="flex-1 overflow-y-auto px-6 py-6 lg:px-10 pb-10 scroll-smooth">
<div class="max-w-3xl mx-auto w-full flex flex-col gap-8">
<!-- Profile Header Section -->
<section class="flex flex-col items-center text-center gap-6">
<div class="relative group">
<div class="bg-center bg-no-repeat aspect-square bg-cover rounded-full size-40 border-4 border-white dark:border-slate-800 shadow-xl" id="profileImageDisplay" style='background-image: url("https://ui-avatars.com/api/?name=<?php echo urlencode(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'); ?>&background=2b8cee&color=fff&size=128");'></div>
<button id="editPhotoBtn" class="absolute bottom-2 right-2 bg-primary text-white p-2.5 rounded-full shadow-lg hover:bg-blue-600 transition-all cursor-pointer">
<span class="material-symbols-outlined text-lg">edit</span>
</button>
<input type="file" id="photoInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display: none;">
</div>
<div>
<h1 id="profileName" class="text-slate-900 dark:text-white text-3xl font-black tracking-tight"><?php echo htmlspecialchars(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Loading...'); ?></h1>
<p id="staffIdDisplay" class="text-slate-500 dark:text-slate-400 font-medium text-lg">Loading...</p>
</div>
</section>

<!-- Personal Information Card -->
<section class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
<div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
<span class="material-symbols-outlined text-primary">contact_page</span>
<h3 class="text-lg font-bold text-slate-900 dark:text-white">Personal Details</h3>
</div>
<div class="p-8">
<form id="personalDetailsForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">Username</label>
<input id="username" name="username" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary bg-slate-100 dark:bg-slate-900 cursor-not-allowed" type="text" readonly/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">Email Address</label>
<input id="email" name="email" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" type="email" required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">First Name</label>
<input id="first_name" name="first_name" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" type="text" required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">Last Name</label>
<input id="last_name" name="last_name" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" type="text" required/>
</div>
</form>
<div class="mt-8 flex justify-end gap-3">
<button id="cancelPersonalBtn" class="rounded-lg px-6 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold hover:bg-slate-200 transition-colors">Cancel</button>
<button id="savePersonalBtn" class="rounded-lg px-6 py-2.5 bg-primary text-white font-bold hover:bg-blue-600 shadow-md shadow-primary/20 transition-all">Save Changes</button>
</div>
</div>
</section>

<!-- Security Card -->
<section class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
<div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center gap-2">
<span class="material-symbols-outlined text-primary">security</span>
<h3 class="text-lg font-bold text-slate-900 dark:text-white">Security Settings</h3>
</div>
<div class="p-8">
<p class="text-sm text-slate-600 dark:text-slate-400 mb-6">Update your password to keep your account secure.</p>
<form id="passwordForm" class="grid grid-cols-1 gap-6">
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">Current Password</label>
<input id="current_password" name="current_password" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" placeholder="••••••••" type="password" required/>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">New Password</label>
<input id="new_password" name="new_password" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" placeholder="••••••••" type="password" required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">Confirm New Password</label>
<input id="confirm_password" name="confirm_password" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" placeholder="••••••••" type="password" required/>
</div>
</div>
</form>
<div class="mt-8 flex justify-between items-center">
<button id="resetPasswordBtn" class="text-sm text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 font-semibold flex items-center gap-2">
<span class="material-symbols-outlined text-base">lock_reset</span>
<span>Reset Password via Email</span>
</button>
<button id="updatePasswordBtn" class="rounded-lg px-6 py-2.5 bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 font-bold hover:opacity-90 transition-all">Update Password</button>
</div>
</div>
</section>

<!-- Reset Password Modal -->
<div id="resetPasswordModal" class="hidden fixed inset-0 bg-black/50 dark:bg-black/70 z-50 flex items-center justify-center p-4">
<div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl max-w-md w-full p-6 relative">
<button id="closeResetPasswordModal" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300">
<span class="material-symbols-outlined">close</span>
</button>
<h3 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Reset Password</h3>
<p class="text-sm text-slate-600 dark:text-slate-400 mb-6">We'll send a verification code to your email address.</p>

<!-- Step 1: Request Code -->
<div id="resetPasswordStep1" class="space-y-4">
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">Email Address</label>
<input id="resetPasswordEmail" type="email" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" placeholder="your@email.com" required/>
</div>
<button id="requestResetCodeBtn" class="w-full rounded-lg px-6 py-2.5 bg-red-600 text-white font-bold hover:bg-red-700 transition-all">Send Reset Code</button>
</div>

<!-- Step 2: Enter Code and New Password -->
<div id="resetPasswordStep2" class="hidden space-y-4">
<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
<p class="text-sm text-blue-900 dark:text-blue-200">
<span class="material-symbols-outlined align-middle text-base">info</span>
<span class="ml-2">Verification code sent to your email. Check your inbox.</span>
</p>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">Verification Code</label>
<input id="resetPasswordCode" type="text" maxlength="6" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary text-center text-2xl tracking-widest font-mono" placeholder="000000" required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">New Password</label>
<input id="resetPasswordNew" type="password" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" placeholder="••••••••" required/>
</div>
<div class="flex flex-col gap-2">
<label class="text-sm font-bold text-slate-700 dark:text-slate-300">Confirm New Password</label>
<input id="resetPasswordConfirm" type="password" class="rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-primary focus:border-primary" placeholder="••••••••" required/>
</div>
<button id="submitResetPasswordBtn" class="w-full rounded-lg px-6 py-2.5 bg-red-600 text-white font-bold hover:bg-red-700 transition-all">Reset Password</button>
<button id="backToStep1Btn" class="w-full rounded-lg px-6 py-2.5 bg-slate-200 dark:bg-slate-700 text-slate-900 dark:text-white font-bold hover:opacity-90 transition-all">Back</button>
</div>
</div>
</div>
</div>
</div>
</main>
<script>
const API_BASE = '<?php echo BASE_URL; ?>api/admin_profile.php';

// Load profile data from API
async function loadProfileData() {
    try {
        const response = await fetch(API_BASE, {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            const profile = data.data;
            
            // Update profile header
            const profileNameEl = document.getElementById('profileName');
            const staffIdEl = document.getElementById('staffIdDisplay');
            if (profileNameEl) {
                profileNameEl.textContent = `${profile.first_name || ''} ${profile.last_name || ''}`.trim() || '<?php echo htmlspecialchars(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User'); ?>';
            }
            if (staffIdEl) {
                if (profile.staff_display_id) {
                    staffIdEl.textContent = `Staff ID: ${profile.staff_display_id}`;
                } else if (profile.user_id) {
                    staffIdEl.textContent = `User ID: ${profile.user_id}`;
                } else {
                    staffIdEl.textContent = '';
                }
            }
            
            // Update profile image if available
            const profileImageEl = document.getElementById('profileImageDisplay');
            if (profileImageEl && profile.profile_image) {
                const imageUrl = profile.profile_image.startsWith('http') 
                    ? profile.profile_image 
                    : '<?php echo BASE_URL; ?>' + profile.profile_image;
                profileImageEl.style.backgroundImage = `url("${imageUrl}")`;
            } else if (profileImageEl) {
                // Use default avatar based on name
                const name = `${profile.first_name || ''} ${profile.last_name || ''}`.trim() || 'User';
                profileImageEl.style.backgroundImage = `url("https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&background=2b8cee&color=fff&size=128")`;
            }
            
            // Populate form fields
            if (document.getElementById('username')) document.getElementById('username').value = profile.username || '';
            if (document.getElementById('email')) document.getElementById('email').value = profile.email || '';
            if (document.getElementById('first_name')) document.getElementById('first_name').value = profile.first_name || '';
            if (document.getElementById('last_name')) document.getElementById('last_name').value = profile.last_name || '';
        } else {
            console.error('Failed to load profile:', data.message);
        }
    } catch (error) {
        console.error('Error loading profile:', error);
    }
}

// Save personal details
async function savePersonalDetails() {
    const form = document.getElementById('personalDetailsForm');
    if (!form) return;
    
    const saveBtn = document.getElementById('savePersonalBtn');
    const originalText = saveBtn ? saveBtn.textContent : 'Save Changes';
    
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';
    }
    
    const formData = {
        first_name: document.getElementById('first_name').value,
        last_name: document.getElementById('last_name').value,
        email: document.getElementById('email').value
    };
    
    try {
        const response = await fetch(API_BASE, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (saveBtn) {
                saveBtn.textContent = 'Saved!';
                saveBtn.classList.add('bg-green-500', 'hover:bg-green-600');
                setTimeout(() => {
                    saveBtn.textContent = originalText;
                    saveBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                    saveBtn.disabled = false;
                }, 2000);
            }
            // Reload profile data to update display
            await loadProfileData();
        } else {
            alert(data.message || 'Failed to save profile');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = originalText;
            }
        }
    } catch (error) {
        console.error('Error saving profile:', error);
        alert('An error occurred while saving. Please try again.');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = originalText;
        }
    }
}

// Update password
async function updatePassword() {
    const currentPassword = document.getElementById('current_password').value;
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword !== confirmPassword) {
        alert('New password and confirm password do not match.');
        return;
    }
    
    const updateBtn = document.getElementById('updatePasswordBtn');
    const originalText = updateBtn ? updateBtn.textContent : 'Update Password';
    
    if (updateBtn) {
        updateBtn.disabled = true;
        updateBtn.textContent = 'Updating...';
    }
    
    const formData = {
        update_type: 'password',
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword
    };
    
    try {
        const response = await fetch(API_BASE, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (updateBtn) {
                updateBtn.textContent = 'Updated!';
                updateBtn.classList.add('bg-green-500', 'hover:bg-green-600');
                setTimeout(() => {
                    updateBtn.textContent = originalText;
                    updateBtn.classList.remove('bg-green-500', 'hover:bg-green-600');
                    updateBtn.disabled = false;
                }, 2000);
            }
            // Clear password fields
            document.getElementById('current_password').value = '';
            document.getElementById('new_password').value = '';
            document.getElementById('confirm_password').value = '';
        } else {
            alert(data.message || 'Failed to update password');
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.textContent = originalText;
            }
        }
    } catch (error) {
        console.error('Error updating password:', error);
        alert('An error occurred while updating password. Please try again.');
        if (updateBtn) {
            updateBtn.disabled = false;
            updateBtn.textContent = originalText;
        }
    }
}

// Form event handlers
const savePersonalBtn = document.getElementById('savePersonalBtn');
if (savePersonalBtn) {
    savePersonalBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const form = document.getElementById('personalDetailsForm');
        if (form && form.checkValidity()) {
            savePersonalDetails();
        } else {
            form.reportValidity();
        }
    });
}

const cancelPersonalBtn = document.getElementById('cancelPersonalBtn');
if (cancelPersonalBtn) {
    cancelPersonalBtn.addEventListener('click', function(e) {
        e.preventDefault();
        loadProfileData(); // Reload to reset form
    });
}

const updatePasswordBtn = document.getElementById('updatePasswordBtn');
if (updatePasswordBtn) {
    updatePasswordBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const form = document.getElementById('passwordForm');
        if (form && form.checkValidity()) {
            updatePassword();
        } else {
            form.reportValidity();
        }
    });
}

// Reset Password Modal Functionality
const resetPasswordModal = document.getElementById('resetPasswordModal');
const resetPasswordBtn = document.getElementById('resetPasswordBtn');
const closeResetPasswordModal = document.getElementById('closeResetPasswordModal');
const resetPasswordStep1 = document.getElementById('resetPasswordStep1');
const resetPasswordStep2 = document.getElementById('resetPasswordStep2');
const requestResetCodeBtn = document.getElementById('requestResetCodeBtn');
const submitResetPasswordBtn = document.getElementById('submitResetPasswordBtn');
const backToStep1Btn = document.getElementById('backToStep1Btn');
const resetPasswordEmail = document.getElementById('resetPasswordEmail');
const resetPasswordCode = document.getElementById('resetPasswordCode');
const resetPasswordNew = document.getElementById('resetPasswordNew');
const resetPasswordConfirm = document.getElementById('resetPasswordConfirm');

// Open modal
if (resetPasswordBtn) {
    resetPasswordBtn.addEventListener('click', function() {
        if (resetPasswordModal) {
            resetPasswordModal.classList.remove('hidden');
            // Pre-fill email from profile
            const emailInput = document.getElementById('email');
            if (emailInput && emailInput.value) {
                resetPasswordEmail.value = emailInput.value;
            }
            // Reset to step 1
            resetPasswordStep1.classList.remove('hidden');
            resetPasswordStep2.classList.add('hidden');
            resetPasswordEmail.value = '';
            resetPasswordCode.value = '';
            resetPasswordNew.value = '';
            resetPasswordConfirm.value = '';
        }
    });
}

// Close modal
if (closeResetPasswordModal) {
    closeResetPasswordModal.addEventListener('click', function() {
        if (resetPasswordModal) {
            resetPasswordModal.classList.add('hidden');
        }
    });
}

// Close modal on background click
if (resetPasswordModal) {
    resetPasswordModal.addEventListener('click', function(e) {
        if (e.target === resetPasswordModal) {
            resetPasswordModal.classList.add('hidden');
        }
    });
}

// Request reset code
if (requestResetCodeBtn) {
    requestResetCodeBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        const email = resetPasswordEmail.value.trim();
        
        if (!email) {
            alert('Please enter your email address.');
            return;
        }

        requestResetCodeBtn.disabled = true;
        requestResetCodeBtn.textContent = 'Sending...';

        try {
            const response = await fetch('<?php echo BASE_URL; ?>api/reset_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'request',
                    email: email
                })
            });

            const data = await response.json();

            if (data.success) {
                // Move to step 2
                resetPasswordStep1.classList.add('hidden');
                resetPasswordStep2.classList.remove('hidden');
            } else {
                alert(data.message || 'Failed to send reset code. Please try again.');
            }
        } catch (error) {
            console.error('Error requesting reset code:', error);
            alert('An error occurred. Please try again.');
        } finally {
            requestResetCodeBtn.disabled = false;
            requestResetCodeBtn.textContent = 'Send Reset Code';
        }
    });
}

// Submit password reset
if (submitResetPasswordBtn) {
    submitResetPasswordBtn.addEventListener('click', async function(e) {
        e.preventDefault();
        const email = resetPasswordEmail.value.trim();
        const code = resetPasswordCode.value.trim();
        const newPassword = resetPasswordNew.value;
        const confirmPassword = resetPasswordConfirm.value;

        if (!code || code.length !== 6) {
            alert('Please enter a valid 6-digit verification code.');
            return;
        }

        if (newPassword.length < 8) {
            alert('Password must be at least 8 characters.');
            return;
        }

        if (newPassword !== confirmPassword) {
            alert('New password and confirm password do not match.');
            return;
        }

        submitResetPasswordBtn.disabled = true;
        submitResetPasswordBtn.textContent = 'Resetting...';

        try {
            const response = await fetch('<?php echo BASE_URL; ?>api/reset_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'reset',
                    email: email,
                    code: code,
                    new_password: newPassword
                })
            });

            const data = await response.json();

            if (data.success) {
                alert(data.message || 'Password reset successfully! You can now login with your new password.');
                resetPasswordModal.classList.add('hidden');
                // Clear all fields
                resetPasswordEmail.value = '';
                resetPasswordCode.value = '';
                resetPasswordNew.value = '';
                resetPasswordConfirm.value = '';
            } else {
                alert(data.message || 'Failed to reset password. Please try again.');
            }
        } catch (error) {
            console.error('Error resetting password:', error);
            alert('An error occurred. Please try again.');
        } finally {
            submitResetPasswordBtn.disabled = false;
            submitResetPasswordBtn.textContent = 'Reset Password';
        }
    });
}

// Back to step 1
if (backToStep1Btn) {
    backToStep1Btn.addEventListener('click', function() {
        resetPasswordStep1.classList.remove('hidden');
        resetPasswordStep2.classList.add('hidden');
        resetPasswordCode.value = '';
        resetPasswordNew.value = '';
        resetPasswordConfirm.value = '';
    });
}

// Auto-format verification code input
if (resetPasswordCode) {
    resetPasswordCode.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
    });
}

// Photo upload functionality with cropping
const photoInput = document.getElementById('photoInput');
const editPhotoBtn = document.getElementById('editPhotoBtn');

if (editPhotoBtn && photoInput) {
    editPhotoBtn.addEventListener('click', function() {
        photoInput.click();
    });
}

// Handle file selection
if (photoInput) {
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            showCropModal(file);
        }
    });
}

// Show crop modal
let cropperInstance = null;
function showCropModal(file) {
    // Validate file type
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        alert('Please select a valid image file (JPEG, PNG, GIF, or WebP).');
        return;
    }
    
    // Validate file size (5MB max)
    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if (file.size > maxSize) {
        alert('File size must be less than 5MB.');
        return;
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'cropModal';
    modal.className = 'fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-4';
    modal.innerHTML = `
        <div class="bg-white dark:bg-slate-900 rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h3 class="text-xl font-bold text-slate-900 dark:text-white">Crop & Position Profile Photo</h3>
                <button id="closeCropModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="flex-1 overflow-auto p-6">
                <div class="flex flex-col md:flex-row gap-6">
                    <div class="flex-1">
                        <img id="cropImage" src="" alt="Crop preview" class="max-w-full h-auto">
                    </div>
                    <div class="w-full md:w-64 flex flex-col gap-4">
                        <div class="bg-slate-50 dark:bg-slate-800 rounded-lg p-4">
                            <h4 class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-2">Preview</h4>
                            <div class="w-32 h-32 mx-auto rounded-full overflow-hidden border-4 border-white dark:border-slate-700 shadow-lg bg-slate-200 dark:bg-slate-700" id="previewContainer">
                                <img id="previewImage" src="" alt="Preview" class="w-full h-full object-cover">
                            </div>
                        </div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 space-y-1">
                            <p>• Drag to reposition</p>
                            <p>• Scroll to zoom</p>
                            <p>• Recommended: Square images work best</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex justify-end gap-3">
                <button id="cancelCrop" class="rounded-lg px-6 py-2.5 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold hover:bg-slate-200 transition-colors">Cancel</button>
                <button id="saveCrop" class="rounded-lg px-6 py-2.5 bg-primary text-white font-bold hover:bg-blue-600 shadow-md shadow-primary/20 transition-all">Save Photo</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Load image
    const reader = new FileReader();
    reader.onload = function(e) {
        const cropImage = document.getElementById('cropImage');
        const previewImage = document.getElementById('previewImage');
        cropImage.src = e.target.result;
        previewImage.src = e.target.result;
        
        // Initialize cropper
        cropperInstance = new Cropper(cropImage, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.8,
            restore: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
            responsive: true
        });
        
        // Update preview function with throttling for smooth performance
        let previewUpdateTimer = null;
        function updatePreview() {
            if (previewUpdateTimer) {
                clearTimeout(previewUpdateTimer);
            }
            previewUpdateTimer = setTimeout(function() {
                if (cropperInstance) {
                    try {
                        const canvas = cropperInstance.getCroppedCanvas({
                            width: 400,
                            height: 400,
                            imageSmoothingEnabled: true,
                            imageSmoothingQuality: 'high'
                        });
                        if (canvas) {
                            previewImage.src = canvas.toDataURL();
                        }
                    } catch (e) {
                        console.error('Preview update error:', e);
                    }
                }
            }, 50); // Throttle to 50ms for smooth updates
        }
        
        // Update preview in real-time when crop area changes
        cropImage.addEventListener('ready', function() {
            updatePreview();
        });
        
        cropImage.addEventListener('crop', function() {
            updatePreview();
        });
        
        cropImage.addEventListener('cropmove', function() {
            updatePreview();
        });
        
        cropImage.addEventListener('cropend', function() {
            updatePreview();
        });
        
        cropImage.addEventListener('zoom', function() {
            updatePreview();
        });
    };
    reader.readAsDataURL(file);
    
    // Close modal handlers
    const closeModal = () => {
        if (cropperInstance) {
            cropperInstance.destroy();
            cropperInstance = null;
        }
        document.body.removeChild(modal);
        photoInput.value = '';
    };
    
    document.getElementById('closeCropModal').addEventListener('click', closeModal);
    document.getElementById('cancelCrop').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
    
    // Save cropped image
    document.getElementById('saveCrop').addEventListener('click', function() {
        if (!cropperInstance) return;
        
        const canvas = cropperInstance.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        if (canvas) {
            canvas.toBlob(function(blob) {
                const croppedFile = new File([blob], file.name, { type: file.type });
                closeModal();
                uploadPhoto(croppedFile);
            }, file.type, 0.9);
        }
    });
}

// Upload photo function
async function uploadPhoto(file) {
    // Show loading state
    const editBtn = document.getElementById('editPhotoBtn');
    const originalHTML = editBtn ? editBtn.innerHTML : '';
    if (editBtn) {
        editBtn.disabled = true;
        editBtn.innerHTML = '<span class="material-symbols-outlined text-lg animate-spin">sync</span>';
    }
    
    // Convert file to base64
    const reader = new FileReader();
    reader.onload = async function(e) {
        const base64Data = e.target.result;
        
        try {
            const formData = {
                photo: base64Data
            };
            
            const response = await fetch(API_BASE + '?action=upload_photo', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update profile image display
                const profileImageEl = document.getElementById('profileImageDisplay');
                if (profileImageEl && data.data && data.data.profile_image) {
                    const imageUrl = data.data.profile_image.startsWith('http') 
                        ? data.data.profile_image 
                        : '<?php echo BASE_URL; ?>' + data.data.profile_image;
                    profileImageEl.style.backgroundImage = `url("${imageUrl}")`;
                }
                
                // Show success message
                if (editBtn) {
                    editBtn.innerHTML = '<span class="material-symbols-outlined text-lg">check_circle</span>';
                    setTimeout(() => {
                        editBtn.innerHTML = originalHTML;
                        editBtn.disabled = false;
                    }, 2000);
                }
                
                // Reload profile data to ensure everything is in sync
                await loadProfileData();
            } else {
                alert(data.message || 'Failed to upload photo. Please try again.');
                if (editBtn) {
                    editBtn.disabled = false;
                    editBtn.innerHTML = originalHTML;
                }
            }
        } catch (error) {
            console.error('Error uploading photo:', error);
            alert('An error occurred while uploading the photo. Please try again.');
            if (editBtn) {
                editBtn.disabled = false;
                editBtn.innerHTML = originalHTML;
            }
        }
    };
    
    reader.onerror = function() {
        alert('Error reading file. Please try again.');
        if (editBtn) {
            editBtn.disabled = false;
            editBtn.innerHTML = originalHTML;
        }
    };
    
    reader.readAsDataURL(file);
}

// Load profile data on page load
loadProfileData();
</script>
</body>
</html>
