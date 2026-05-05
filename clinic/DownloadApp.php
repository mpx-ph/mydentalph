<?php
/**
 * Download App - Set Appointment / App download page
 * Uses clinic_slug from URL or session so the page reflects the clinic site the user came from.
 */
$pageTitle = 'Download App - Set Appointment';
require_once __DIR__ . '/config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    clinic_session_start();
}
// Use clinic_slug from URL; if missing, use session (e.g. link opened as DownloadApp.php without query)
if (empty($_GET['clinic_slug']) && !empty($_SESSION['public_tenant_slug'])) {
    $_GET['clinic_slug'] = $_SESSION['public_tenant_slug'];
}

require_once __DIR__ . '/tenant_bootstrap.php';
require_once __DIR__ . '/includes/clinic_customization.php';
require_once __DIR__ . '/includes/auth.php';

// Only allow logged-in patients to view this page
if (!isLoggedIn('client')) {
    $clinicSlug = '';
    if (!empty($_GET['clinic_slug'])) {
        $clinicSlug = trim((string) $_GET['clinic_slug']);
    } elseif (isset($currentTenantSlug) && $currentTenantSlug !== '') {
        $clinicSlug = $currentTenantSlug;
    }

    if ($clinicSlug !== '') {
        header('Location: ' . BASE_URL . 'Login.php?clinic_slug=' . rawurlencode($clinicSlug));
    } else {
        header('Location: ' . BASE_URL . 'Login.php');
    }
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>
<?php include __DIR__ . '/includes/nav_client.php'; ?>

<style>
.bg-soft-gradient {
    background: radial-gradient(circle at 0% 0%, rgba(43, 139, 235, 0.05) 0%, transparent 50%),
                radial-gradient(circle at 100% 100%, rgba(43, 139, 235, 0.08) 0%, transparent 50%);
}
.fade-in {
    animation: fadeIn 0.8s ease-out forwards;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
<main class="min-h-screen bg-soft-gradient flex flex-col items-center pt-32 pb-20 lg:pt-40 lg:pb-24 px-6 relative overflow-hidden">
<!-- Text Content -->
<div class="text-center max-w-3xl mx-auto z-10 fade-in">
<h1 class="text-4xl md:text-6xl lg:text-7xl font-bold text-slate-900 dark:text-white leading-[1.1] mb-6">
                Quality Healthcare at Your Convenience
            </h1>
<p class="text-gray-500 text-lg md:text-xl mb-10 max-w-2xl mx-auto leading-relaxed">
                Book appointments, consult online, and access your health records easily. 
                All your healthcare needs are now in one app.
            </p>
<!-- Store Button -->
<div class="flex justify-center mb-20" data-purpose="store-buttons">
<a class="inline-flex items-center justify-center bg-slate-900 text-white px-6 py-3 rounded-full hover:scale-105 transition-transform shadow-lg font-medium" href="https://drive.google.com/uc?id=18yvg4NJIM_xjIPW1L_KwpoFJnd4TpqfS&export=download">Download</a>
</div>
</div>
<!-- Mockup Display Section (Triple Phone Layout like IMAGE_66) -->
<div class="relative w-full max-w-6xl mx-auto flex justify-center items-end gap-0 md:gap-4 fade-in" style="animation-delay: 0.3s;">
<!-- Left Phone (Partially Visible) -->
<div class="hidden md:block relative w-[280px] h-[500px] bg-white rounded-[2.5rem] border-[8px] border-slate-900 shadow-2xl overflow-hidden translate-y-12 rotate-[-5deg]">
<div class="absolute inset-0 bg-gray-50">
<div class="p-6 space-y-4">
<div class="h-40 bg-primary/10 rounded-2xl flex items-center justify-center">
<div class="w-12 h-12 bg-primary rounded-full opacity-20"></div>
</div>
<div class="space-y-2">
<div class="h-4 w-3/4 bg-gray-200 rounded"></div>
<div class="h-4 w-1/2 bg-gray-200 rounded"></div>
</div>
</div>
</div>
</div>
<!-- Central Main Phone (Largest) -->
<div class="relative w-[320px] h-[640px] bg-white rounded-[3rem] border-[10px] border-slate-900 shadow-[0_20px_50px_rgba(0,0,0,0.1)] overflow-hidden z-10">
<!-- Phone Top Bar -->
<div class="absolute top-0 inset-x-0 h-8 flex items-center justify-center pt-2">
<div class="w-20 h-5 bg-slate-900 rounded-b-2xl"></div>
</div>
<!-- App Content Simulation -->
<div class="pt-12 p-6 space-y-6">
<div class="flex items-center justify-between">
<div class="flex items-center gap-3">
<div class="w-10 h-10 rounded-full bg-primary/10 overflow-hidden border border-primary/20"></div>
<div>
<p class="text-[10px] text-gray-400">Welcome,</p>
<p class="text-xs font-bold text-slate-900 dark:text-white">Alex Johnson</p>
</div>
</div>
<div class="w-8 h-8 rounded-full bg-gray-50 flex items-center justify-center">
<div class="w-4 h-0.5 bg-gray-300 rounded-full"></div>
</div>
</div>
<div class="bg-primary rounded-2xl p-4 text-white">
<p class="text-sm font-medium mb-1">Bringing Healthcare Closer to You!</p>
<p class="text-[10px] opacity-80 mb-4">Book your next consultation in minutes.</p>
<div class="flex gap-2">
<div class="h-6 w-16 bg-white/20 rounded-full"></div>
<div class="h-6 w-16 bg-white/20 rounded-full"></div>
</div>
</div>
<div class="space-y-3">
<p class="text-xs font-bold text-slate-900 dark:text-white">Featured Doctors</p>
<div class="grid grid-cols-2 gap-3">
<div class="h-28 bg-gray-50 rounded-xl p-3 border border-gray-100">
<div class="w-8 h-8 bg-primary/20 rounded-full mb-2"></div>
<div class="h-2 w-12 bg-gray-300 rounded mb-1"></div>
<div class="h-2 w-8 bg-gray-200 rounded"></div>
</div>
<div class="h-28 bg-gray-50 rounded-xl p-3 border border-gray-100">
<div class="w-8 h-8 bg-primary/20 rounded-full mb-2"></div>
<div class="h-2 w-12 bg-gray-300 rounded mb-1"></div>
<div class="h-2 w-8 bg-gray-200 rounded"></div>
</div>
</div>
</div>
</div>
<!-- Bottom Navigation -->
<div class="absolute bottom-0 w-full h-16 bg-white border-t border-gray-50 flex items-center justify-around px-4">
<div class="w-5 h-5 bg-primary rounded-md opacity-40"></div>
<div class="w-5 h-5 bg-gray-200 rounded-md"></div>
<div class="w-10 h-10 bg-primary rounded-full -mt-8 border-4 border-white shadow-lg"></div>
<div class="w-5 h-5 bg-gray-200 rounded-md"></div>
<div class="w-5 h-5 bg-gray-200 rounded-md"></div>
</div>
</div>
<!-- Right Phone (Partially Visible) -->
<div class="hidden md:block relative w-[280px] h-[500px] bg-white rounded-[2.5rem] border-[8px] border-slate-900 shadow-2xl overflow-hidden translate-y-12 rotate-[5deg]">
<div class="absolute inset-0 bg-gray-50">
<div class="p-6 space-y-4">
<div class="h-8 w-24 bg-primary/10 rounded-full mb-6"></div>
<div class="space-y-3">
<div class="flex items-center gap-3 bg-white p-3 rounded-xl shadow-sm">
<div class="w-8 h-8 bg-gray-100 rounded-full"></div>
<div class="flex-1 h-2 bg-gray-100 rounded"></div>
</div>
<div class="flex items-center gap-3 bg-white p-3 rounded-xl shadow-sm">
<div class="w-8 h-8 bg-gray-100 rounded-full"></div>
<div class="flex-1 h-2 bg-gray-100 rounded"></div>
</div>
</div>
</div>
</div>
</div>
<!-- Floater Widgets (Extra Detail like in IMAGE_66) -->
<div class="absolute -left-10 top-1/4 hidden lg:block bg-white p-4 rounded-2xl shadow-xl border border-gray-100 animate-bounce duration-[3000ms]">
<div class="flex items-center gap-3">
<div class="w-10 h-10 bg-primary rounded-lg"></div>
<div>
<p class="text-xs font-bold">Upcoming Appt</p>
<p class="text-[10px] text-gray-400">Tomorrow at 10:00 AM</p>
</div>
</div>
</div>
<div class="absolute -right-10 top-1/3 hidden lg:block bg-white p-4 rounded-2xl shadow-xl border border-gray-100 animate-pulse">
<div class="flex items-center gap-3">
<div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-[10px] font-bold">✓</div>
<div>
<p class="text-xs font-bold">Payment Success</p>
<p class="text-[10px] text-gray-400">$120.00 Received</p>
</div>
</div>
</div>
</div>
</main>
<!-- Decorative Blurs -->
<div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
<div class="absolute top-[20%] left-[-10%] w-[500px] h-[500px] bg-primary/[0.05] rounded-full blur-[120px]"></div>
<div class="absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] bg-primary/[0.03] rounded-full blur-[150px]"></div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>