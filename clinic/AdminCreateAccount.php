<?php
/**
 * Admin Create Account Page
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

// If accessed via /{slug}/AdminCreateAccount.php, bootstrap tenant context for branding
$clinic_slug = isset($_GET['clinic_slug']) ? strtolower(trim((string) $_GET['clinic_slug'])) : '';
if ($clinic_slug !== '' && preg_match('/^[a-z0-9\-]+$/', $clinic_slug)) {
    $_GET['clinic_slug'] = $clinic_slug;
    require_once __DIR__ . '/tenant_bootstrap.php';
    if (isset($currentTenantId) && $currentTenantId) {
        $_SESSION['public_tenant_id'] = $currentTenantId;
        $_SESSION['public_tenant_slug'] = $clinic_slug;
    }
}

require_once __DIR__ . '/includes/clinic_customization.php';
$createLogo = isset($CLINIC['logo']) ? trim($CLINIC['logo']) : 'DRCGLogo.png';
$createLogoUrl = (strpos($createLogo, 'http') === 0) ? $createLogo : (BASE_URL . ltrim($createLogo, '/'));
$createLogoAlt = isset($CLINIC['clinic_name']) ? htmlspecialchars($CLINIC['clinic_name'], ENT_QUOTES, 'UTF-8') : 'Dental Clinic';
$createHex = function($k) use ($CLINIC) {
    $v = isset($CLINIC[$k]) ? preg_replace('/^#/', '', trim($CLINIC[$k])) : '';
    return (strlen($v) === 6 && ctype_xdigit($v)) ? '#' . $v : '';
};
$createPrimary = $createHex('color_primary') ?: '#2b8cee';
$createDarkBlue = $createHex('color_primary_dark') ?: '#1e6bb5';
$createDeepBlue = $createHex('color_primary_dark') ?: '#164e85';

// Check if already logged in as manager
if (isLoggedIn('manager')) {
    header("Location: " . clinicPageUrl('AdminDashboard.php'));
    exit();
}

$adminLoginUrl = clinicPageUrl('Login.php');
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Admin Registration - Dental Care Portal</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,typography,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: "<?php echo $createPrimary; ?>",
                        darkBlue: "<?php echo $createDarkBlue; ?>",
                        deepBlue: "<?php echo $createDeepBlue; ?>",
                    },
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
                    },
                },
            },
        };
    </script>
<style type="text/tailwindcss">
        @layer base {
            body {
                @apply font-sans antialiased text-slate-900;
            }
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 300, 'GRAD' 0, 'opsz' 24;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .bg-gradient-blue {
            background: radial-gradient(circle at center, <?php echo $createPrimary; ?> 0%, <?php echo $createDarkBlue; ?> 100%);
        }
        @keyframes pulse-border {
            0%, 100% {
                border-color: rgb(43, 140, 238);
                box-shadow: 0 0 0 0 rgba(43, 140, 238, 0.4);
            }
            50% {
                border-color: rgb(30, 107, 181);
                box-shadow: 0 0 0 4px rgba(43, 140, 238, 0.1);
            }
        }
        .checking-username {
            animation: pulse-border 1.5s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gradient-blue min-h-screen flex items-center justify-center p-6 sm:p-12 relative overflow-x-hidden">
<div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-400/20 rounded-full blur-[120px]"></div>
<div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-300/20 rounded-full blur-[120px]"></div>
<div class="w-full max-w-2xl relative z-10">
<div class="flex flex-col items-center mb-8 text-white">
<img src="<?php echo $createLogoUrl; ?>" alt="<?php echo $createLogoAlt; ?>" class="h-20 object-contain mb-4"/>
</div>
<div class="glass-card rounded-[2.5rem] shadow-2xl overflow-hidden p-8 sm:p-12">
<div class="text-center mb-10">
<h2 class="text-3xl font-extrabold text-slate-800 mb-3">Create Admin Account</h2>
<div class="h-1 w-20 bg-primary mx-auto rounded-full mb-4"></div>
<p class="text-slate-500 max-w-sm mx-auto">Register a new management user for the secure internal clinic portal.</p>
</div>
<form id="createAccountForm" class="space-y-6">
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="space-y-2">
<label class="block text-[11px] font-bold text-primary uppercase tracking-widest ml-1" for="first-name">First Name</label>
<div class="relative group">
<span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 group-focus-within:text-primary transition-colors">person</span>
<input class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="first-name" placeholder="John" type="text"/>
</div>
</div>
<div class="space-y-2">
<label class="block text-[11px] font-bold text-primary uppercase tracking-widest ml-1" for="last-name">Last Name</label>
<div class="relative group">
<span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 group-focus-within:text-primary transition-colors">person</span>
<input class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="last-name" placeholder="Doe" type="text"/>
</div>
</div>
</div>
<div class="space-y-2">
<label class="block text-[11px] font-bold text-primary uppercase tracking-widest ml-1" for="email">Email Address</label>
<div class="relative group">
<span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 group-focus-within:text-primary transition-colors">mail</span>
<input class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="email" placeholder="admin@clinic.com" type="email"/>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="space-y-2">
<label class="block text-[11px] font-bold text-primary uppercase tracking-widest ml-1" for="username">Username</label>
<div class="relative group">
<span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 group-focus-within:text-primary transition-colors">alternate_email</span>
<input class="w-full pl-12 pr-12 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="username" placeholder="johndoe_admin" type="text"/>
<span id="usernameStatusIcon" class="absolute right-4 top-1/2 -translate-y-1/2 hidden"></span>
</div>
<div id="usernameMessage" class="hidden mt-2 text-xs"></div>
</div>
<div class="space-y-2">
<label class="block text-[11px] font-bold text-primary uppercase tracking-widest ml-1" for="password">Password</label>
<div class="relative group">
<span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 group-focus-within:text-primary transition-colors">lock</span>
<input class="w-full pl-12 pr-4 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all placeholder:text-slate-400" id="password" placeholder="••••••••" type="password"/>
</div>
</div>
</div>
<div class="space-y-2">
<label class="block text-[11px] font-bold text-primary uppercase tracking-widest ml-1" for="user-type">Account Role</label>
<div class="relative group">
<span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-primary/50 group-focus-within:text-primary transition-colors">badge</span>
<select class="w-full pl-12 pr-10 py-3.5 bg-slate-50 border border-slate-100 rounded-2xl focus:ring-4 focus:ring-primary/10 focus:border-primary appearance-none transition-all cursor-pointer text-slate-700" id="user-type">
<option value="manager">Manager</option>
<option value="doctor">Doctor</option>
<option value="staff">Staff</option>
</select>
        <span class="absolute right-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 pointer-events-none">expand_more</span>
    </div>
</div>
<div class="flex items-start py-1">
<div class="flex h-6 items-center">
<input class="h-5 w-5 rounded border-slate-300 text-primary focus:ring-primary/20 bg-slate-50 cursor-pointer" id="terms" name="terms" type="checkbox" required/>
</div>
<div class="ml-3 text-sm leading-6">
<label class="font-medium text-slate-600 select-none" for="terms">I agree to the <a class="text-primary hover:text-darkBlue hover:underline font-semibold transition-colors" href="<?php echo BASE_URL; ?>TermsofService.php">Terms of Service</a> and <a class="text-primary hover:text-darkBlue hover:underline font-semibold transition-colors" href="<?php echo BASE_URL; ?>PrivacyPolicy.php">Privacy Policy</a></label>
</div>
</div>
<div class="pt-4">
<button class="w-full bg-primary hover:bg-darkBlue text-white font-bold py-5 px-8 rounded-2xl transition-all shadow-xl shadow-primary/30 active:scale-[0.98] text-sm uppercase tracking-widest flex items-center justify-center space-x-3" type="submit">
<span class="material-symbols-outlined text-xl">how_to_reg</span>
<span>Create Account</span>
</button>
</div>
</form>
<div class="mt-10 flex flex-col items-center space-y-6">
<div class="flex items-center space-x-6 text-slate-400">
<div class="flex items-center space-x-2">
<span class="material-symbols-outlined text-primary text-xl">verified_user</span>
<span class="text-[10px] font-bold uppercase tracking-widest">256-bit SSL</span>
</div>
<div class="w-px h-4 bg-slate-200"></div>
<div class="flex items-center space-x-2">
<span class="material-symbols-outlined text-primary text-xl">security</span>
<span class="text-[10px] font-bold uppercase tracking-widest">Secure Portal</span>
</div>
</div>
<div class="w-full pt-6 border-t border-slate-100 flex flex-col items-center">
<p class="text-slate-500 text-sm mb-4">Already have administrative access?</p>
<a class="group inline-flex items-center text-primary font-bold text-sm hover:text-darkBlue transition-colors" href="<?php echo BASE_URL; ?>ProviderMyDentalSSO.php">
<span class="material-symbols-outlined text-xl mr-2 group-hover:translate-x-[-2px] transition-transform">login</span>
                        Login using MyDental
                    </a>
</div>
</div>
</div>
<footer class="mt-8 text-center text-white/60 text-[10px] uppercase tracking-[0.2em] leading-loose">
            © 2026 <?php echo htmlspecialchars(trim((string) ($CLINIC['clinic_name'] ?? '')) !== '' ? $CLINIC['clinic_name'] : '(Business Name) Dental Clinic', ENT_QUOTES, 'UTF-8'); ?> Management System.<br/>
            All rights reserved. Internal Use Only.
        </footer>
</div>

<script>
    // Username availability checker
    let usernameCheckTimeout;
    let isUsernameAvailable = false;
    let isCheckingUsername = false;
    
    const usernameInput = document.getElementById('username');
    const usernameStatusIcon = document.getElementById('usernameStatusIcon');
    const usernameMessage = document.getElementById('usernameMessage');
    
    function checkUsernameAvailability(username) {
        // Clear previous timeout
        clearTimeout(usernameCheckTimeout);
        
        // Reset state
        isUsernameAvailable = false;
        usernameStatusIcon.classList.add('hidden');
        usernameMessage.classList.add('hidden');
        usernameInput.classList.remove('border-red-500', 'border-green-500', 'border-primary', 'checking-username');
        
        // If username is empty or too short, don't check
        if (!username || username.length < 3) {
            return;
        }
        
        // Validate username format
        if (!/^[a-zA-Z0-9_-]{3,20}$/.test(username)) {
            usernameStatusIcon.classList.remove('hidden');
            usernameStatusIcon.innerHTML = '<span class="material-symbols-outlined text-red-500">error</span>';
            usernameMessage.classList.remove('hidden');
            usernameMessage.className = 'mt-2 text-xs text-red-600';
            usernameMessage.textContent = 'Username must be 3-20 characters and contain only letters, numbers, underscores, or hyphens.';
            usernameInput.classList.add('border-red-500');
            return;
        }
        
        // Show checking state with better loading indicator
        isCheckingUsername = true;
        usernameStatusIcon.classList.remove('hidden');
        usernameStatusIcon.innerHTML = '<span class="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></span>';
        usernameMessage.classList.remove('hidden');
        usernameMessage.className = 'mt-2 text-xs text-primary flex items-center gap-2';
        usernameMessage.innerHTML = '<span class="w-4 h-4 border-2 border-primary border-t-transparent rounded-full animate-spin"></span><span>Checking availability...</span>';
        usernameInput.classList.remove('border-red-500', 'border-green-500', 'checking-username');
        usernameInput.classList.add('border-primary', 'checking-username');
        
        // Debounce: wait 500ms after user stops typing
        usernameCheckTimeout = setTimeout(() => {
            fetch('<?php echo BASE_URL; ?>api/check_username.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ username: username })
            })
            .then(response => response.json())
            .then(data => {
                isCheckingUsername = false;
                
                if (data.success && data.data && data.data.available) {
                    // Username is available
                    isUsernameAvailable = true;
                    usernameStatusIcon.classList.remove('hidden');
                    usernameStatusIcon.innerHTML = '<span class="material-symbols-outlined text-green-500">check_circle</span>';
                    usernameMessage.classList.remove('hidden');
                    usernameMessage.className = 'mt-2 text-xs text-green-600';
                    usernameMessage.textContent = 'Username is available.';
                    usernameInput.classList.remove('border-red-500', 'border-primary', 'checking-username');
                    usernameInput.classList.add('border-green-500');
                } else {
                    // Username is taken
                    isUsernameAvailable = false;
                    usernameStatusIcon.classList.remove('hidden');
                    usernameStatusIcon.innerHTML = '<span class="material-symbols-outlined text-red-500">cancel</span>';
                    usernameMessage.classList.remove('hidden');
                    usernameMessage.className = 'mt-2 text-xs text-red-600';
                    usernameMessage.textContent = data.message || 'Username is already taken.';
                    usernameInput.classList.remove('border-green-500', 'border-primary', 'checking-username');
                    usernameInput.classList.add('border-red-500');
                }
            })
            .catch(error => {
                isCheckingUsername = false;
                console.error('Username check error:', error);
                // Show error message on network failure
                usernameStatusIcon.classList.remove('hidden');
                usernameStatusIcon.innerHTML = '<span class="material-symbols-outlined text-amber-500">warning</span>';
                usernameMessage.classList.remove('hidden');
                usernameMessage.className = 'mt-2 text-xs text-amber-600';
                usernameMessage.textContent = 'Unable to verify username. Please try again.';
                usernameInput.classList.remove('border-primary', 'border-green-500', 'checking-username');
                usernameInput.classList.add('border-amber-500');
            });
        }, 500);
    }
    
    // Add event listener for username input
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            const username = this.value.trim();
            checkUsernameAvailability(username);
        });
        
        usernameInput.addEventListener('blur', function() {
            const username = this.value.trim();
            if (username) {
                checkUsernameAvailability(username);
            }
        });
    }
    
    // Handle form submission via AJAX
    document.getElementById('createAccountForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Get form data
        const formData = {
            first_name: document.getElementById('first-name').value.trim(),
            last_name: document.getElementById('last-name').value.trim(),
            email: document.getElementById('email').value.trim(),
            username: document.getElementById('username').value.trim(),
            password: document.getElementById('password').value,
            mobile: '',
            user_type: document.getElementById('user-type').value
        };
        
        // Validation
        if (!formData.first_name || !formData.last_name || !formData.email || !formData.username || !formData.password) {
            showMessage('Please fill in all required fields.', 'error');
            return;
        }
        
        // Check terms acceptance
        const termsAccepted = document.getElementById('terms').checked;
        if (!termsAccepted) {
            showMessage('Please accept the Terms of Service and Privacy Policy.', 'error');
            return;
        }
        
        // Check username availability before submitting
        if (isCheckingUsername) {
            showMessage('Please wait while we check username availability.', 'error');
            return;
        }
        
        if (!isUsernameAvailable && formData.username) {
            showMessage('Please choose an available username.', 'error');
            usernameInput.focus();
            return;
        }
        
        // Validate email format
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(formData.email)) {
            showMessage('Please enter a valid email address.', 'error');
            return;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="flex items-center justify-center space-x-3"><span class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span><span>Creating Account...</span></span>';
        
        // Create message container if it doesn't exist
        let messageDiv = document.getElementById('createAccountMessage');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'createAccountMessage';
            messageDiv.className = 'mb-6';
            form.insertBefore(messageDiv, form.firstChild);
        }
        messageDiv.classList.add('hidden');
        
        // Send JSON request
        const apiUrl = '<?php echo BASE_URL; ?>api/create_user.php';
        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(formData)
        })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('HTTP error! status: ' + response.status + ', body: ' + text);
                });
            }
            // Check if response is JSON
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            } else {
                // If not JSON, try to parse as text
                return response.text().then(text => {
                    throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                });
            }
        })
        .then(data => {
            if (!data) {
                showMessage('No response from server. Please check your connection.', 'error');
                return;
            }
            
            if (data.success) {
                showMessage('Account created successfully! Redirecting to login page...', 'success');
                // Redirect to login page after 2 seconds
                setTimeout(() => {
                    window.location.href = '<?php echo addslashes($adminLoginUrl); ?>';
                }, 2000);
            } else {
                showMessage(data.message || 'Failed to create account. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('Create Account Error:', error);
            let errorMessage = 'An error occurred. Please try again.';
            
            // Provide more specific error messages
            if (error.message.includes('HTTP error')) {
                errorMessage = 'Server error. Please check your database connection.';
            } else if (error.message.includes('JSON') || error.message.includes('Unexpected token')) {
                errorMessage = 'Server returned invalid response. Please check the browser console (F12) for details.';
            } else if (error.message) {
                errorMessage = 'Error: ' + error.message;
            }
            
            showMessage(errorMessage, 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
    
    function showMessage(message, type) {
        let messageDiv = document.getElementById('createAccountMessage');
        if (!messageDiv) {
            messageDiv = document.createElement('div');
            messageDiv.id = 'createAccountMessage';
            messageDiv.className = 'mb-6';
            const form = document.getElementById('createAccountForm');
            form.insertBefore(messageDiv, form.firstChild);
        }
        
        messageDiv.className = 'mb-6 p-4 rounded-2xl text-sm font-medium';
        
        if (type === 'success') {
            messageDiv.classList.add('bg-green-50', 'text-green-800', 'border', 'border-green-200');
            messageDiv.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-green-600">check_circle</span>
                    <span>${message}</span>
                </div>
            `;
        } else {
            messageDiv.classList.add('bg-red-50', 'text-red-800', 'border', 'border-red-200');
            messageDiv.innerHTML = `
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <span>${message}</span>
                </div>
            `;
        }
        
        messageDiv.classList.remove('hidden');
        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
</script>

</body></html>