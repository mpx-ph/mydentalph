<?php
/**
 * Admin Payment Settings Page
 * Requires admin authentication
 */
$pageTitle = 'Payment Settings - Dental Clinic Admin';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin(); // Require admin role
require_once __DIR__ . '/includes/header.php';
?>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen flex">
<?php include __DIR__ . '/includes/nav_admin.php'; ?>

<main class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden">
<header class="h-20 border-b border-slate-200 dark:border-slate-800 bg-surface-light dark:bg-surface-dark flex items-center justify-between px-8 sticky top-0 z-10 shrink-0">
<div>
<h1 class="text-2xl font-bold">Payment Settings</h1>
<p class="text-sm text-slate-500 dark:text-slate-400">Configure how patients are billed during booking. These settings apply globally to all clinic appointments.</p>
</div>
</header>
<div class="flex-1 overflow-y-auto p-8">
<div class="mx-auto max-w-4xl space-y-8">
<div class="mb-8 p-6 bg-blue-50/50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/50 rounded-2xl flex gap-4 items-start">
<div class="flex-shrink-0 w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
<span class="material-symbols-outlined text-primary">info</span>
</div>
<div>
<h3 class="font-semibold text-blue-900 dark:text-blue-300 mb-1">Configuration Impact</h3>
<p class="text-blue-800/70 dark:text-blue-400/80 text-sm leading-relaxed">
                    Changes made here will immediately affect how patients are charged for new bookings. These rules do not retroactively affect existing appointments or pending payments.
                </p>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
<div class="bg-surface-light dark:bg-surface-dark p-8 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
<div class="flex items-center gap-3 mb-6">
<div class="w-10 h-10 bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 rounded-xl flex items-center justify-center">
<span class="material-symbols-outlined">percent</span>
</div>
<div>
<h2 class="font-semibold font-display text-lg">Regular Services</h2>
<p class="text-xs text-slate-400">Non-orthodontic procedures</p>
</div>
</div>
<div class="space-y-4">
<label class="block">
<span class="text-sm font-medium text-slate-600 dark:text-slate-400 block mb-2">Downpayment Percentage</span>
<div class="relative group">
<input id="nonOrthoPercentage" class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-xl py-3 px-4 focus:ring-2 focus:ring-primary focus:border-primary transition-all pr-12 text-lg font-medium" placeholder="0" type="number" step="0.01" min="0" max="100" value="20"/>
<span class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 font-medium">%</span>
</div>
<p class="mt-2 text-xs text-slate-400 leading-normal italic">
                            Minimum % of total service price required upfront.
                        </p>
</label>
<button id="saveNonOrthoBtn" class="w-full bg-primary hover:bg-blue-600 text-white font-medium py-3 rounded-xl transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-sm">save</span>
                        Update Rule
                    </button>
</div>
</div>
<div class="bg-surface-light dark:bg-surface-dark p-8 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md transition-shadow">
<div class="flex items-center gap-3 mb-6">
<div class="w-10 h-10 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl flex items-center justify-center">
<span class="material-symbols-outlined">payments</span>
</div>
<div>
<h2 class="font-semibold font-display text-lg">Orthodontics</h2>
<p class="text-xs text-slate-400">Braces &amp; alignment services</p>
</div>
</div>
<div class="space-y-4">
<label class="block">
<span class="text-sm font-medium text-slate-600 dark:text-slate-400 block mb-2">Fixed Minimum Amount</span>
<div class="relative group">
<input id="orthoMinAmount" class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-xl py-3 px-10 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 transition-all text-lg font-medium" placeholder="0" type="number" step="100" min="0" value="5000"/>
<span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-medium">₱</span>
</div>
<p class="mt-2 text-xs text-slate-400 leading-normal italic">
                            Minimum fixed amount for orthodontic packages.
                        </p>
</label>
<button id="saveOrthoBtn" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-medium py-3 rounded-xl transition-all shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2">
<span class="material-symbols-outlined text-sm">save</span>
                        Update Rule
                    </button>
</div>
</div>
</div>
<div class="bg-surface-light dark:bg-surface-dark border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden">
<div class="px-8 py-6 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-slate-400">rule</span>
<h2 class="font-display font-semibold text-lg">Active Payment Logic</h2>
</div>
<span class="px-3 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 text-[10px] uppercase tracking-widest font-bold rounded-full">Live Rules</span>
</div>
<div class="divide-y divide-slate-100 dark:divide-slate-800">
<div class="px-8 py-5 flex items-center gap-6 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
<div class="w-2 h-2 rounded-full bg-primary shadow-sm shadow-primary"></div>
<div class="flex-grow">
<span class="font-medium text-slate-900 dark:text-white">Regular Services:</span>
<span class="text-slate-500 dark:text-slate-400 ml-1">Patients must pay at least <strong class="text-primary" id="activeNonOrthoPercentage">20%</strong> of the total price upfront.</span>
</div>
<span class="material-symbols-outlined text-slate-300 text-sm">check_circle</span>
</div>
<div class="px-8 py-5 flex items-center gap-6 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
<div class="w-2 h-2 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500"></div>
<div class="flex-grow">
<span class="font-medium text-slate-900 dark:text-white">Orthodontic Services:</span>
<span class="text-slate-500 dark:text-slate-400 ml-1">Minimum downpayment of <strong class="text-emerald-600 dark:text-emerald-400" id="activeOrthoMinAmount">₱5,000</strong> required.</span>
</div>
<span class="material-symbols-outlined text-slate-300 text-sm">check_circle</span>
</div>
<div class="px-8 py-5 flex items-center gap-6 hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition-colors">
<div class="w-2 h-2 rounded-full bg-amber-400 shadow-sm shadow-amber-400"></div>
<div class="flex-grow">
<span class="font-medium text-slate-900 dark:text-white">Full Payment Option:</span>
<span class="text-slate-500 dark:text-slate-400 ml-1">Patients can always choose to pay the <strong class="text-slate-700 dark:text-slate-300">full amount</strong> at booking.</span>
</div>
<span class="material-symbols-outlined text-slate-300 text-sm">check_circle</span>
</div>
</div>
<div class="bg-slate-50 dark:bg-slate-900/30 px-8 py-4 flex items-center justify-between text-xs text-slate-400">
<p>Last modified by <span class="text-slate-600 dark:text-slate-300 font-medium">Dr. Peterson</span> • Oct 24, 2023</p>
<div class="flex items-center gap-4">
<button class="hover:text-primary transition-colors flex items-center gap-1">
<span class="material-symbols-outlined text-[16px]">history</span> History
                    </button>
<button class="hover:text-primary transition-colors flex items-center gap-1">
<span class="material-symbols-outlined text-[16px]">print</span> Export PDF
                    </button>
</div>
</div>
</div>
</div>
</div>
</main>

<script>
// Load payment settings on page load
async function loadPaymentSettings() {
    try {
        const response = await fetch('<?php echo BASE_URL; ?>api/payment_settings.php', {
            method: 'GET',
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success && data.data) {
            // Update input fields
            const orthoMinInput = document.getElementById('orthoMinAmount');
            const nonOrthoPercentageInput = document.getElementById('nonOrthoPercentage');
            
            if (orthoMinInput) {
                orthoMinInput.value = data.data.orthodontics_min_downpayment || 5000;
            }
            
            if (nonOrthoPercentageInput) {
                // Convert decimal to percentage (0.20 -> 20)
                const percentage = (data.data.non_orthodontics_downpayment_percentage || 0.20) * 100;
                nonOrthoPercentageInput.value = percentage;
            }
            
            // Update active rules display
            updateActiveRulesDisplay(data.data);
        }
    } catch (error) {
        console.error('Error loading payment settings:', error);
    }
}

// Update active rules display
function updateActiveRulesDisplay(settings) {
    const activeNonOrthoEl = document.getElementById('activeNonOrthoPercentage');
    const activeOrthoEl = document.getElementById('activeOrthoMinAmount');
    
    if (activeNonOrthoEl) {
        const percentage = (settings.non_orthodontics_downpayment_percentage || 0.20) * 100;
        activeNonOrthoEl.textContent = percentage + '%';
    }
    
    if (activeOrthoEl) {
        const amount = settings.orthodontics_min_downpayment || 5000;
        activeOrthoEl.textContent = '₱' + parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        });
    }
}

// Save orthodontics minimum downpayment
document.getElementById('saveOrthoBtn')?.addEventListener('click', async function() {
    const input = document.getElementById('orthoMinAmount');
    const value = parseFloat(input.value);
    
    if (isNaN(value) || value < 0) {
        alert('Please enter a valid amount.');
        return;
    }
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">hourglass_empty</span> Saving...';
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>api/payment_settings.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                orthodontics_min_downpayment: value
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update active rules display
            updateActiveRulesDisplay(data.data);
            
            // Show success message
            showMessage('Orthodontics minimum downpayment updated successfully!', 'success');
        } else {
            alert(data.message || 'Failed to update settings.');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Save non-orthodontics downpayment percentage
document.getElementById('saveNonOrthoBtn')?.addEventListener('click', async function() {
    const input = document.getElementById('nonOrthoPercentage');
    const value = parseFloat(input.value);
    
    if (isNaN(value) || value < 0 || value > 100) {
        alert('Please enter a valid percentage (0-100).');
        return;
    }
    
    // Convert percentage to decimal (20 -> 0.20)
    const decimalValue = value / 100;
    
    const btn = this;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">hourglass_empty</span> Saving...';
    
    try {
        const response = await fetch('<?php echo BASE_URL; ?>api/payment_settings.php', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                non_orthodontics_downpayment_percentage: decimalValue
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Update active rules display
            updateActiveRulesDisplay(data.data);
            
            // Show success message
            showMessage('Non-orthodontics downpayment percentage updated successfully!', 'success');
        } else {
            alert(data.message || 'Failed to update settings.');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
});

// Simple message display function
function showMessage(message, type) {
    // Create or get message container
    let messageContainer = document.getElementById('settingsMessage');
    if (!messageContainer) {
        messageContainer = document.createElement('div');
        messageContainer.id = 'settingsMessage';
        messageContainer.className = 'fixed top-24 right-8 z-50 p-4 rounded-xl shadow-lg max-w-sm';
        document.body.appendChild(messageContainer);
    }
    
    const bgColor = type === 'success' ? 'bg-green-500' : 'bg-red-500';
    messageContainer.className = `fixed top-24 right-8 z-50 p-4 rounded-xl shadow-lg max-w-sm ${bgColor} text-white`;
    messageContainer.textContent = message;
    messageContainer.style.display = 'block';
    
    // Hide after 3 seconds
    setTimeout(() => {
        messageContainer.style.display = 'none';
    }, 3000);
}

// Load settings on page load
loadPaymentSettings();
</script>
</body>
</html>