<?php
$staff_nav_active = 'clinic_hours';
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$clinicHoursRows = [
    ['day' => 'Sunday', 'open_time' => '09:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    ['day' => 'Monday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    ['day' => 'Tuesday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    ['day' => 'Wednesday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    ['day' => 'Thursday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    ['day' => 'Friday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    ['day' => 'Saturday', 'open_time' => '09:00 AM', 'close_time' => '03:00 PM', 'is_closed' => false],
];

$rowsByDay = [];
foreach ($clinicHoursRows as $row) {
    $rowsByDay[$row['day']] = $row;
}

$today = new DateTimeImmutable('today');
$weekStartInput = isset($_GET['week_start']) ? trim((string) $_GET['week_start']) : '';
$selectedDate = null;
if ($weekStartInput !== '') {
    $selectedDate = DateTimeImmutable::createFromFormat('Y-m-d', $weekStartInput);
    if (!($selectedDate instanceof DateTimeImmutable)) {
        $selectedDate = null;
    }
}
if (!($selectedDate instanceof DateTimeImmutable)) {
    $selectedDate = $today;
}

$selectedWeekStart = $selectedDate->modify('last sunday');
if ((int) $selectedDate->format('w') === 0) {
    $selectedWeekStart = $selectedDate;
}
$currentWeekStart = $today->modify('last sunday');
if ((int) $today->format('w') === 0) {
    $currentWeekStart = $today;
}
$currentWeekStartTs = (int) $currentWeekStart->format('U');
$selectedWeekStartTs = (int) $selectedWeekStart->format('U');
if ($selectedWeekStartTs < $currentWeekStartTs) {
    $selectedWeekStart = $currentWeekStart;
}

$selectedWeekEnd = $selectedWeekStart->modify('+6 days');
$prevWeekStart = $selectedWeekStart->modify('-7 days')->format('Y-m-d');
$nextWeekStart = $selectedWeekStart->modify('+7 days')->format('Y-m-d');
$isCurrentWeek = $selectedWeekStart->format('Y-m-d') === $currentWeekStart->format('Y-m-d');

$weekOptions = [];
for ($offset = 0; $offset <= 16; $offset++) {
    $optionStart = $currentWeekStart->modify(($offset * 7) . ' days');
    $optionEnd = $optionStart->modify('+6 days');
    $optionValue = $optionStart->format('Y-m-d');
    $weekOptions[] = [
        'value' => $optionValue,
        'label' => $optionStart->format('M j') . ' - ' . $optionEnd->format('M j, Y'),
    ];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Clinic Hours - Staff Portal</title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,400;1,700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
            vertical-align: middle;
        }
        .mesh-bg {
            background-color: #f8fafc;
            background-image:
                radial-gradient(at 0% 0%, rgba(43, 139, 235, 0.03) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(43, 139, 235, 0.02) 0px, transparent 50%);
        }
        .elevated-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.05);
        }
        .provider-page-enter {
            animation: provider-page-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes provider-page-in {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .schedule-input {
            border: none;
            background: #f8fafc;
            border-radius: 0.9rem;
            font-size: 0.86rem;
            font-weight: 700;
            color: #0f172a;
            transition: box-shadow 0.25s ease, background-color 0.25s ease;
        }
        .schedule-input:focus {
            outline: none;
            background: #f1f5f9;
            box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.18);
        }
    </style>
</head>
<body class="bg-background text-on-background mesh-bg min-h-screen flex">
<?php include __DIR__ . '/includes/staff_portal_sidebar.php'; ?>
<main class="flex-1 flex flex-col min-w-0 ml-64 pt-[4.5rem] sm:pt-20 provider-page-enter">
    <?php include __DIR__ . '/includes/staff_top_header.inc.php'; ?>

    <div class="p-10 space-y-8">
        <section class="flex flex-col gap-4">
            <div class="text-primary font-bold text-xs uppercase flex items-center gap-4 tracking-[0.3em]">
                <span class="w-12 h-[1.5px] bg-primary"></span> CLINIC SETTINGS
            </div>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                        Clinic <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Hours</span>
                    </h1>
                    <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                        Manage clinic operating hours per day
                    </p>
                </div>
            </div>
        </section>

        <section class="elevated-card rounded-3xl p-7">
            <div class="flex flex-col gap-4 mb-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <h2 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Weekly Hours</h2>
                    <p class="text-sm font-semibold text-slate-600">
                        <?php echo htmlspecialchars($selectedWeekStart->format('F j, Y') . ' - ' . $selectedWeekEnd->format('F j, Y'), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white p-1">
                        <?php if ($isCurrentWeek): ?>
                            <button
                                type="button"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-300 cursor-not-allowed"
                                aria-label="Previous week unavailable"
                                disabled
                            >
                                <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                            </button>
                        <?php else: ?>
                            <a
                                href="?week_start=<?php echo htmlspecialchars($prevWeekStart, ENT_QUOTES, 'UTF-8'); ?>"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 hover:text-primary hover:bg-primary/10 transition-colors"
                                aria-label="Previous week"
                            >
                                <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                            </a>
                        <?php endif; ?>
                        <a
                            href="?week_start=<?php echo htmlspecialchars($nextWeekStart, ENT_QUOTES, 'UTF-8'); ?>"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-slate-600 hover:text-primary hover:bg-primary/10 transition-colors"
                            aria-label="Next week"
                        >
                            <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                        </a>
                    </div>
                    <form method="get" class="flex items-center gap-2">
                        <label for="week_start" class="text-[10px] font-black text-on-surface-variant/70 uppercase tracking-[0.2em]">Week Range</label>
                        <select id="week_start" name="week_start" class="schedule-input py-2.5 pl-3 pr-9 text-xs min-w-[190px]" onchange="this.form.submit()">
                            <?php foreach ($weekOptions as $option): ?>
                                <option value="<?php echo htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $option['value'] === $selectedWeekStart->format('Y-m-d') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="min-w-[780px] border border-slate-200 rounded-2xl overflow-hidden bg-white">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Day</th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Open Time</th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Close Time</th>
                            <th class="px-5 py-3.5 text-left text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Status</th>
                            <th class="px-5 py-3.5 text-center text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Action</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php for ($dayOffset = 0; $dayOffset < 7; $dayOffset++): ?>
                            <?php
                            $dayDate = $selectedWeekStart->modify('+' . $dayOffset . ' days');
                            $dayName = $dayDate->format('l');
                            $row = isset($rowsByDay[$dayName]) ? $rowsByDay[$dayName] : ['open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false];
                            $fullDayLabel = $dayDate->format('F j, Y') . ' (' . $dayName . ')';
                            $statusLabel = $row['is_closed'] ? 'Closed' : 'Open';
                            $statusClass = $row['is_closed']
                                ? 'border-rose-200 bg-rose-50 text-rose-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                            ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-5 py-4 text-sm font-bold text-slate-800">
                                    <?php echo htmlspecialchars($fullDayLabel, ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-5 py-4 text-sm font-semibold text-slate-700">
                                    <?php echo htmlspecialchars($row['open_time'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-5 py-4 text-sm font-semibold text-slate-700">
                                    <?php echo htmlspecialchars($row['close_time'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-black uppercase tracking-[0.12em] <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-center">
                                    <button
                                        type="button"
                                        data-open-modal="editClinicHoursModal"
                                        data-day="<?php echo htmlspecialchars($fullDayLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-open-time="<?php echo htmlspecialchars($row['open_time'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-close-time="<?php echo htmlspecialchars($row['close_time'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-is-closed="<?php echo $row['is_closed'] ? '1' : '0'; ?>"
                                        class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 text-slate-600 hover:text-primary hover:border-primary/30 transition-colors"
                                        aria-label="Edit clinic hours"
                                    >
                                        <span class="material-symbols-outlined text-[20px]">edit</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</main>

<div id="editClinicHoursModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/45">
    <div class="w-full max-w-lg bg-white rounded-3xl border border-slate-200 shadow-2xl">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-headline text-xl font-extrabold text-slate-900">Edit Clinic Hours</h3>
            <button type="button" data-close-modal="editClinicHoursModal" class="w-9 h-9 inline-flex items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:text-slate-700">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form onsubmit="event.preventDefault(); closeModal('editClinicHoursModal');">
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Day</label>
                    <div id="modalDayLabel" class="schedule-input w-full py-3 px-4 text-slate-700">Monday</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Open Time</label>
                        <select id="modalOpenTime" class="schedule-input w-full py-3 px-4">
                            <option>08:00 AM</option>
                            <option>09:00 AM</option>
                            <option>10:00 AM</option>
                            <option>11:00 AM</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Close Time</label>
                        <select id="modalCloseTime" class="schedule-input w-full py-3 px-4">
                            <option>03:00 PM</option>
                            <option>04:00 PM</option>
                            <option selected>05:00 PM</option>
                            <option>06:00 PM</option>
                        </select>
                    </div>
                </div>
                <label class="inline-flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700">
                    <input id="modalClosedCheckbox" type="checkbox" class="rounded border-slate-300 text-primary focus:ring-primary/20"/>
                    Mark as Closed
                </label>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" data-close-modal="editClinicHoursModal" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-xs uppercase tracking-wider">Cancel</button>
                <button type="submit" class="px-4 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs uppercase tracking-wider">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetModal = button.getAttribute('data-open-modal');
            if (targetModal === 'editClinicHoursModal') {
                const day = button.getAttribute('data-day') || 'Monday';
                const openTime = button.getAttribute('data-open-time') || '08:00 AM';
                const closeTime = button.getAttribute('data-close-time') || '05:00 PM';
                const isClosed = button.getAttribute('data-is-closed') === '1';

                const dayEl = document.getElementById('modalDayLabel');
                const openEl = document.getElementById('modalOpenTime');
                const closeEl = document.getElementById('modalCloseTime');
                const closedEl = document.getElementById('modalClosedCheckbox');

                if (dayEl) dayEl.textContent = day;
                if (openEl) openEl.value = openTime;
                if (closeEl) closeEl.value = closeTime;
                if (closedEl) closedEl.checked = isClosed;
            }
            openModal(targetModal);
        });
    });

    document.querySelectorAll('[data-close-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            closeModal(button.getAttribute('data-close-modal'));
        });
    });

    document.querySelectorAll('[id$="Modal"]').forEach((modal) => {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeModal('editClinicHoursModal');
        }
    });
</script>
</body>
</html>
