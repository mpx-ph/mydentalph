<?php
$staff_nav_active = 'block_schedule';
require_once __DIR__ . '/config/config.php';

if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}
$currentStaffUserId = isset($_SESSION['user_id']) ? (string) $_SESSION['user_id'] : '';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Block Schedule - Staff Portal</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                "primary": "#2b8beb",
                "background": "#f8fafc",
                "surface": "#ffffff",
                "on-background": "#101922",
                "on-surface-variant": "#404752"
            },
            fontFamily: {
                "headline": ["Manrope", "sans-serif"],
                "body": ["Manrope", "sans-serif"],
                "editorial": ["Playfair Display", "serif"]
            }
        }
    }
};
</script>
<style>
body { font-family: "Manrope", sans-serif; }
.material-symbols-outlined { vertical-align: middle; }
.mesh-bg {
    background-color: #f8fafc;
    background-image: radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%), radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.01) 0px, transparent 50%);
}
.elevated-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
}
</style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20">
<?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>
<div class="p-10 space-y-8">
    <section class="flex flex-col gap-4">
        <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
            <span class="w-12 h-[1.5px] bg-primary"></span> SCHEDULE MANAGEMENT
        </div>
        <div class="flex items-end justify-between gap-6">
            <div>
                <h2 class="font-headline text-6xl font-extrabold tracking-tighter leading-tight text-on-background">
                    Block <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Schedule</span>
                </h2>
                <p class="font-body text-xl font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-4">
                    Manage and view blocked schedules to prevent appointments during specific times.
                </p>
            </div>
        </div>
    </section>

    <!-- Block Schedule Form -->
    <section class="elevated-card p-8 rounded-3xl">
        <h3 class="text-lg font-bold text-slate-800 mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">event_busy</span> Add Block Schedule
        </h3>
        <form id="blockScheduleForm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_today</span>
                        <input id="blockDate" required class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" type="date"/>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Start Time <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">schedule</span>
                        <input id="blockStartTime" required class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none" type="time"/>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">End Time <span class="text-rose-500">*</span></label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">schedule</span>
                        <input id="blockEndTime" required class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold appearance-none" type="time"/>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Reason (Optional)</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">notes</span>
                        <input id="blockReason" class="w-full bg-slate-50 border-none rounded-xl py-2.5 pl-10 pr-4 outline-none focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold" placeholder="e.g. Clinic Maintenance" type="text"/>
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end">
                 <button type="submit" id="saveBlockBtn" class="bg-primary hover:bg-primary/90 text-white px-8 py-3.5 rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-primary/30 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">lock_clock</span>
                    Block Schedule
                </button>
            </div>
        </form>
    </section>

    <!-- Blocked Schedules Table -->
    <section class="elevated-card rounded-3xl overflow-hidden mt-2">
        <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-white text-on-background">
             <h3 class="text-md font-bold text-on-background flex items-center gap-2">
                Recently Blocked Schedules
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/50">
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Date</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Schedule Time</th>
                        <th class="px-6 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest">Reason</th>
                        <th class="px-8 py-5 text-[10px] font-black text-slate-400 uppercase tracking-widest text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="schedulesTableBody" class="divide-y divide-slate-100">
                    <tr><td colspan="4" class="px-6 py-10 text-center text-slate-500 font-medium">Loading schedules...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="p-6 bg-slate-50/30 border-t border-slate-100">
            <p id="recordsSummary" class="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Showing 0 blocked schedules</p>
        </div>
    </section>
</div>
</main>

<script>
// Mock data and API implementation for demonstration. 
// Replace API_SCHEDULES_URL with the real API endpoint later.
const API_SCHEDULES_URL = '/api/block_schedule.php'; 

let allSchedulesData = [
    // Provide a mocked example to match the layout
    { id: 1, date: '2026-04-10', start_time: '10:00', end_time: '12:00', reason: 'Clinic Maintenance' },
    { id: 2, date: '2026-04-15', start_time: '14:00', end_time: '16:00', reason: 'Staff Meeting' }
];

const tableBody = document.getElementById('schedulesTableBody');
const blockScheduleForm = document.getElementById('blockScheduleForm');
const recordsSummary = document.getElementById('recordsSummary');

function escapeHtml(text) {
    return String(text || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return escapeHtml(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
}

function formatTime(timeStr) {
    if (!timeStr) return '';
    const [hours, minutes] = timeStr.split(':');
    if (!hours || !minutes) return escapeHtml(timeStr);
    const date = new Date();
    date.setHours(parseInt(hours, 10));
    date.setMinutes(parseInt(minutes, 10));
    return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

function renderSchedules(schedules) {
    if (!schedules.length) {
        tableBody.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-slate-500 font-medium">No blocked schedules found.</td></tr>';
        recordsSummary.textContent = `Showing 0 blocked schedules`;
        return;
    }

    tableBody.innerHTML = schedules.map(schedule => {
        return \`
            <tr class="hover:bg-slate-50/30 transition-colors group">
                <td class="px-8 py-6">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center text-orange-500">
                             <span class="material-symbols-outlined text-lg">event</span>
                        </div>
                        <span class="text-sm font-bold text-slate-900 group-hover:text-primary transition-colors">\${escapeHtml(formatDate(schedule.date))}</span>
                    </div>
                </td>
                <td class="px-6 py-6 text-sm font-bold text-slate-700">
                    <div class="flex items-center gap-1.5 bg-slate-50 w-fit px-3 py-1.5 rounded-lg border border-slate-100">
                        <span class="material-symbols-outlined text-[14px] text-slate-400">schedule</span>
                        \${escapeHtml(formatTime(schedule.start_time))} - \${escapeHtml(formatTime(schedule.end_time))}
                    </div>
                </td>
                <td class="px-6 py-6 text-sm font-medium text-slate-600">
                    \${escapeHtml(schedule.reason || 'N/A')}
                </td>
                <td class="px-8 py-6 text-right">
                    <div class="flex justify-end gap-2">
                        <button data-action="delete" data-id="\${schedule.id}" class="w-9 h-9 flex items-center justify-center border border-slate-200 text-slate-400 hover:text-rose-500 hover:border-rose-100 rounded-xl transition-all" title="Remove Block">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                </td>
            </tr>
        \`;
    }).join('');

    recordsSummary.textContent = \`Showing \${schedules.length} blocked \${schedules.length === 1 ? 'schedule' : 'schedules'}\`;
}

// Initial render since we are mocking for now
renderSchedules(allSchedulesData);

async function saveBlockSchedule(event) {
    event.preventDefault();
    
    const dateInput = document.getElementById('blockDate').value;
    const startTimeInput = document.getElementById('blockStartTime').value;
    const endTimeInput = document.getElementById('blockEndTime').value;
    const reasonInput = document.getElementById('blockReason').value.trim();

    if(startTimeInput >= endTimeInput) {
        alert("End time must be after start time.");
        return;
    }

    const saveBtn = document.getElementById('saveBlockBtn');
    const oldHtml = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">sync</span> Saving...';

    // Simulate saving via API
    setTimeout(() => {
        const newSchedule = {
            id: Date.now(), 
            date: dateInput, 
            start_time: startTimeInput, 
            end_time: endTimeInput, 
            reason: reasonInput
        };
        allSchedulesData.unshift(newSchedule); // Add to the top of the array
        renderSchedules(allSchedulesData);
        
        blockScheduleForm.reset();
        
        saveBtn.disabled = false;
        saveBtn.innerHTML = oldHtml;
    }, 500);
}

function deleteSchedule(id) {
    if (!confirm('Are you sure you want to remove this blocked schedule limit?')) return;
    
    // Simulate removing via API
    allSchedulesData = allSchedulesData.filter(s => s.id !== id);
    renderSchedules(allSchedulesData);
}

blockScheduleForm.addEventListener('submit', saveBlockSchedule);

tableBody.addEventListener('click', function (event) {
    const button = event.target.closest('button[data-action]');
    if (!button) return;
    const action = button.getAttribute('data-action');
    const id = Number(button.getAttribute('data-id'));
    
    if(action === 'delete') {
        deleteSchedule(id);
    }
});

</script>
</body>
</html>
