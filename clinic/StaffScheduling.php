<?php
$staff_nav_active = 'my_schedule';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$weekLabel = 'Apr 1-7, 2026';
$monthLabel = 'April 2026';
$weekDays = [
    ['name' => 'Sun', 'date' => 1],
    ['name' => 'Mon', 'date' => 2],
    ['name' => 'Tue', 'date' => 3],
    ['name' => 'Wed', 'date' => 4],
    ['name' => 'Thu', 'date' => 5],
    ['name' => 'Fri', 'date' => 6],
    ['name' => 'Sat', 'date' => 7],
];
$timeSlots = [];
for ($hour = 8; $hour <= 20; $hour++) {
    $timeSlots[] = sprintf('%02d:00', $hour);
}

$miniCalendar = [
    [30, 31, 1, 2, 3, 4, 5],
    [6, 7, 8, 9, 10, 11, 12],
    [13, 14, 15, 16, 17, 18, 19],
    [20, 21, 22, 23, 24, 25, 26],
    [27, 28, 29, 30, 1, 2, 3],
];

$scheduleBlocks = [
    ['day' => 1, 'start' => '09:00', 'end' => '11:00', 'label' => 'Treatment', 'class' => 'bg-violet-500 border-violet-600', 'icon' => 'event_available'],
    ['day' => 1, 'start' => '14:00', 'end' => '15:00', 'label' => 'Blocked', 'class' => 'bg-slate-500 border-slate-600', 'icon' => 'block'],
    ['day' => 2, 'start' => '10:00', 'end' => '12:00', 'label' => 'Cleaning', 'class' => 'bg-teal-500 border-teal-600', 'icon' => 'event_note'],
    ['day' => 3, 'start' => '13:00', 'end' => '15:00', 'label' => 'Treatment', 'class' => 'bg-violet-500 border-violet-600', 'icon' => 'event_available'],
    ['day' => 4, 'start' => '11:00', 'end' => '12:00', 'label' => 'Blocked', 'class' => 'bg-slate-500 border-slate-600', 'icon' => 'block'],
    ['day' => 5, 'start' => '16:00', 'end' => '18:00', 'label' => 'Cleaning', 'class' => 'bg-teal-500 border-teal-600', 'icon' => 'event_note'],
    ['day' => 6, 'start' => '08:00', 'end' => '10:00', 'label' => 'Treatment', 'class' => 'bg-violet-500 border-violet-600', 'icon' => 'event_available'],
    ['day' => 7, 'start' => '15:00', 'end' => '17:00', 'label' => 'Blocked', 'class' => 'bg-slate-500 border-slate-600', 'icon' => 'block'],
];

function toMinutes($timeValue)
{
    [$hourPart, $minutePart] = array_map('intval', explode(':', $timeValue));
    return ($hourPart * 60) + $minutePart;
}

function renderSlotBlock($day, $slotStart, $slotEnd, $blocks)
{
    foreach ($blocks as $block) {
        if ((int) $block['day'] !== (int) $day) {
            continue;
        }
        $blockStart = toMinutes((string) $block['start']);
        $blockEnd = toMinutes((string) $block['end']);
        if ($blockStart < $slotEnd && $blockEnd > $slotStart) {
            return $block;
        }
    }
    return null;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>My Schedule - Staff Portal</title>
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
        .schedule-block {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .schedule-block:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px -10px rgba(15, 23, 42, 0.6);
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
                <span class="w-12 h-[1.5px] bg-primary"></span> DENTIST SCHEDULING
            </div>
            <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                <div>
                    <h1 class="font-headline text-5xl font-extrabold tracking-tighter leading-tight text-on-background">
                        My <span class="font-editorial italic font-normal text-primary transform -skew-x-6 inline-block">Schedule</span>
                    </h1>
                    <p class="font-body text-lg font-medium text-on-surface-variant max-w-3xl leading-relaxed mt-3">
                        Manage your weekly schedule, appointments, and blocked time
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5 xl:justify-end">
                    <button type="button" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 font-bold text-xs uppercase tracking-widest hover:border-primary/30 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-base">filter_list</span>
                        Filter
                    </button>
                    <div class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 font-bold text-xs uppercase tracking-widest">
                        <span class="material-symbols-outlined text-base text-primary">date_range</span>
                        <?php echo htmlspecialchars($weekLabel, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1">
                        <button type="button" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500 hover:text-primary transition-colors">Day</button>
                        <button type="button" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-[0.2em] bg-primary text-white">Week</button>
                        <button type="button" class="px-3 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500 hover:text-primary transition-colors">Month</button>
                    </div>
                    <button type="button" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-base">add</span>
                        Block Time
                    </button>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 2xl:grid-cols-12 gap-6 items-start">
            <aside class="2xl:col-span-3 space-y-6">
                <div class="elevated-card rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Calendar</h2>
                        <span class="text-xs font-bold text-slate-500"><?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-2">
                        <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                    </div>
                    <div class="space-y-1.5">
                        <?php foreach ($miniCalendar as $weekIndex => $weekRow): ?>
                            <div class="grid grid-cols-7 gap-1.5">
                                <?php foreach ($weekRow as $dateNumber): ?>
                                    <?php
                                    $isCurrentDate = ($dateNumber === 3 && $weekIndex === 0);
                                    $isActiveWeek = ($weekIndex === 0);
                                    $dateClasses = 'h-8 rounded-lg text-xs font-bold flex items-center justify-center border';
                                    if ($isCurrentDate) {
                                        $dateClasses .= ' bg-primary text-white border-primary';
                                    } elseif ($isActiveWeek) {
                                        $dateClasses .= ' bg-primary/10 text-primary border-primary/20';
                                    } else {
                                        $dateClasses .= ' bg-white text-slate-500 border-slate-100';
                                    }
                                    ?>
                                    <div class="<?php echo $dateClasses; ?>">
                                        <?php echo htmlspecialchars((string) $dateNumber, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="elevated-card rounded-3xl p-6">
                    <h3 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Legend</h3>
                    <div class="space-y-2.5">
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                            <span class="w-3 h-3 rounded-full bg-violet-500"></span><span class="text-sm font-semibold text-slate-700">Treatment</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                            <span class="w-3 h-3 rounded-full bg-teal-500"></span><span class="text-sm font-semibold text-slate-700">Hygiene</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                            <span class="w-3 h-3 rounded-full bg-orange-500"></span><span class="text-sm font-semibold text-slate-700">Consultation</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                            <span class="w-3 h-3 rounded-full bg-slate-500"></span><span class="text-sm font-semibold text-slate-700">Blocked / Personal</span>
                        </div>
                    </div>
                    <div class="mt-5 pt-5 border-t border-slate-100 space-y-2">
                        <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <span class="material-symbols-outlined text-base text-primary">event_available</span> Appointment
                        </div>
                        <div class="flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <span class="material-symbols-outlined text-base text-amber-500">directions_walk</span> Walk-in
                        </div>
                    </div>
                </div>
            </aside>

            <div class="2xl:col-span-9 elevated-card rounded-3xl p-5 lg:p-6 overflow-hidden">
                <div class="overflow-x-auto">
                    <div class="min-w-[900px] border border-slate-200 rounded-2xl overflow-hidden bg-white">
                        <div class="grid grid-cols-[84px_repeat(7,minmax(0,1fr))] bg-slate-50 border-b border-slate-200">
                            <div class="px-3 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Time</div>
                            <?php foreach ($weekDays as $weekDay): ?>
                                <div class="px-3 py-3 border-l border-slate-200">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"><?php echo htmlspecialchars($weekDay['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-sm font-bold text-slate-700 mt-1"><?php echo htmlspecialchars((string) $weekDay['date'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($timeSlots as $slotTime): ?>
                            <?php
                            $slotStartMin = toMinutes($slotTime);
                            $slotEndMin = $slotStartMin + 60;
                            ?>
                            <div class="grid grid-cols-[84px_repeat(7,minmax(0,1fr))] border-b border-slate-100 last:border-b-0">
                                <div class="px-3 py-4 text-xs font-bold text-slate-500 bg-slate-50/70 border-r border-slate-100">
                                    <?php echo htmlspecialchars($slotTime, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php foreach ($weekDays as $weekDay): ?>
                                    <?php $block = renderSlotBlock((int) $weekDay['date'], $slotStartMin, $slotEndMin, $scheduleBlocks); ?>
                                    <div class="h-[72px] p-2 border-l border-slate-100">
                                        <?php if ($block !== null): ?>
                                            <div class="schedule-block h-full rounded-xl border px-2.5 py-2 text-white <?php echo htmlspecialchars((string) $block['class'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <p class="text-[11px] font-black uppercase tracking-[0.12em]"><?php echo htmlspecialchars((string) $block['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="text-[10px] font-semibold mt-1 opacity-95">
                                                    <?php echo htmlspecialchars((string) $block['start'] . ' - ' . (string) $block['end'], ENT_QUOTES, 'UTF-8'); ?>
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
</body>
</html>
