<?php
$staff_nav_active = 'clinic_hours';
require_once __DIR__ . '/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/tenant.php';

$currentTenantId = '';
$tRes = getClinicTenantId();
if ($tRes !== null && trim((string) $tRes) !== '') {
    $currentTenantId = trim((string) $tRes);
}

$manilaTz = new DateTimeZone('Asia/Manila');

$defaultClinicHoursRows = [
    0 => ['day' => 'Sunday', 'open_time' => '09:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    1 => ['day' => 'Monday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    2 => ['day' => 'Tuesday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    3 => ['day' => 'Wednesday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    4 => ['day' => 'Thursday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    5 => ['day' => 'Friday', 'open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false],
    6 => ['day' => 'Saturday', 'open_time' => '09:00 AM', 'close_time' => '03:00 PM', 'is_closed' => false],
];

$today = new DateTimeImmutable('today', $manilaTz);

$formatTimeForDisplay = static function ($timeValue, $fallback) {
    $raw = trim((string) $timeValue);
    if ($raw === '') {
        return $fallback;
    }
    $dt = DateTimeImmutable::createFromFormat('H:i:s', $raw);
    if (!($dt instanceof DateTimeImmutable)) {
        $dt = DateTimeImmutable::createFromFormat('H:i', $raw);
    }
    if (!($dt instanceof DateTimeImmutable)) {
        return $fallback;
    }
    return $dt->format('h:i A');
};

$parseClockTime = static function ($timeRaw) use ($manilaTz) {
    $time = trim((string) $timeRaw);
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return null;
    }
    $dt = DateTimeImmutable::createFromFormat('!H:i', $time, $manilaTz);
    if (!($dt instanceof DateTimeImmutable)) {
        return null;
    }
    return $dt;
};

$formMessage = '';
$formMessageType = 'success';
if (isset($_SESSION['clinic_hours_message']) && is_array($_SESSION['clinic_hours_message'])) {
    $flashMessage = $_SESSION['clinic_hours_message'];
    if (isset($flashMessage['text']) && is_string($flashMessage['text'])) {
        $formMessage = $flashMessage['text'];
    }
    if (isset($flashMessage['type']) && in_array($flashMessage['type'], ['success', 'error'], true)) {
        $formMessageType = $flashMessage['type'];
    }
    unset($_SESSION['clinic_hours_message']);
}

$fallbackRowsByDayIndex = $defaultClinicHoursRows;

try {
    $pdo = getDBConnection();

    if ($currentTenantId === '') {
        require_once __DIR__ . '/includes/appointment_db_tables.php';
        $resolvedTenantId = clinic_resolve_walkin_tenant_id($pdo);
        if (is_string($resolvedTenantId) && $resolvedTenantId !== '') {
            $currentTenantId = trim($resolvedTenantId);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_clinic_hours'])) {
        if ($currentTenantId === '') {
            throw new RuntimeException('You must be signed in under a clinic to update clinic hours.');
        }
        $dayOfWeek = isset($_POST['day_of_week']) ? (int) $_POST['day_of_week'] : -1;
        $openTimeRaw = isset($_POST['open_time']) ? trim((string) $_POST['open_time']) : '';
        $closeTimeRaw = isset($_POST['close_time']) ? trim((string) $_POST['close_time']) : '';
        $notesInput = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';
        $notes = $notesInput !== '' ? substr($notesInput, 0, 255) : null;
        $isClosed = isset($_POST['is_closed']) && $_POST['is_closed'] === '1';

        $openTime = null;
        $closeTime = null;
        if (!$isClosed) {
            $openTimeDt = $parseClockTime($openTimeRaw);
            $closeTimeDt = $parseClockTime($closeTimeRaw);

            if (!($openTimeDt instanceof DateTimeImmutable) || !($closeTimeDt instanceof DateTimeImmutable)) {
                throw new RuntimeException('Please select valid opening and closing times.');
            }
            $openMinutes = ((int) $openTimeDt->format('H')) * 60 + (int) $openTimeDt->format('i');
            $closeMinutes = ((int) $closeTimeDt->format('H')) * 60 + (int) $closeTimeDt->format('i');
            if ($closeMinutes <= $openMinutes) {
                throw new RuntimeException('Closing time must be later than opening time on the same day.');
            }
            $openTime = $openTimeDt->format('H:i:s');
            $closeTime = $closeTimeDt->format('H:i:s');
        }

        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new RuntimeException('Invalid day selected for clinic hours.');
        }

        $findWeeklyStmt = $pdo->prepare(
            'SELECT clinic_hours_id FROM tbl_clinic_hours WHERE tenant_id = ? AND clinic_date IS NULL AND day_of_week = ? LIMIT 1'
        );
        $findWeeklyStmt->execute([$currentTenantId, $dayOfWeek]);
        $existingWeekly = $findWeeklyStmt->fetch(PDO::FETCH_ASSOC);
        $findWeeklyStmt->closeCursor();
        $existingWeeklyId = is_array($existingWeekly) && isset($existingWeekly['clinic_hours_id'])
            ? (int) $existingWeekly['clinic_hours_id']
            : null;

        $insertOneStmt = $pdo->prepare('
            INSERT INTO tbl_clinic_hours (tenant_id, clinic_date, day_of_week, open_time, close_time, is_closed, notes)
            VALUES (?, NULL, ?, ?, ?, ?, ?)
        ');
        $updateOneStmt = $pdo->prepare('
            UPDATE tbl_clinic_hours
            SET open_time = ?,
                close_time = ?,
                is_closed = ?,
                notes = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE clinic_hours_id = ?
              AND tenant_id = ?
        ');

        if ($existingWeeklyId !== null) {
            $updateOneStmt->execute([
                $isClosed ? null : $openTime,
                $isClosed ? null : $closeTime,
                $isClosed ? 1 : 0,
                $notes,
                $existingWeeklyId,
                $currentTenantId,
            ]);
        } else {
            $insertOneStmt->execute([
                $currentTenantId,
                $dayOfWeek,
                $isClosed ? null : $openTime,
                $isClosed ? null : $closeTime,
                $isClosed ? 1 : 0,
                $notes,
            ]);
        }

        $_SESSION['clinic_hours_message'] = [
            'type' => 'success',
            'text' => 'Weekly clinic hours updated. This schedule applies to every matching weekday going forward.',
        ];
        header('Location: StaffClinicHours.php');
        exit;
    }

    if ($currentTenantId !== '') {
        $legacyStmt = $pdo->prepare('SELECT day_of_week, open_time, close_time, is_closed, notes FROM tbl_clinic_hours WHERE tenant_id = ? AND clinic_date IS NULL');
        $legacyStmt->execute([$currentTenantId]);
        $legacyRows = $legacyStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($legacyRows as $dbRow) {
            $dayIndex = isset($dbRow['day_of_week']) ? (int) $dbRow['day_of_week'] : -1;
            if (!isset($fallbackRowsByDayIndex[$dayIndex])) {
                continue;
            }

            $fallbackOpen = $defaultClinicHoursRows[$dayIndex]['open_time'];
            $fallbackClose = $defaultClinicHoursRows[$dayIndex]['close_time'];
            $isClosedFromDb = isset($dbRow['is_closed']) && (int) $dbRow['is_closed'] === 1;

            $fallbackRowsByDayIndex[$dayIndex] = [
                'day' => $defaultClinicHoursRows[$dayIndex]['day'],
                'open_time' => $isClosedFromDb ? '--' : $formatTimeForDisplay($dbRow['open_time'], $fallbackOpen),
                'close_time' => $isClosedFromDb ? '--' : $formatTimeForDisplay($dbRow['close_time'], $fallbackClose),
                'is_closed' => $isClosedFromDb,
                'open_time_raw' => $isClosedFromDb ? '' : substr((string) $dbRow['open_time'], 0, 5),
                'close_time_raw' => $isClosedFromDb ? '' : substr((string) $dbRow['close_time'], 0, 5),
                'notes' => isset($dbRow['notes']) ? trim((string) $dbRow['notes']) : '',
            ];
        }
    }
} catch (Throwable $e) {
    error_log('Staff clinic hours load/save error: ' . $e->getMessage());
    $formMessage = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'An unexpected error occurred while loading clinic hours.';
    $formMessageType = 'error';
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
        .modal-shell {
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 28px 60px -28px rgba(15, 23, 42, 0.35);
        }
        .modal-surface {
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.88) 0%, rgba(255, 255, 255, 1) 100%);
        }
        .modal-time-input {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 0.75rem !important;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #0f172a;
            min-height: 3.1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        input[type="time"].modal-time-input {
            -webkit-appearance: none;
            appearance: none;
            border-radius: 0.95rem !important;
        }
        .modal-time-input:focus {
            border-color: #2b8beb;
            background: #ffffff;
            box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.15);
            outline: none;
        }
        .modal-day-pill {
            background: linear-gradient(90deg, rgba(43, 139, 235, 0.09), rgba(43, 139, 235, 0.03));
            border: 1px solid rgba(147, 197, 253, 0.45);
        }
        .success-popup-enter {
            animation: success-popup-in 0.28s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        @keyframes success-popup-in {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
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
                        Set a recurring weekly schedule—changes apply to every matching weekday on the calendar.
                    </p>
                </div>
            </div>
        </section>

        <?php if ($currentTenantId === ''): ?>
            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-900">
                <p class="text-sm font-semibold">
                    You are not associated with a clinic in this session, so schedules here are placeholders only.
                    Staff sign-in should set tenant_id before saving clinic hours.
                </p>
            </section>
        <?php endif; ?>

        <?php if ($formMessage !== '' && $formMessageType === 'error'): ?>
            <section class="rounded-2xl border px-5 py-4 <?php echo $formMessageType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'; ?>">
                <p class="text-sm font-semibold"><?php echo htmlspecialchars($formMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            </section>
        <?php endif; ?>

        <section class="elevated-card rounded-3xl p-7">
            <div class="flex flex-col gap-4 mb-6">
                <div class="space-y-2">
                    <h2 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Weekly Hours</h2>
                    <p class="text-xs font-medium text-slate-500 leading-relaxed max-w-2xl">
                        Edit a weekday to set open and close times for <span class="font-semibold text-slate-600">every</span> future occurrence of that day (Sunday through Saturday). Days without a saved template show <span class="font-semibold text-slate-600">default hours</span> until you save.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="min-w-[640px] border border-slate-200 rounded-2xl overflow-hidden bg-white">
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
                        <?php for ($dayOfWeekIndex = 0; $dayOfWeekIndex < 7; $dayOfWeekIndex++): ?>
                            <?php
                            $row = isset($fallbackRowsByDayIndex[$dayOfWeekIndex])
                                ? $fallbackRowsByDayIndex[$dayOfWeekIndex]
                                : ['open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false, 'notes' => '', 'open_time_raw' => '', 'close_time_raw' => ''];
                            $dayNameLabel = isset($row['day']) ? (string) $row['day'] : $defaultClinicHoursRows[$dayOfWeekIndex]['day'];
                            $statusLabel = $row['is_closed'] ? 'Closed' : 'Open';
                            $statusClass = $row['is_closed']
                                ? 'border-rose-200 bg-rose-50 text-rose-700'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700';
                            ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-5 py-4 text-sm font-bold text-slate-800">
                                    <?php echo htmlspecialchars($dayNameLabel, ENT_QUOTES, 'UTF-8'); ?>
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
                                        data-day="<?php echo htmlspecialchars($dayNameLabel, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-day-index="<?php echo htmlspecialchars((string) $dayOfWeekIndex, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-open-time="<?php echo htmlspecialchars($row['open_time'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-close-time="<?php echo htmlspecialchars($row['close_time'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-open-time-raw="<?php echo htmlspecialchars(isset($row['open_time_raw']) ? (string) $row['open_time_raw'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-close-time-raw="<?php echo htmlspecialchars(isset($row['close_time_raw']) ? (string) $row['close_time_raw'] : '', ENT_QUOTES, 'UTF-8'); ?>"
                                        data-notes="<?php echo htmlspecialchars(isset($row['notes']) ? (string) $row['notes'] : '', ENT_QUOTES, 'UTF-8'); ?>"
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
    <div class="modal-shell modal-surface w-full max-w-xl overflow-hidden rounded-[1.9rem]">
        <div class="px-6 sm:px-7 py-5 border-b border-slate-200/80 flex items-start justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/25 bg-primary/10 px-3 py-1 text-[10px] font-black uppercase tracking-[0.16em] text-primary">
                    <span class="material-symbols-outlined text-[14px]">schedule</span>
                    Clinic Schedule
                </span>
                <h3 class="font-headline text-2xl font-extrabold tracking-tight text-slate-900 mt-2">Edit Clinic Hours</h3>
                <p class="text-xs font-semibold text-slate-500 mt-1">Applies to every week—this weekday on all future dates.</p>
            </div>
            <button type="button" data-close-modal="editClinicHoursModal" class="w-10 h-10 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form method="post" id="editClinicHoursForm">
            <input type="hidden" name="save_clinic_hours" value="1"/>
            <input type="hidden" id="modalDayOfWeekInput" name="day_of_week" value="1"/>
            <div class="p-6 sm:p-7 space-y-5">
                <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3.5">
                    <label class="block text-[10px] font-black text-on-surface-variant/65 uppercase tracking-[0.2em] mb-2">Day</label>
                    <div id="modalDayLabel" class="modal-day-pill w-full rounded-xl px-4 py-3.5 text-[15px] font-extrabold tracking-tight text-slate-700">Monday</div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="modalOpenTime" class="block text-[10px] font-black text-on-surface-variant/65 uppercase tracking-[0.2em] mb-2">Open Time</label>
                        <input id="modalOpenTime" name="open_time" type="time" step="60" class="modal-time-input w-full px-4" value="09:00"/>
                        <p class="mt-1.5 text-[11px] font-semibold text-slate-400">Choose any minute (e.g., 04:47).</p>
                    </div>
                    <div>
                        <label for="modalCloseTime" class="block text-[10px] font-black text-on-surface-variant/65 uppercase tracking-[0.2em] mb-2">Close Time</label>
                        <input id="modalCloseTime" name="close_time" type="time" step="60" class="modal-time-input w-full px-4" value="17:00"/>
                        <p class="mt-1.5 text-[11px] font-semibold text-slate-400">Must be after open time the same day.</p>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3.5">
                    <label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700 cursor-pointer">
                        <input id="modalClosedCheckbox" name="is_closed" type="checkbox" value="1" class="rounded-md border-slate-300 text-primary focus:ring-primary/20"/>
                        Mark as Closed
                    </label>
                </div>
                <div class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3.5">
                    <label for="modalNotes" class="block text-[10px] font-black text-on-surface-variant/65 uppercase tracking-[0.2em] mb-2">Notes (Optional)</label>
                    <textarea id="modalNotes" name="notes" rows="3" maxlength="255" class="modal-time-input w-full px-4 py-3 resize-none" placeholder="Optional notes for this weekday template..."></textarea>
                </div>
            </div>
            <div class="px-6 sm:px-7 py-4 border-t border-slate-200/80 bg-slate-50/70 flex justify-end gap-2">
                <button type="button" data-close-modal="editClinicHoursModal" class="px-5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-600 font-black text-xs uppercase tracking-[0.16em] hover:border-slate-400">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-black text-xs uppercase tracking-[0.16em] shadow-sm shadow-primary/30">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="clinicHoursValidationModal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/45 p-4">
    <div class="success-popup-enter w-full max-w-md rounded-3xl border border-red-200 bg-white shadow-2xl overflow-hidden">
        <div class="px-6 py-5 flex items-start gap-4">
            <span class="inline-flex w-11 h-11 shrink-0 items-center justify-center rounded-2xl bg-red-100 text-red-700">
                <span class="material-symbols-outlined text-[22px]">error</span>
            </span>
            <div class="min-w-0">
                <h3 class="font-headline text-xl font-extrabold text-slate-900">Invalid times</h3>
                <p id="clinicHoursValidationMessage" class="mt-1 text-sm font-semibold text-slate-600">Closing time must be later than opening time on the same day.</p>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50/70 flex justify-end">
            <button type="button" id="closeClinicHoursValidationModal" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-black text-xs uppercase tracking-[0.16em] shadow-sm shadow-primary/30">OK</button>
        </div>
    </div>
</div>

<?php if ($formMessage !== '' && $formMessageType === 'success'): ?>
<div id="clinicHoursSuccessModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/40 p-4">
    <div class="success-popup-enter w-full max-w-md rounded-3xl border border-emerald-200 bg-white shadow-2xl overflow-hidden">
        <div class="px-6 py-5 flex items-start gap-4">
            <span class="inline-flex w-11 h-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700">
                <span class="material-symbols-outlined text-[22px]">check_circle</span>
            </span>
            <div class="min-w-0">
                <h3 class="font-headline text-xl font-extrabold text-slate-900">Success</h3>
                <p class="mt-1 text-sm font-semibold text-slate-600"><?php echo htmlspecialchars($formMessage, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50/70 flex justify-end">
            <button type="button" id="closeClinicHoursSuccessModal" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-black text-xs uppercase tracking-[0.16em] shadow-sm shadow-primary/30">OK</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    function twelveHourToTwentyFour(timeText) {
        const fallback = '09:00';
        if (!timeText || typeof timeText !== 'string') return fallback;
        const trimmed = timeText.trim();
        if (/^\d{2}:\d{2}$/.test(trimmed)) return trimmed;
        const match = trimmed.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
        if (!match) return fallback;

        let hour = parseInt(match[1], 10);
        const minute = match[2];
        const period = match[3].toUpperCase();

        if (period === 'AM' && hour === 12) hour = 0;
        if (period === 'PM' && hour !== 12) hour += 12;

        return String(hour).padStart(2, '0') + ':' + minute;
    }

    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        syncBodyScrollLock();
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        syncBodyScrollLock();
    }

    let modalScrollLockY = 0;
    let isModalScrollLocked = false;

    function syncBodyScrollLock() {
        const hasVisibleModal = Array.from(document.querySelectorAll('[id$="Modal"]')).some((modal) => {
            return !modal.classList.contains('hidden');
        });
        if (hasVisibleModal && !isModalScrollLocked) {
            modalScrollLockY = window.scrollY || window.pageYOffset || 0;
            document.documentElement.style.overflow = 'hidden';
            document.body.style.overflow = 'hidden';
            document.body.style.position = 'fixed';
            document.body.style.top = `-${modalScrollLockY}px`;
            document.body.style.left = '0';
            document.body.style.right = '0';
            document.body.style.width = '100%';
            isModalScrollLocked = true;
            return;
        }

        if (!hasVisibleModal && isModalScrollLocked) {
            document.documentElement.style.overflow = '';
            document.body.style.overflow = '';
            document.body.style.position = '';
            document.body.style.top = '';
            document.body.style.left = '';
            document.body.style.right = '';
            document.body.style.width = '';
            window.scrollTo(0, modalScrollLockY);
            modalScrollLockY = 0;
            isModalScrollLocked = false;
        }
    }

    function setClosedState(isClosed) {
        const openEl = document.getElementById('modalOpenTime');
        const closeEl = document.getElementById('modalCloseTime');
        if (openEl) openEl.disabled = !!isClosed;
        if (closeEl) closeEl.disabled = !!isClosed;
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetModal = button.getAttribute('data-open-modal');
            if (targetModal === 'editClinicHoursModal') {
                clearModalTimeRangeError();
                const day = button.getAttribute('data-day') || 'Monday';
                const openTime = button.getAttribute('data-open-time') || '08:00 AM';
                const closeTime = button.getAttribute('data-close-time') || '05:00 PM';
                const openTimeRaw = button.getAttribute('data-open-time-raw') || '';
                const closeTimeRaw = button.getAttribute('data-close-time-raw') || '';
                const notes = button.getAttribute('data-notes') || '';
                const isClosed = button.getAttribute('data-is-closed') === '1';
                const dayIndex = button.getAttribute('data-day-index') || '1';

                const dayEl = document.getElementById('modalDayLabel');
                const openEl = document.getElementById('modalOpenTime');
                const closeEl = document.getElementById('modalCloseTime');
                const closedEl = document.getElementById('modalClosedCheckbox');
                const dayOfWeekEl = document.getElementById('modalDayOfWeekInput');
                const notesEl = document.getElementById('modalNotes');

                if (dayEl) dayEl.textContent = day;
                if (openEl) openEl.value = openTimeRaw || twelveHourToTwentyFour(openTime);
                if (closeEl) closeEl.value = closeTimeRaw || twelveHourToTwentyFour(closeTime);
                if (closedEl) closedEl.checked = isClosed;
                if (dayOfWeekEl) dayOfWeekEl.value = dayIndex;
                if (notesEl) notesEl.value = notes;
                setClosedState(isClosed);
            }
            openModal(targetModal);
        });
    });

    const modalClosedCheckbox = document.getElementById('modalClosedCheckbox');
    if (modalClosedCheckbox) {
        modalClosedCheckbox.addEventListener('change', () => {
            setClosedState(modalClosedCheckbox.checked);
            clearModalTimeRangeError();
        });
    }

    function modalTimeToMinutes(value) {
        if (!value || typeof value !== 'string' || !/^\d{2}:\d{2}$/.test(value.trim())) {
            return null;
        }
        const parts = value.trim().split(':');
        const h = parseInt(parts[0], 10);
        const m = parseInt(parts[1], 10);
        if (!Number.isFinite(h) || !Number.isFinite(m)) {
            return null;
        }
        return (h * 60) + m;
    }

    function closeValidationAlert() {
        closeModal('clinicHoursValidationModal');
    }

    function clearModalTimeRangeError() {
        closeValidationAlert();
    }

    function showModalTimeRangeError(message) {
        const msgEl = document.getElementById('clinicHoursValidationMessage');
        if (msgEl) {
            msgEl.textContent = message || 'Closing time must be later than opening time on the same day.';
        }
        openModal('clinicHoursValidationModal');
    }

    const editClinicHoursForm = document.getElementById('editClinicHoursForm');
    const modalOpenTimeInput = document.getElementById('modalOpenTime');
    const modalCloseTimeInput = document.getElementById('modalCloseTime');
    if (editClinicHoursForm) {
        editClinicHoursForm.addEventListener('submit', function (event) {
            clearModalTimeRangeError();
            const closed = modalClosedCheckbox && modalClosedCheckbox.checked;
            if (closed) {
                return;
            }
            const openM = modalTimeToMinutes((modalOpenTimeInput && modalOpenTimeInput.value) ? modalOpenTimeInput.value : '');
            const closeM = modalTimeToMinutes((modalCloseTimeInput && modalCloseTimeInput.value) ? modalCloseTimeInput.value : '');
            if (openM === null || closeM === null) {
                return;
            }
            if (closeM <= openM) {
                event.preventDefault();
                showModalTimeRangeError('Closing time must be later than opening time on the same day.');
            }
        });
    }
    if (modalOpenTimeInput) {
        modalOpenTimeInput.addEventListener('input', clearModalTimeRangeError);
    }
    if (modalCloseTimeInput) {
        modalCloseTimeInput.addEventListener('input', clearModalTimeRangeError);
    }

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
            const validationModal = document.getElementById('clinicHoursValidationModal');
            if (validationModal && !validationModal.classList.contains('hidden')) {
                closeValidationAlert();
                return;
            }
            closeModal('editClinicHoursModal');
        }
    });

    const clinicHoursValidationModal = document.getElementById('clinicHoursValidationModal');
    const closeClinicHoursValidationModal = document.getElementById('closeClinicHoursValidationModal');
    if (clinicHoursValidationModal && closeClinicHoursValidationModal) {
        closeClinicHoursValidationModal.addEventListener('click', closeValidationAlert);
        clinicHoursValidationModal.addEventListener('click', (event) => {
            if (event.target === clinicHoursValidationModal) {
                closeValidationAlert();
            }
        });
    }

    const clinicHoursSuccessModal = document.getElementById('clinicHoursSuccessModal');
    const closeClinicHoursSuccessModal = document.getElementById('closeClinicHoursSuccessModal');
    if (clinicHoursSuccessModal && closeClinicHoursSuccessModal) {
        const dismissSuccessModal = () => {
            clinicHoursSuccessModal.classList.add('hidden');
            syncBodyScrollLock();
        };
        closeClinicHoursSuccessModal.addEventListener('click', dismissSuccessModal);
        clinicHoursSuccessModal.addEventListener('click', (event) => {
            if (event.target === clinicHoursSuccessModal) {
                dismissSuccessModal();
            }
        });
    }
    syncBodyScrollLock();
</script>
</body>
</html>
