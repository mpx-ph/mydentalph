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
$weekStartInput = isset($_GET['week_start']) ? trim((string) $_GET['week_start']) : '';
$selectedDate = null;
if ($weekStartInput !== '') {
    $selectedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $weekStartInput, $manilaTz);
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
$clinicHoursRowsByDate = [];

try {
    $pdo = getDBConnection();

    if ($currentTenantId === '') {
        require_once __DIR__ . '/includes/appointment_db_tables.php';
        $resolvedTenantId = clinic_resolve_walkin_tenant_id($pdo);
        if (is_string($resolvedTenantId) && $resolvedTenantId !== '') {
            $currentTenantId = trim($resolvedTenantId);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_apply_clinic_hours'])) {
        if ($currentTenantId === '') {
            throw new RuntimeException('You must be signed in under a clinic to update clinic hours.');
        }
        $weekStartForRedirect = isset($_POST['week_start']) ? trim((string) $_POST['week_start']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartForRedirect)) {
            $weekStartForRedirect = '';
        }

        $dateFromRaw = isset($_POST['bulk_date_from']) ? trim((string) $_POST['bulk_date_from']) : '';
        $dateToRaw = isset($_POST['bulk_date_to']) ? trim((string) $_POST['bulk_date_to']) : '';
        $openTimeRaw = isset($_POST['bulk_open_time']) ? trim((string) $_POST['bulk_open_time']) : '';
        $closeTimeRaw = isset($_POST['bulk_close_time']) ? trim((string) $_POST['bulk_close_time']) : '';
        $isClosed = isset($_POST['bulk_is_closed']) && $_POST['bulk_is_closed'] === '1';
        $overwrite = isset($_POST['bulk_overwrite']) && $_POST['bulk_overwrite'] === '1';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFromRaw) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateToRaw)) {
            throw new RuntimeException('Please select a valid date range.');
        }
        $dateFrom = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFromRaw, $manilaTz);
        $dateTo = DateTimeImmutable::createFromFormat('!Y-m-d', $dateToRaw, $manilaTz);
        if (!($dateFrom instanceof DateTimeImmutable) || !($dateTo instanceof DateTimeImmutable)) {
            throw new RuntimeException('Please select a valid date range.');
        }
        if ($dateFrom > $dateTo) {
            throw new RuntimeException('The start date must be on or before the end date.');
        }
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
            if ($openMinutes === $closeMinutes) {
                throw new RuntimeException('Opening and closing times cannot be the same.');
            }
            $openTime = $openTimeDt->format('H:i:s');
            $closeTime = $closeTimeDt->format('H:i:s');
        }

        // Existence must match UNIQUE unique_day_date (day_of_week, clinic_date) on many hosts—not only (tenant_id, clinic_date).
        $findDayDateStmt = $pdo->prepare(
            'SELECT clinic_hours_id, tenant_id FROM tbl_clinic_hours WHERE day_of_week = ? AND clinic_date = ? LIMIT 1'
        );
        $insertStmt = $pdo->prepare('
            INSERT INTO tbl_clinic_hours (tenant_id, clinic_date, day_of_week, open_time, close_time, is_closed, notes)
            VALUES (?, ?, ?, ?, ?, ?, NULL)
        ');
        $updateStmt = $pdo->prepare('
            UPDATE tbl_clinic_hours
            SET tenant_id = ?,
                clinic_date = ?,
                day_of_week = ?,
                open_time = ?,
                close_time = ?,
                is_closed = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE clinic_hours_id = ?
              AND tenant_id = ?
        ');

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        $pdo->beginTransaction();
        try {
            for ($d = $dateFrom; $d <= $dateTo; $d = $d->modify('+1 day')) {
                $dow = (int) $d->format('w');
                $clinicDateStr = $d->format('Y-m-d');
                $findDayDateStmt->execute([$dow, $clinicDateStr]);
                $existing = $findDayDateStmt->fetch(PDO::FETCH_ASSOC);
                $findDayDateStmt->closeCursor();
                $existingId = null;
                if (is_array($existing) && isset($existing['clinic_hours_id'])) {
                    $existingTid = isset($existing['tenant_id']) ? trim((string) $existing['tenant_id']) : '';
                    if ($existingTid !== '' && strcasecmp($existingTid, $currentTenantId) !== 0) {
                        throw new RuntimeException(
                            'This date already has clinic hours tied to another tenant ('
                            . substr($existingTid, 0, 48) . '). Migrate unique_day_date to include tenant_id, or delete the conflicting row in tbl_clinic_hours.'
                        );
                    }
                    $existingId = (int) $existing['clinic_hours_id'];
                }

                if ($existingId !== null) {
                    if ($overwrite) {
                        $updateStmt->execute([
                            $currentTenantId,
                            $clinicDateStr,
                            $dow,
                            $isClosed ? null : $openTime,
                            $isClosed ? null : $closeTime,
                            $isClosed ? 1 : 0,
                            $existingId,
                            $currentTenantId,
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $insertStmt->execute([
                        $currentTenantId,
                        $clinicDateStr,
                        $dow,
                        $isClosed ? null : $openTime,
                        $isClosed ? null : $closeTime,
                        $isClosed ? 1 : 0,
                    ]);
                    $inserted++;
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $msgParts = [];
        if ($inserted > 0) {
            $msgParts[] = $inserted . ' new date' . ($inserted === 1 ? '' : 's');
        }
        if ($updated > 0) {
            $msgParts[] = $updated . ' updated';
        }
        if ($skipped > 0) {
            $msgParts[] = $skipped . ' skipped (existing hours, overwrite off)';
        }
        if ($inserted === 0 && $updated === 0 && $skipped === 0) {
            $_SESSION['clinic_hours_message'] = [
                'type' => 'success',
                'text' => 'No dates matched your range and day selection. Nothing was changed.',
            ];
        } else {
            $summary = $msgParts !== [] ? implode('; ', $msgParts) . '.' : 'Done.';
            $_SESSION['clinic_hours_message'] = [
                'type' => 'success',
                'text' => 'Bulk apply complete. ' . $summary,
            ];
        }

        $redirectUrl = 'StaffClinicHours.php';
        if ($weekStartForRedirect !== '') {
            $redirectUrl .= '?week_start=' . rawurlencode($weekStartForRedirect);
        }
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_clinic_hours'])) {
        if ($currentTenantId === '') {
            throw new RuntimeException('You must be signed in under a clinic to update clinic hours.');
        }
        $weekStartForRedirect = isset($_POST['week_start']) ? trim((string) $_POST['week_start']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartForRedirect)) {
            $weekStartForRedirect = '';
        }

        $dayOfWeek = isset($_POST['day_of_week']) ? (int) $_POST['day_of_week'] : -1;
        $clinicDate = isset($_POST['clinic_date']) ? trim((string) $_POST['clinic_date']) : '';
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
            if ($openMinutes === $closeMinutes) {
                throw new RuntimeException('Opening and closing times cannot be the same.');
            }
            $openTime = $openTimeDt->format('H:i:s');
            $closeTime = $closeTimeDt->format('H:i:s');
        }

        if ($dayOfWeek < 0 || $dayOfWeek > 6) {
            throw new RuntimeException('Invalid day selected for clinic hours.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $clinicDate)) {
            throw new RuntimeException('Invalid clinic date selected for clinic hours.');
        }

        $findOneDayDateStmt = $pdo->prepare(
            'SELECT clinic_hours_id, tenant_id FROM tbl_clinic_hours WHERE day_of_week = ? AND clinic_date = ? LIMIT 1'
        );
        $findOneDayDateStmt->execute([$dayOfWeek, $clinicDate]);
        $existingOne = $findOneDayDateStmt->fetch(PDO::FETCH_ASSOC);
        $findOneDayDateStmt->closeCursor();
        $existingOneId = null;
        if (is_array($existingOne) && isset($existingOne['clinic_hours_id'])) {
            $existingOneTid = isset($existingOne['tenant_id']) ? trim((string) $existingOne['tenant_id']) : '';
            if ($existingOneTid !== '' && strcasecmp($existingOneTid, $currentTenantId) !== 0) {
                throw new RuntimeException(
                    'This date already has clinic hours tied to another tenant. Migrate unique_day_date to include tenant_id, or remove the conflicting row.'
                );
            }
            $existingOneId = (int) $existingOne['clinic_hours_id'];
        }

        $insertOneStmt = $pdo->prepare('
            INSERT INTO tbl_clinic_hours (tenant_id, clinic_date, day_of_week, open_time, close_time, is_closed, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $updateOneStmt = $pdo->prepare('
            UPDATE tbl_clinic_hours
            SET tenant_id = ?,
                clinic_date = ?,
                day_of_week = ?,
                open_time = ?,
                close_time = ?,
                is_closed = ?,
                notes = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE clinic_hours_id = ?
              AND tenant_id = ?
        ');

        if ($existingOneId !== null) {
            $updateOneStmt->execute([
                $currentTenantId,
                $clinicDate,
                $dayOfWeek,
                $isClosed ? null : $openTime,
                $isClosed ? null : $closeTime,
                $isClosed ? 1 : 0,
                $notes,
                $existingOneId,
                $currentTenantId,
            ]);
        } else {
            $insertOneStmt->execute([
                $currentTenantId,
                $clinicDate,
                $dayOfWeek,
                $isClosed ? null : $openTime,
                $isClosed ? null : $closeTime,
                $isClosed ? 1 : 0,
                $notes,
            ]);
        }

        $_SESSION['clinic_hours_message'] = [
            'type' => 'success',
            'text' => 'Clinic hours updated successfully.',
        ];
        $redirectUrl = 'StaffClinicHours.php';
        if ($weekStartForRedirect !== '') {
            $redirectUrl .= '?week_start=' . rawurlencode($weekStartForRedirect);
        }
        header('Location: ' . $redirectUrl);
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

        $weekHoursStmt = $pdo->prepare("
            SELECT clinic_date, day_of_week, open_time, close_time, is_closed, notes
            FROM tbl_clinic_hours
            WHERE tenant_id = ?
              AND clinic_date IS NOT NULL
              AND clinic_date BETWEEN ? AND ?
        ");
        $weekHoursStmt->execute([
            $currentTenantId,
            $selectedWeekStart->format('Y-m-d'),
            $selectedWeekEnd->format('Y-m-d'),
        ]);
        $weekRows = $weekHoursStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($weekRows as $weekRow) {
            $dateKey = isset($weekRow['clinic_date']) ? trim((string) $weekRow['clinic_date']) : '';
            if ($dateKey === '') {
                continue;
            }
            $dayIndex = isset($weekRow['day_of_week']) ? (int) $weekRow['day_of_week'] : -1;
            $dayFallback = isset($fallbackRowsByDayIndex[$dayIndex]) ? $fallbackRowsByDayIndex[$dayIndex] : ['open_time' => '08:00 AM', 'close_time' => '05:00 PM'];
            $isClosedFromDb = isset($weekRow['is_closed']) && (int) $weekRow['is_closed'] === 1;

            $clinicHoursRowsByDate[$dateKey] = [
                'open_time' => $isClosedFromDb ? '--' : $formatTimeForDisplay($weekRow['open_time'], $dayFallback['open_time']),
                'close_time' => $isClosedFromDb ? '--' : $formatTimeForDisplay($weekRow['close_time'], $dayFallback['close_time']),
                'is_closed' => $isClosedFromDb,
                'open_time_raw' => $isClosedFromDb ? '' : substr((string) $weekRow['open_time'], 0, 5),
                'close_time_raw' => $isClosedFromDb ? '' : substr((string) $weekRow['close_time'], 0, 5),
                'notes' => isset($weekRow['notes']) ? trim((string) $weekRow['notes']) : '',
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
        .bulk-calendar-day {
            position: relative;
            border: 1px solid transparent;
            border-radius: 0.9rem;
            min-height: 2.6rem;
            font-size: 0.92rem;
            font-weight: 700;
            color: #1e293b;
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
        }
        .bulk-calendar-day:hover {
            border-color: rgba(99, 102, 241, 0.28);
            background: rgba(99, 102, 241, 0.08);
        }
        .bulk-calendar-day.is-muted {
            color: #94a3b8;
        }
        .bulk-calendar-day.is-in-range {
            background: rgba(79, 70, 229, 0.12);
            border-radius: 0;
        }
        .bulk-calendar-day.is-range-start,
        .bulk-calendar-day.is-range-end {
            background: #4f46e5;
            color: #ffffff;
            border-color: #4f46e5;
            z-index: 2;
        }
        .bulk-calendar-day.is-range-start {
            border-top-left-radius: 9999px;
            border-bottom-left-radius: 9999px;
        }
        .bulk-calendar-day.is-range-end {
            border-top-right-radius: 9999px;
            border-bottom-right-radius: 9999px;
        }
        .bulk-calendar-day.is-range-single {
            border-radius: 9999px;
        }
        .bulk-date-input {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            border-radius: 0.75rem;
            min-height: 3.1rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: #0f172a;
            width: 100%;
            padding: 0 2.75rem 0 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            appearance: none;
            -webkit-appearance: none;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
        }
        .bulk-date-input:hover {
            border-color: #e2e8f0;
            background: #ffffff;
        }
        .bulk-date-input:focus {
            outline: none;
            border-color: #2b8beb;
            box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.15);
        }
        .bulk-date-input.is-active {
            border-color: #2b8beb;
            background: #ffffff;
            box-shadow: 0 0 0 2px rgba(43, 139, 235, 0.15);
        }
        .bulk-date-input::-webkit-calendar-picker-indicator {
            opacity: 0;
            cursor: pointer;
        }
        .bulk-date-icon {
            position: absolute;
            right: 0.95rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }
        .bulk-calendar-day.is-disabled {
            color: #94a3b8;
            background: #f8fafc;
            border-color: transparent;
            cursor: not-allowed;
            opacity: 0.65;
        }
        .bulk-calendar-day.is-disabled:hover {
            background: #f8fafc;
            border-color: transparent;
        }
        .success-popup-enter {
            animation: success-popup-in 0.28s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .clinic-hours-popup-enter {
            animation: clinic-hours-popup-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            transform-origin: center;
        }
        @keyframes success-popup-in {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes clinic-hours-popup-in {
            from { opacity: 0; transform: translateY(14px) scale(0.97); }
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
                        Manage clinic operating hours per day
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
            <div class="flex flex-col gap-4 mb-6 lg:flex-row lg:items-center lg:justify-between">
                <div class="space-y-2">
                    <h2 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Weekly Hours</h2>
                    <p class="text-sm font-semibold text-slate-600">
                        <?php echo htmlspecialchars($selectedWeekStart->format('F j, Y') . ' - ' . $selectedWeekEnd->format('F j, Y'), ENT_QUOTES, 'UTF-8'); ?>
                    </p>
                    <p class="text-xs font-medium text-slate-500 leading-relaxed max-w-xl">
                        Times with no row in the database yet show <span class="font-semibold text-slate-600">default hours</span> for today and past days (this week), and “<span class="font-semibold text-slate-600">-</span>” for future dates. Use edit or bulk apply to save a date into the database.
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
                    <button
                        type="button"
                        data-open-modal="applyClinicHoursModal"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-white font-black text-xs uppercase tracking-[0.16em] shadow-sm shadow-primary/30 hover:bg-primary/90 transition-colors w-full sm:w-auto"
                    >
                        <span class="material-symbols-outlined text-[18px]">event_repeat</span>
                        Apply Clinic Hours
                    </button>
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
                            $dateKey = $dayDate->format('Y-m-d');
                            $dayOfWeekIndex = (int) $dayDate->format('w');
                            $dayName = $dayDate->format('l');
                            $hasSavedClinicHours = isset($clinicHoursRowsByDate[$dateKey]);
                            $isFutureDateWithoutSchedule = !$hasSavedClinicHours && $dateKey > $today->format('Y-m-d');
                            if ($hasSavedClinicHours) {
                                $row = $clinicHoursRowsByDate[$dateKey];
                            } elseif ($isFutureDateWithoutSchedule) {
                                $row = [
                                    'open_time' => '-',
                                    'close_time' => '-',
                                    'is_closed' => false,
                                    'open_time_raw' => '',
                                    'close_time_raw' => '',
                                    'notes' => '',
                                ];
                            } else {
                                $row = isset($fallbackRowsByDayIndex[$dayOfWeekIndex])
                                    ? $fallbackRowsByDayIndex[$dayOfWeekIndex]
                                    : ['open_time' => '08:00 AM', 'close_time' => '05:00 PM', 'is_closed' => false, 'notes' => ''];
                            }
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
                                        data-day-index="<?php echo htmlspecialchars((string) $dayOfWeekIndex, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-date="<?php echo htmlspecialchars($dateKey, ENT_QUOTES, 'UTF-8'); ?>"
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
                <p class="text-xs font-semibold text-slate-500 mt-1">Set your exact opening and closing time for this day.</p>
            </div>
            <button type="button" data-close-modal="editClinicHoursModal" class="w-10 h-10 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 hover:text-slate-700 hover:border-slate-300 transition-colors">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form method="post">
            <input type="hidden" name="save_clinic_hours" value="1"/>
            <input type="hidden" id="modalDayOfWeekInput" name="day_of_week" value="1"/>
            <input type="hidden" id="modalClinicDateInput" name="clinic_date" value="<?php echo htmlspecialchars($selectedWeekStart->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"/>
            <input type="hidden" name="week_start" value="<?php echo htmlspecialchars($selectedWeekStart->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"/>
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
                        <p class="mt-1.5 text-[11px] font-semibold text-slate-400">Supports precise time selection.</p>
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
                    <textarea id="modalNotes" name="notes" rows="3" maxlength="255" class="modal-time-input w-full px-4 py-3 resize-none" placeholder="Add optional clinic-hours notes for this day..."></textarea>
                </div>
            </div>
            <div class="px-6 sm:px-7 py-4 border-t border-slate-200/80 bg-slate-50/70 flex justify-end gap-2">
                <button type="button" data-close-modal="editClinicHoursModal" class="px-5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-600 font-black text-xs uppercase tracking-[0.16em] hover:border-slate-400">Cancel</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-black text-xs uppercase tracking-[0.16em] shadow-sm shadow-primary/30">Save</button>
            </div>
        </form>
    </div>
</div>

<div id="applyClinicHoursModal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-[2px]">
    <div class="staff-modal-panel clinic-hours-popup-enter bg-white rounded-3xl shadow-[0_24px_64px_-12px_rgba(15,23,42,0.25)] border border-slate-100 w-full max-w-5xl max-h-[92vh] overflow-y-auto overflow-x-hidden flex flex-col">
        <div class="shrink-0 px-6 sm:px-8 pt-7 pb-5 border-b border-slate-100 flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                <span class="material-symbols-outlined text-2xl text-primary">calendar_month</span>
            </div>
            <div class="min-w-0 flex-1 pr-2">
                <h3 class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight">Apply Clinic Hours</h3>
                <p class="text-sm text-slate-500 mt-1 leading-relaxed">Set clinic hours for a custom date range</p>
            </div>
            <button type="button" data-close-modal="applyClinicHoursModal" class="shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                <span class="material-symbols-outlined text-[22px]">close</span>
            </button>
        </div>
        <form method="post" id="applyClinicHoursForm">
            <input type="hidden" name="bulk_apply_clinic_hours" value="1"/>
            <input type="hidden" name="week_start" value="<?php echo htmlspecialchars($selectedWeekStart->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"/>
            <div class="px-6 sm:px-8 pt-3 pb-5 space-y-6 overflow-y-auto">
                <section>
                    <div class="flex items-center gap-2 mb-3">
                        <span class="material-symbols-outlined text-primary text-[22px]">info</span>
                        <h4 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Basic Information</h4>
                    </div>
                <div class="grid grid-cols-1 xl:grid-cols-[1.28fr_1fr] gap-6">
                    <div>
                        <div class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-sm">
                            <div class="flex items-center justify-between mb-4">
                                <button type="button" id="bulkCalendarPrevMonth" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 text-slate-600 hover:text-primary hover:border-primary/30 transition-colors" aria-label="Previous month">
                                    <span class="material-symbols-outlined text-[20px]">chevron_left</span>
                                </button>
                                <p id="bulkCalendarMonthLabel" class="text-xl font-extrabold tracking-tight text-slate-900">Month Year</p>
                                <button type="button" id="bulkCalendarNextMonth" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-slate-200 text-slate-600 hover:text-primary hover:border-primary/30 transition-colors" aria-label="Next month">
                                    <span class="material-symbols-outlined text-[20px]">chevron_right</span>
                                </button>
                            </div>
                            <div class="grid grid-cols-7 gap-2 mb-2">
                                <div class="text-center text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Sun</div>
                                <div class="text-center text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Mon</div>
                                <div class="text-center text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Tue</div>
                                <div class="text-center text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Wed</div>
                                <div class="text-center text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Thu</div>
                                <div class="text-center text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Fri</div>
                                <div class="text-center text-[11px] font-black uppercase tracking-[0.16em] text-slate-500">Sat</div>
                            </div>
                            <div id="bulkCalendarGrid" class="grid grid-cols-7 gap-2"></div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 space-y-4 shadow-sm">
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">event</span>
                                    Start Date <span class="text-red-500 font-bold">*</span>
                                </label>
                                <div class="relative">
                                    <input id="bulkDateFrom" name="bulk_date_from" type="date" required class="bulk-date-input" min="<?php echo htmlspecialchars($today->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($selectedWeekStart->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"/>
                                    <span class="material-symbols-outlined text-[18px] text-slate-500 bulk-date-icon">calendar_month</span>
                                </div>
                            </div>
                            <div>
                                <label for="bulkOpenTime" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">timer</span>
                                    Start Time <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="bulkOpenTime" name="bulk_open_time" type="time" step="60" class="modal-time-input w-full px-4 bulk-time-field" value="08:00"/>
                            </div>
                            <div>
                                <label class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">event</span>
                                    End Date <span class="text-red-500 font-bold">*</span>
                                </label>
                                <div class="relative">
                                    <input id="bulkDateTo" name="bulk_date_to" type="date" required class="bulk-date-input" min="<?php echo htmlspecialchars($today->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($selectedWeekEnd->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"/>
                                    <span class="material-symbols-outlined text-[18px] text-slate-500 bulk-date-icon">calendar_month</span>
                                </div>
                            </div>
                            <div>
                                <label for="bulkCloseTime" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">timer_off</span>
                                    End Time <span class="text-red-500 font-bold">*</span>
                                </label>
                                <input id="bulkCloseTime" name="bulk_close_time" type="time" step="60" class="modal-time-input w-full px-4 bulk-time-field" value="17:00"/>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200/90 bg-white px-4 py-3.5 space-y-3 shadow-sm">
                            <label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700 cursor-pointer">
                                <input id="bulkClosedCheckbox" name="bulk_is_closed" type="checkbox" value="1" class="rounded-md border-slate-300 text-primary focus:ring-primary/20"/>
                                Mark as Closed
                            </label>
                            <label class="inline-flex items-center gap-3 text-sm font-semibold text-slate-700 cursor-pointer">
                                <input id="bulkOverwriteCheckbox" name="bulk_overwrite" type="checkbox" value="1" class="rounded-md border-slate-300 text-primary focus:ring-primary/20"/>
                                Overwrite existing clinic hours
                            </label>
                        </div>
                    </div>
                </div>
                </section>
            </div>
            <div class="border-t border-slate-100 bg-slate-50/50 px-6 sm:px-8 py-4 flex justify-end">
                <div class="flex items-center gap-2">
                    <button type="button" data-close-modal="applyClinicHoursModal" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:bg-slate-50 transition-all shadow-sm">
                        <span class="material-symbols-outlined text-[18px]">close</span>
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-primary hover:bg-primary/92 text-white text-sm font-bold shadow-lg shadow-primary/25 transition-all">
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        Apply Hours
                    </button>
                </div>
            </div>
        </form>
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

    function setBulkClosedState(isClosed) {
        const openEl = document.getElementById('bulkOpenTime');
        const closeEl = document.getElementById('bulkCloseTime');
        if (openEl) openEl.disabled = !!isClosed;
        if (closeEl) closeEl.disabled = !!isClosed;
    }

    function parseISODate(isoDate) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(isoDate || '')) return null;
        const [y, m, d] = isoDate.split('-').map((v) => parseInt(v, 10));
        return new Date(y, m - 1, d, 12, 0, 0, 0);
    }

    function toISODate(dateObj) {
        const y = dateObj.getFullYear();
        const m = String(dateObj.getMonth() + 1).padStart(2, '0');
        const d = String(dateObj.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function formatDateLong(isoDate) {
        const d = parseISODate(isoDate);
        if (!d) return '-';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function formatDateShortNoYear(isoDate) {
        const d = parseISODate(isoDate);
        if (!d) return '-';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
    }

    function formatTimeForSummary(timeVal) {
        if (!timeVal || !/^\d{2}:\d{2}$/.test(timeVal)) return '--';
        const [hourRaw, minute] = timeVal.split(':');
        let hour = parseInt(hourRaw, 10);
        const period = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12;
        if (hour === 0) hour = 12;
        return `${hour}:${minute} ${period}`;
    }

    const bulkCalendarState = {
        viewDate: null,
        startDate: '',
        endDate: '',
        selectionStep: 'start',
        todayIso: '<?php echo htmlspecialchars($today->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>'
    };

    function syncBulkDateFields() {
        const startInput = document.getElementById('bulkDateFrom');
        const endInput = document.getElementById('bulkDateTo');
        if (startInput) startInput.value = bulkCalendarState.startDate;
        if (endInput) endInput.value = bulkCalendarState.endDate || '';
    }

    function setActiveDateTarget(targetKey) {
        const startDisplay = document.getElementById('bulkDateFrom');
        const endDisplay = document.getElementById('bulkDateTo');
        const activeTarget = targetKey === 'end' ? 'end' : 'start';
        if (startDisplay) startDisplay.classList.toggle('is-active', activeTarget === 'start');
        if (endDisplay) endDisplay.classList.toggle('is-active', activeTarget === 'end');
    }

    function updateBulkEventSummary() {
        const summaryEl = document.getElementById('bulkEventSummary');
        if (!summaryEl) return;
        const start = bulkCalendarState.startDate;
        const end = bulkCalendarState.endDate || bulkCalendarState.startDate;
        const openTime = (document.getElementById('bulkOpenTime') || {}).value || '';
        const closeTime = (document.getElementById('bulkCloseTime') || {}).value || '';
        if (!start) {
            summaryEl.textContent = 'Event: -';
            return;
        }
        if (!bulkCalendarState.endDate) {
            summaryEl.textContent = `Event: ${formatDateLong(start)}`;
            return;
        }
        summaryEl.textContent = `Event: ${formatDateShortNoYear(start)} - ${parseISODate(start).getFullYear() === parseISODate(end).getFullYear() ? formatDateShortNoYear(end) : formatDateLong(end)}, from ${formatTimeForSummary(openTime)} - ${formatTimeForSummary(closeTime)}`;
    }

    function renderBulkCalendar() {
        const monthLabel = document.getElementById('bulkCalendarMonthLabel');
        const grid = document.getElementById('bulkCalendarGrid');
        if (!monthLabel || !grid || !bulkCalendarState.viewDate) return;

        monthLabel.textContent = bulkCalendarState.viewDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        grid.innerHTML = '';

        const year = bulkCalendarState.viewDate.getFullYear();
        const month = bulkCalendarState.viewDate.getMonth();
        const firstOfMonth = new Date(year, month, 1, 12);
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const firstWeekday = firstOfMonth.getDay();

        for (let i = 0; i < firstWeekday; i++) {
            const placeholder = document.createElement('div');
            grid.appendChild(placeholder);
        }

        const start = bulkCalendarState.startDate;
        const end = bulkCalendarState.endDate || bulkCalendarState.startDate;
        for (let day = 1; day <= daysInMonth; day++) {
            const d = new Date(year, month, day, 12);
            const iso = toISODate(d);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'bulk-calendar-day';
            btn.textContent = String(day);
            btn.dataset.date = iso;

            const inRange = start && end && iso >= start && iso <= end;
            const isStart = start && iso === start;
            const isEnd = end && iso === end;
            const isSingle = isStart && isEnd;
            const isDisabled = iso < bulkCalendarState.todayIso;
            if (inRange) btn.classList.add('is-in-range');
            if (isStart) btn.classList.add('is-range-start');
            if (isEnd) btn.classList.add('is-range-end');
            if (isSingle) btn.classList.add('is-range-single');
            if (isDisabled) btn.classList.add('is-disabled');

            btn.addEventListener('click', () => {
                if (isDisabled) return;
                if (bulkCalendarState.selectionStep === 'start' || !bulkCalendarState.startDate) {
                    bulkCalendarState.startDate = iso;
                    bulkCalendarState.endDate = '';
                    bulkCalendarState.selectionStep = 'end';
                    setActiveDateTarget('end');
                } else {
                    if (iso < bulkCalendarState.startDate) {
                        bulkCalendarState.endDate = bulkCalendarState.startDate;
                        bulkCalendarState.startDate = iso;
                    } else {
                        bulkCalendarState.endDate = iso;
                    }
                    bulkCalendarState.selectionStep = 'start';
                    setActiveDateTarget('start');
                }
                syncBulkDateFields();
                updateBulkEventSummary();
                renderBulkCalendar();
            });

            grid.appendChild(btn);
        }
    }

    function initializeBulkCalendar() {
        const startInput = document.getElementById('bulkDateFrom');
        const endInput = document.getElementById('bulkDateTo');
        if (!startInput || !endInput) return;

        const defaultStart = '<?php echo htmlspecialchars($selectedWeekStart->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>';
        const startVal = startInput.value || defaultStart;
        bulkCalendarState.startDate = startVal < bulkCalendarState.todayIso ? bulkCalendarState.todayIso : startVal;
        bulkCalendarState.endDate = '';
        const view = parseISODate(bulkCalendarState.startDate) || new Date();
        bulkCalendarState.viewDate = new Date(view.getFullYear(), view.getMonth(), 1, 12);
        bulkCalendarState.selectionStep = 'start';

        syncBulkDateFields();
        updateBulkEventSummary();
        setActiveDateTarget('start');
        renderBulkCalendar();
    }

    document.querySelectorAll('[data-open-modal]').forEach((button) => {
        button.addEventListener('click', () => {
            const targetModal = button.getAttribute('data-open-modal');
            if (targetModal === 'applyClinicHoursModal') {
                const closedEl = document.getElementById('bulkClosedCheckbox');
                setBulkClosedState(closedEl && closedEl.checked);
                initializeBulkCalendar();
            }
            if (targetModal === 'editClinicHoursModal') {
                const day = button.getAttribute('data-day') || 'Monday';
                const openTime = button.getAttribute('data-open-time') || '08:00 AM';
                const closeTime = button.getAttribute('data-close-time') || '05:00 PM';
                const openTimeRaw = button.getAttribute('data-open-time-raw') || '';
                const closeTimeRaw = button.getAttribute('data-close-time-raw') || '';
                const notes = button.getAttribute('data-notes') || '';
                const isClosed = button.getAttribute('data-is-closed') === '1';
                const dayIndex = button.getAttribute('data-day-index') || '1';
                const clinicDate = button.getAttribute('data-date') || '';

                const dayEl = document.getElementById('modalDayLabel');
                const openEl = document.getElementById('modalOpenTime');
                const closeEl = document.getElementById('modalCloseTime');
                const closedEl = document.getElementById('modalClosedCheckbox');
                const dayOfWeekEl = document.getElementById('modalDayOfWeekInput');
                const clinicDateEl = document.getElementById('modalClinicDateInput');
                const notesEl = document.getElementById('modalNotes');

                if (dayEl) dayEl.textContent = day;
                if (openEl) openEl.value = openTimeRaw || twelveHourToTwentyFour(openTime);
                if (closeEl) closeEl.value = closeTimeRaw || twelveHourToTwentyFour(closeTime);
                if (closedEl) closedEl.checked = isClosed;
                if (dayOfWeekEl) dayOfWeekEl.value = dayIndex;
                if (clinicDateEl) clinicDateEl.value = clinicDate;
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
        });
    }

    const bulkClosedCheckbox = document.getElementById('bulkClosedCheckbox');
    if (bulkClosedCheckbox) {
        bulkClosedCheckbox.addEventListener('change', () => {
            setBulkClosedState(bulkClosedCheckbox.checked);
        });
    }

    const applyClinicHoursForm = document.getElementById('applyClinicHoursForm');
    if (applyClinicHoursForm) {
        applyClinicHoursForm.addEventListener('submit', (e) => {
            const fromVal = (document.getElementById('bulkDateFrom') || {}).value;
            const toVal = (document.getElementById('bulkDateTo') || {}).value;
            const todayIso = bulkCalendarState.todayIso;
            if (!fromVal || !toVal) {
                e.preventDefault();
                alert('Please select both start and end dates.');
                return;
            }
            if (fromVal < todayIso || toVal < todayIso) {
                e.preventDefault();
                alert('Please select current or future dates only.');
                return;
            }
            if (fromVal && toVal && fromVal > toVal) {
                e.preventDefault();
                alert('The start date must be on or before the end date.');
                return;
            }
            const closed = bulkClosedCheckbox && bulkClosedCheckbox.checked;
            if (!closed) {
                const o = (document.getElementById('bulkOpenTime') || {}).value;
                const c = (document.getElementById('bulkCloseTime') || {}).value;
                if (o && c && o >= c) {
                    e.preventDefault();
                    alert('Closing time must be later than opening time.');
                }
            }
        });
    }

    const bulkPrevBtn = document.getElementById('bulkCalendarPrevMonth');
    const bulkNextBtn = document.getElementById('bulkCalendarNextMonth');
    if (bulkPrevBtn) {
        bulkPrevBtn.addEventListener('click', () => {
            if (!bulkCalendarState.viewDate) return;
            bulkCalendarState.viewDate = new Date(
                bulkCalendarState.viewDate.getFullYear(),
                bulkCalendarState.viewDate.getMonth() - 1,
                1,
                12
            );
            renderBulkCalendar();
        });
    }
    if (bulkNextBtn) {
        bulkNextBtn.addEventListener('click', () => {
            if (!bulkCalendarState.viewDate) return;
            bulkCalendarState.viewDate = new Date(
                bulkCalendarState.viewDate.getFullYear(),
                bulkCalendarState.viewDate.getMonth() + 1,
                1,
                12
            );
            renderBulkCalendar();
        });
    }

    const bulkStartDateInput = document.getElementById('bulkDateFrom');
    const bulkEndDateInput = document.getElementById('bulkDateTo');

    function openNativeDatePicker(inputEl) {
        if (!inputEl) return;
        if (typeof inputEl.showPicker === 'function') {
            try {
                inputEl.showPicker();
            } catch (e) {
                // Some browsers block showPicker without direct user gesture.
            }
        }
    }

    if (bulkStartDateInput) {
        bulkStartDateInput.addEventListener('click', () => {
            bulkCalendarState.selectionStep = 'start';
            setActiveDateTarget('start');
            openNativeDatePicker(bulkStartDateInput);
        });
        bulkStartDateInput.addEventListener('focus', () => {
            bulkCalendarState.selectionStep = 'start';
            setActiveDateTarget('start');
        });
        bulkStartDateInput.addEventListener('change', () => {
            const selected = bulkStartDateInput.value || '';
            if (!selected) return;
            const startIso = selected < bulkCalendarState.todayIso ? bulkCalendarState.todayIso : selected;
            bulkCalendarState.startDate = startIso;
            if (bulkCalendarState.endDate && bulkCalendarState.endDate < startIso) {
                bulkCalendarState.endDate = '';
            }
            const view = parseISODate(startIso);
            if (view) {
                bulkCalendarState.viewDate = new Date(view.getFullYear(), view.getMonth(), 1, 12);
            }
            bulkCalendarState.selectionStep = 'end';
            syncBulkDateFields();
            updateBulkEventSummary();
            setActiveDateTarget('end');
            renderBulkCalendar();
        });
    }

    if (bulkEndDateInput) {
        bulkEndDateInput.addEventListener('click', () => {
            bulkCalendarState.selectionStep = 'end';
            setActiveDateTarget('end');
            openNativeDatePicker(bulkEndDateInput);
        });
        bulkEndDateInput.addEventListener('focus', () => {
            bulkCalendarState.selectionStep = 'end';
            setActiveDateTarget('end');
        });
        bulkEndDateInput.addEventListener('change', () => {
            const selected = bulkEndDateInput.value || '';
            if (!selected) return;
            const endIso = selected < bulkCalendarState.todayIso ? bulkCalendarState.todayIso : selected;
            if (!bulkCalendarState.startDate) {
                bulkCalendarState.startDate = endIso;
                bulkCalendarState.endDate = '';
                bulkCalendarState.selectionStep = 'end';
                setActiveDateTarget('end');
            } else if (endIso < bulkCalendarState.startDate) {
                bulkCalendarState.startDate = endIso;
                bulkCalendarState.endDate = '';
                bulkCalendarState.selectionStep = 'end';
                setActiveDateTarget('end');
            } else {
                bulkCalendarState.endDate = endIso;
                bulkCalendarState.selectionStep = 'start';
                setActiveDateTarget('start');
            }
            const view = parseISODate(endIso);
            if (view) {
                bulkCalendarState.viewDate = new Date(view.getFullYear(), view.getMonth(), 1, 12);
            }
            syncBulkDateFields();
            updateBulkEventSummary();
            renderBulkCalendar();
        });
    }

    const bulkOpenTimeInput = document.getElementById('bulkOpenTime');
    const bulkCloseTimeInput = document.getElementById('bulkCloseTime');
    if (bulkOpenTimeInput) bulkOpenTimeInput.addEventListener('input', updateBulkEventSummary);
    if (bulkCloseTimeInput) bulkCloseTimeInput.addEventListener('input', updateBulkEventSummary);

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
            closeModal('applyClinicHoursModal');
        }
    });

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
