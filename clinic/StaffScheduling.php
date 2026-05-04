<?php
$staff_nav_active = 'my_schedule';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/appointment_db_tables.php';

if (session_status() === PHP_SESSION_NONE) {
    clinic_session_start();
}

if (!isset($currentTenantSlug)) {
    $currentTenantSlug = '';
    if (isset($_GET['clinic_slug'])) {
        $staffTenantSlug = strtolower(trim((string) $_GET['clinic_slug']));
        if ($staffTenantSlug !== '' && preg_match('/^[a-z0-9\-]+$/', $staffTenantSlug)) {
            $currentTenantSlug = $staffTenantSlug;
        }
    }
}

function toMinutes($timeValue)
{
    $parts = explode(':', (string) $timeValue);
    $hourPart = (int) ($parts[0] ?? 0);
    $minutePart = (int) ($parts[1] ?? 0);
    return ($hourPart * 60) + $minutePart;
}

function formatDisplayTime($timeValue)
{
    $dt = DateTime::createFromFormat('H:i:s', (string) $timeValue);
    if (!$dt) {
        $dt = DateTime::createFromFormat('H:i', (string) $timeValue);
    }
    return $dt ? $dt->format('H:i') : (string) $timeValue;
}

function formatTimeForUi($timeValue)
{
    $dt = DateTime::createFromFormat('H:i:s', (string) $timeValue);
    if (!$dt) {
        $dt = DateTime::createFromFormat('H:i', (string) $timeValue);
    }
    return $dt ? $dt->format('g:i A') : (string) $timeValue;
}

function formatShiftDentistShortName($fullName)
{
    $normalized = trim((string) $fullName);
    if ($normalized === '') {
        return 'Dr. Dentist';
    }

    $normalized = preg_replace('/^dr\.?\s+/i', '', $normalized) ?? $normalized;
    $parts = preg_split('/\s+/', $normalized) ?: [];
    $parts = array_values(array_filter(array_map(static function ($part) {
        return trim((string) $part);
    }, $parts), static function ($part) {
        return $part !== '';
    }));
    if (empty($parts)) {
        return 'Dr. Dentist';
    }

    if (count($parts) === 1) {
        return 'Dr. ' . $parts[0];
    }

    $firstInitial = strtoupper(substr($parts[0], 0, 1));
    $lastName = $parts[count($parts) - 1];
    return 'Dr. ' . $firstInitial . '. ' . $lastName;
}

function mapAppointmentClass($serviceType)
{
    $normalized = strtolower(trim((string) $serviceType));
    if (strpos($normalized, 'hygiene') !== false || strpos($normalized, 'clean') !== false) {
        $label = 'Hygiene';
    } elseif (strpos($normalized, 'consult') !== false) {
        $label = 'Consultation';
    } else {
        $label = 'Treatment';
    }
    return ['label' => $label, 'class' => 'sched-fill--pending'];
}

function normalizeAppointmentStatus($statusValue)
{
    $statusRaw = strtolower(trim((string) $statusValue));
    if ($statusRaw === 'scheduled' || $statusRaw === 'confirmed') {
        return 'pending';
    }
    if ($statusRaw === 'ongoing') {
        return 'in_progress';
    }
    if (!in_array($statusRaw, ['pending', 'in_progress', 'completed', 'cancelled', 'no_show'], true)) {
        return 'pending';
    }
    return $statusRaw;
}

function formatAppointmentStatusLabel($statusValue)
{
    $normalized = normalizeAppointmentStatus($statusValue);
    return ucwords(str_replace('_', ' ', $normalized));
}

function mapAppointmentStatusClass($statusValue)
{
    $normalized = normalizeAppointmentStatus($statusValue);
    switch ($normalized) {
        case 'in_progress':
            return 'sched-fill--ongoing';
        case 'completed':
            return 'sched-fill--completed';
        case 'cancelled':
            return 'sched-fill--cancelled';
        case 'no_show':
            return 'sched-fill--no_show';
        case 'pending':
        default:
            return 'sched-fill--pending';
    }
}

/** @return list<string> */
function scheduling_fallback_service_display_names(string $serviceTypeTruncatedSummary): array
{
    $clean = preg_replace('/\s*\(\+\d+\s+more\)/i', '', trim($serviceTypeTruncatedSummary)) ?? '';
    if ($clean === '') {
        return [];
    }
    $parts = array_map(static function ($part) {
        return trim((string) $part);
    }, explode(',', $clean));

    return array_values(array_unique(array_filter($parts, static function ($part) {
        return $part !== '';
    })));
}

/**
 * Place overlapping time-based entries in parallel horizontal lanes (same algorithm as work shifts).
 * Expects $sortedEntryIndexes sorted by start_min, then end_min.
 *
 * @param array<int, array<string, mixed>> $entries
 * @param int[] $sortedEntryIndexes
 */
function assignTimeOverlappingLaneLayout(array &$entries, array $sortedEntryIndexes)
{
    if (empty($sortedEntryIndexes)) {
        return;
    }
    $activeLanes = [];
    $clusterIndexes = [];
    $clusterLaneCount = 1;
    $finalizeCluster = static function (array &$entriesRef, array $clusterRefs, $laneCount) {
        if (empty($clusterRefs)) {
            return;
        }
        $resolvedLaneCount = max(1, (int) $laneCount);
        foreach ($clusterRefs as $entryRefIndex) {
            $entriesRef[$entryRefIndex]['lane_total'] = $resolvedLaneCount;
        }
    };

    foreach ($sortedEntryIndexes as $entryIndex) {
        if (!array_key_exists($entryIndex, $entries)) {
            continue;
        }
        $spanStart = (int) ($entries[$entryIndex]['start_min'] ?? 0);
        $spanEnd = (int) ($entries[$entryIndex]['end_min'] ?? 0);
        foreach ($activeLanes as $laneKey => $activeEnd) {
            if ((int) $activeEnd <= $spanStart) {
                unset($activeLanes[$laneKey]);
            }
        }
        if (empty($activeLanes) && !empty($clusterIndexes)) {
            $finalizeCluster($entries, $clusterIndexes, $clusterLaneCount);
            $clusterIndexes = [];
            $clusterLaneCount = 1;
        }
        $assignedLane = 0;
        while (isset($activeLanes[$assignedLane])) {
            $assignedLane++;
        }
        $activeLanes[$assignedLane] = $spanEnd;
        $entries[$entryIndex]['lane_index'] = $assignedLane;
        $clusterIndexes[] = $entryIndex;
        if (($assignedLane + 1) > $clusterLaneCount) {
            $clusterLaneCount = $assignedLane + 1;
        }
    }
    $finalizeCluster($entries, $clusterIndexes, $clusterLaneCount);
}

function scheduling_normalize_dentist_key(string $name): string
{
    $t = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    return strtolower($t);
}

/**
 * @return array{left: float, width: float} Horizontal band as percent of the grid column (0–100 scale).
 */
function scheduling_lane_rect_percent(float $laneIndex, int $laneTotal, float $laneGapPercent = 1.5): array
{
    $laneTotal = max(1, $laneTotal);
    $laneIndex = max(0.0, min($laneIndex, (float) ($laneTotal - 1)));
    $usableWidthPercent = 100.0 - (($laneTotal - 1) * $laneGapPercent);
    $laneWidthPercent = $usableWidthPercent > 0 ? ($usableWidthPercent / $laneTotal) : (100.0 / $laneTotal);
    $laneLeftPercent = $laneIndex * ($laneWidthPercent + $laneGapPercent);

    return ['left' => $laneLeftPercent, 'width' => $laneWidthPercent];
}

/**
 * Appointment rectangle: dentist column (from work-shift lanes) with optional sub-lanes for same-dentist overlaps.
 *
 * @return array{left: float, width: float}
 */
function scheduling_appointment_lane_rect_percent(int $dentistColumn, int $columnTotal, int $subIndex, int $subTotal, float $laneGapPercent = 1.5): array
{
    $columnTotal = max(1, $columnTotal);
    $dentistColumn = max(0, min($dentistColumn, $columnTotal - 1));
    $col = scheduling_lane_rect_percent((float) $dentistColumn, $columnTotal, $laneGapPercent);
    if ($subTotal <= 1) {
        return $col;
    }
    $sub = scheduling_lane_rect_percent((float) $subIndex, $subTotal, $laneGapPercent);

    return [
        'left' => $col['left'] + ($sub['left'] / 100.0) * $col['width'],
        'width' => ($sub['width'] / 100.0) * $col['width'],
    ];
}

function formatPaymentStatusLabel($statusValue)
{
    $normalized = strtolower(trim((string) $statusValue));
    if ($normalized === '') {
        return 'Unpaid';
    }
    if ($normalized === 'paid') {
        $normalized = 'completed';
    }
    return ucwords(str_replace('_', ' ', $normalized));
}

function formatVisitTypeLabel($visitType)
{
    $normalized = strtolower(trim((string) $visitType));
    if ($normalized === 'walk_in' || $normalized === 'walkin' || $normalized === 'walk-in') {
        return 'Walk-in';
    }
    return 'Booking';
}

$tz = new DateTimeZone('Asia/Manila');
$selectedDateInput = isset($_GET['selected_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['selected_date'])
    ? (string) $_GET['selected_date']
    : (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$selectedDate = new DateTimeImmutable($selectedDateInput, $tz);
$startOfWeek = $selectedDate->modify('-' . $selectedDate->format('w') . ' days')->setTime(0, 0, 0);
$endOfWeek = $startOfWeek->modify('+6 days')->setTime(23, 59, 59);
$monthLabel = $selectedDate->format('F Y');

$gridStartMinutes = 6 * 60;
$gridEndMinutes = 24 * 60; // 12:00 AM (midnight)
$pixelsPerHour = 64;
$gridHeightPx = (int) ((($gridEndMinutes - $gridStartMinutes) / 60) * $pixelsPerHour);
$gridHourSegments = (int) (($gridEndMinutes - $gridStartMinutes) / 60);

$weekDays = [];
$dayNameMap = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
for ($i = 0; $i < 7; $i++) {
    $dayDate = $startOfWeek->modify('+' . $i . ' days');
    $weekDays[] = [
        'short' => $dayDate->format('D'),
        'date' => $dayDate->format('j'),
        'date_key' => $dayDate->format('Y-m-d'),
        'day_name' => $dayDate->format('l'),
    ];
}

$timeSlots = [];
for ($hour = 6; $hour <= 24; $hour++) {
    $slotHour24 = $hour % 24;
    $timeSlots[] = formatTimeForUi(sprintf('%02d:00', $slotHour24));
}

$miniCalendar = [];
$monthFirstDay = $selectedDate->modify('first day of this month')->setTime(0, 0, 0);
$miniStart = $monthFirstDay->modify('-' . $monthFirstDay->format('w') . ' days');
for ($week = 0; $week < 5; $week++) {
    $row = [];
    for ($day = 0; $day < 7; $day++) {
        $cellDate = $miniStart->modify('+' . (($week * 7) + $day) . ' days');
        $row[] = $cellDate;
    }
    $miniCalendar[] = $row;
}

$tenantId = isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '';
$todayDateOnly = (new DateTimeImmutable('today', $tz))->format('Y-m-d');
$setShiftDefaultDate = $selectedDate->format('Y-m-d');
if ($setShiftDefaultDate < $todayDateOnly) {
    $setShiftDefaultDate = $todayDateOnly;
}
$dentists = [];
$selectedDentistUserId = isset($_GET['user_id']) ? trim((string) $_GET['user_id']) : '';
$selectedDentistId = isset($_GET['dentist_id']) ? trim((string) $_GET['dentist_id']) : '';
$showCompletedInput = isset($_GET['show_completed']) ? strtolower(trim((string) $_GET['show_completed'])) : '1';
$showCompletedAppointments = !in_array($showCompletedInput, ['0', 'false', 'off', 'no'], true);
$hasUserFilterParam = array_key_exists('user_id', $_GET);
$hasDentistIdFilterParam = array_key_exists('dentist_id', $_GET);
$selectedDentistName = 'All dentists';
$isDentistFiltered = false;
$entriesByDate = [];
foreach ($weekDays as $day) {
    $entriesByDate[$day['date_key']] = [];
}
$clinicHoursByDate = [];
foreach ($weekDays as $day) {
    $clinicHoursByDate[$day['date_key']] = [
        'has_record' => false,
        'is_closed' => true,
        'open_time' => '--',
        'close_time' => '--',
        'open_time_raw' => '',
        'close_time_raw' => '',
    ];
}

try {
    $pdo = getDBConnection();

    $defaultClinicHoursByDayIndex = [
        0 => ['open_time_raw' => '09:00', 'close_time_raw' => '17:00', 'is_closed' => false],
        1 => ['open_time_raw' => '08:00', 'close_time_raw' => '17:00', 'is_closed' => false],
        2 => ['open_time_raw' => '08:00', 'close_time_raw' => '17:00', 'is_closed' => false],
        3 => ['open_time_raw' => '08:00', 'close_time_raw' => '17:00', 'is_closed' => false],
        4 => ['open_time_raw' => '08:00', 'close_time_raw' => '17:00', 'is_closed' => false],
        5 => ['open_time_raw' => '08:00', 'close_time_raw' => '17:00', 'is_closed' => false],
        6 => ['open_time_raw' => '09:00', 'close_time_raw' => '15:00', 'is_closed' => false],
    ];
    $resolveClinicHoursByDate = function ($dateValue, $effectiveTenantId = '') use ($pdo, $tz, $defaultClinicHoursByDayIndex, $tenantId) {
        static $legacyCache = [];
        $tid = trim((string) $effectiveTenantId);
        if ($tid === '') {
            $tid = trim((string) $tenantId);
        }

        $dateText = trim((string) $dateValue);
        $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $dateText, $tz);
        if (!($dateObj instanceof DateTimeImmutable)) {
            return [
                'has_record' => false,
                'is_closed' => false,
                'open_time' => '-',
                'close_time' => '-',
                'open_time_raw' => '',
                'close_time_raw' => '',
            ];
        }

        $dayIndex = (int) $dateObj->format('w');

        if ($tid !== '' && !isset($legacyCache[$tid])) {
            $mapByDay = $defaultClinicHoursByDayIndex;
            $weeklyFromDb = array_fill(0, 7, false);
            $legacyHoursStmt = $pdo->prepare('SELECT day_of_week, open_time, close_time, is_closed FROM tbl_clinic_hours WHERE tenant_id = ? AND clinic_date IS NULL');
            $legacyHoursStmt->execute([$tid]);
            foreach ($legacyHoursStmt->fetchAll(PDO::FETCH_ASSOC) as $legacyHoursRow) {
                $legacyDayIndex = isset($legacyHoursRow['day_of_week']) ? (int) $legacyHoursRow['day_of_week'] : -1;
                if (!isset($mapByDay[$legacyDayIndex])) {
                    continue;
                }
                $weeklyFromDb[$legacyDayIndex] = true;
                $legacyIsClosed = isset($legacyHoursRow['is_closed']) && (int) $legacyHoursRow['is_closed'] === 1;
                $legacyOpen = $legacyIsClosed ? '' : substr((string) ($legacyHoursRow['open_time'] ?? ''), 0, 5);
                $legacyClose = $legacyIsClosed ? '' : substr((string) ($legacyHoursRow['close_time'] ?? ''), 0, 5);
                $mapByDay[$legacyDayIndex] = [
                    'open_time_raw' => $legacyOpen,
                    'close_time_raw' => $legacyClose,
                    'is_closed' => $legacyIsClosed,
                ];
            }
            $legacyCache[$tid] = ['map' => $mapByDay, 'weekly_from_db' => $weeklyFromDb];
        }

        $cachedEntry = ($tid !== '' && isset($legacyCache[$tid])) ? $legacyCache[$tid] : null;
        $legacyPerDay = $cachedEntry ? $cachedEntry['map'] : $defaultClinicHoursByDayIndex;
        $weeklyFromDbDay = $cachedEntry ? $cachedEntry['weekly_from_db'] : array_fill(0, 7, false);
        $fallback = $legacyPerDay[$dayIndex] ?? $defaultClinicHoursByDayIndex[$dayIndex] ?? ['open_time_raw' => '08:00', 'close_time_raw' => '17:00', 'is_closed' => false];

        // Weekly template only (clinic_date IS NULL). No per-calendar-date overrides.
        $hasRecord = !empty($weeklyFromDbDay[$dayIndex]);
        $isClosed = (bool) ($fallback['is_closed'] ?? false);
        $openTimeRaw = $isClosed ? '' : (string) ($fallback['open_time_raw'] ?? '');
        $closeTimeRaw = $isClosed ? '' : (string) ($fallback['close_time_raw'] ?? '');
        $hasValidTimes = $openTimeRaw !== '' && $closeTimeRaw !== '';

        return [
            'has_record' => $hasRecord,
            'is_closed' => $isClosed || !$hasValidTimes,
            'open_time' => ($isClosed || !$hasValidTimes) ? '--' : formatTimeForUi($openTimeRaw),
            'close_time' => ($isClosed || !$hasValidTimes) ? '--' : formatTimeForUi($closeTimeRaw),
            'open_time_raw' => $hasValidTimes ? $openTimeRaw : '',
            'close_time_raw' => $hasValidTimes ? $closeTimeRaw : '',
        ];
    };

    $clinicHoursByDate = [];
    foreach ($weekDays as $weekDayRef) {
        $clinicHoursByDate[$weekDayRef['date_key']] = $resolveClinicHoursByDate($weekDayRef['date_key']);
    }

    $clinicHoursSnapshotByDayName = [];
    foreach ($weekDays as $wd) {
        $snap = $clinicHoursByDate[$wd['date_key']] ?? [
            'has_record' => false,
            'is_closed' => true,
            'open_time_raw' => '',
            'close_time_raw' => '',
        ];
        $clinicHoursSnapshotByDayName[$wd['day_name']] = [
            'has_record' => (bool) ($snap['has_record'] ?? false),
            'is_closed' => (bool) ($snap['is_closed'] ?? true),
            'open_time_raw' => (string) ($snap['open_time_raw'] ?? ''),
            'close_time_raw' => (string) ($snap['close_time_raw'] ?? ''),
        ];
    }

    if (isset($_GET['dentist_shift_lookup']) && (string) $_GET['dentist_shift_lookup'] === '1') {
        header('Content-Type: application/json; charset=utf-8');

        $lookupDate = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
        $lookupUserId = isset($_GET['dentist_user_id']) ? trim((string) $_GET['dentist_user_id']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lookupDate)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid date supplied.',
            ]);
            exit;
        }
        if ($lookupUserId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid dentist supplied.',
            ]);
            exit;
        }
        if ($tenantId === '') {
            $resolvedTenantId = clinic_resolve_walkin_tenant_id($pdo);
            if (is_string($resolvedTenantId) && $resolvedTenantId !== '') {
                $tenantId = $resolvedTenantId;
            }
        }
        if ($tenantId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to resolve tenant.',
            ]);
            exit;
        }

        $lookupDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $lookupDate, $tz);
        $lookupDayName = $lookupDateObj instanceof DateTimeImmutable ? $lookupDateObj->format('l') : '';

        $shiftLookupStmt = $pdo->prepare("
            SELECT start_time, end_time
            FROM tbl_schedule_blocks
            WHERE tenant_id = ?
              AND user_id = ?
              AND is_active = 1
              AND block_type IN ('shift', 'work')
              AND (
                  block_date = ?
                  OR (
                      (block_date IS NULL OR block_date = '' OR block_date = '0000-00-00')
                      AND day_of_week = ?
                  )
              )
            ORDER BY
                CASE WHEN block_date = ? THEN 0 ELSE 1 END,
                start_time DESC,
                end_time DESC
            LIMIT 1
        ");
        $shiftLookupStmt->execute([$tenantId, $lookupUserId, $lookupDate, $lookupDayName, $lookupDate]);
        $shiftRow = $shiftLookupStmt->fetch(PDO::FETCH_ASSOC);

        $hasRecord = is_array($shiftRow) && !empty($shiftRow);
        $startRaw = $hasRecord ? substr((string) ($shiftRow['start_time'] ?? ''), 0, 5) : '';
        $endRaw = $hasRecord ? substr((string) ($shiftRow['end_time'] ?? ''), 0, 5) : '';

        echo json_encode([
            'success' => true,
            'date' => $lookupDate,
            'has_record' => $hasRecord,
            'start_time_raw' => $startRaw,
            'end_time_raw' => $endRaw,
            'start_time' => $hasRecord && $startRaw !== '' ? formatTimeForUi($startRaw) : '-',
            'end_time' => $hasRecord && $endRaw !== '' ? formatTimeForUi($endRaw) : '-',
        ]);
        exit;
    }

    if (isset($_GET['clinic_hours_lookup']) && (string) $_GET['clinic_hours_lookup'] === '1') {
        header('Content-Type: application/json; charset=utf-8');

        $lookupDate = isset($_GET['date']) ? trim((string) $_GET['date']) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $lookupDate)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid date supplied.',
            ]);
            exit;
        }

        $resolvedLookupTenant = $tenantId;
        if ($resolvedLookupTenant === '') {
            $resolvedWalkinTenant = clinic_resolve_walkin_tenant_id($pdo);
            if (is_string($resolvedWalkinTenant) && $resolvedWalkinTenant !== '') {
                $resolvedLookupTenant = $resolvedWalkinTenant;
            }
        }
        if ($resolvedLookupTenant === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to resolve tenant.',
            ]);
            exit;
        }

        $resolvedHours = $resolveClinicHoursByDate($lookupDate, $resolvedLookupTenant);

        echo json_encode([
            'success' => true,
            'date' => $lookupDate,
            'has_record' => $resolvedHours['has_record'],
            'is_closed' => $resolvedHours['is_closed'],
            'open_time' => $resolvedHours['open_time'],
            'close_time' => $resolvedHours['close_time'],
            'open_time_raw' => $resolvedHours['open_time_raw'],
            'close_time_raw' => $resolvedHours['close_time_raw'],
        ]);
        exit;
    }

    if (isset($_GET['weekly_shift_editor_lookup']) && (string) $_GET['weekly_shift_editor_lookup'] === '1') {
        header('Content-Type: application/json; charset=utf-8');

        $lookupUserId = isset($_GET['dentist_user_id']) ? trim((string) $_GET['dentist_user_id']) : '';
        if ($lookupUserId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid dentist supplied.',
            ]);
            exit;
        }
        if ($tenantId === '') {
            $resolvedWalkinTenant = clinic_resolve_walkin_tenant_id($pdo);
            if (is_string($resolvedWalkinTenant) && $resolvedWalkinTenant !== '') {
                $tenantId = $resolvedWalkinTenant;
            }
        }
        if ($tenantId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to resolve tenant.',
            ]);
            exit;
        }

        $weeklyShiftStmt = $pdo->prepare("
            SELECT day_of_week, start_time, end_time, notes
            FROM tbl_schedule_blocks
            WHERE tenant_id = ?
              AND user_id = ?
              AND block_type = 'shift'
              AND is_active = 1
              AND (block_date IS NULL OR block_date = '0000-00-00')
              AND day_of_week IS NOT NULL
              AND TRIM(day_of_week) <> ''
            ORDER BY updated_at DESC, created_at DESC
        ");
        $weeklyShiftStmt->execute([$tenantId, $lookupUserId]);
        $weeklyRows = $weeklyShiftStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $shiftsByDay = [];
        $mergedNotes = '';
        foreach ($weeklyRows as $wrow) {
            $dowName = trim((string) ($wrow['day_of_week'] ?? ''));
            if ($dowName === '') {
                continue;
            }
            $startRaw = substr(formatDisplayTime((string) ($wrow['start_time'] ?? '')), 0, 5);
            $endRaw = substr(formatDisplayTime((string) ($wrow['end_time'] ?? '')), 0, 5);
            $shiftsByDay[$dowName] = [
                'start_time_raw' => $startRaw,
                'end_time_raw' => $endRaw,
            ];
            $noteRow = trim((string) ($wrow['notes'] ?? ''));
            if ($mergedNotes === '' && $noteRow !== '') {
                $mergedNotes = $noteRow;
            }
        }

        echo json_encode([
            'success' => true,
            'shifts' => $shiftsByDay,
            'notes' => $mergedNotes,
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_block_time']) && (string) $_POST['save_block_time'] === '1') {
        header('Content-Type: application/json; charset=utf-8');

        if ($tenantId === '') {
            $resolvedTenantId = clinic_resolve_walkin_tenant_id($pdo);
            if (is_string($resolvedTenantId) && $resolvedTenantId !== '') {
                $tenantId = $resolvedTenantId;
            }
        }
        if ($tenantId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to resolve tenant for block time saving.',
            ]);
            exit;
        }

        $blockDentistId = isset($_POST['dentist_id']) ? trim((string) $_POST['dentist_id']) : '';
        $blockDentistUserId = isset($_POST['dentist_user_id']) ? trim((string) $_POST['dentist_user_id']) : '';
        $blockDate = isset($_POST['block_date']) ? trim((string) $_POST['block_date']) : '';
        $blockStart = isset($_POST['start_time']) ? trim((string) $_POST['start_time']) : '';
        $blockEnd = isset($_POST['end_time']) ? trim((string) $_POST['end_time']) : '';
        $blockReasonInput = isset($_POST['reason']) ? trim((string) $_POST['reason']) : 'Break';
        $blockReason = ucfirst(strtolower($blockReasonInput));
        $blockNotesInput = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';

        if ($blockDentistId === '' && $blockDentistUserId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a dentist.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $blockDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a valid block date.']);
            exit;
        }
        if ($blockDate < $todayDateOnly) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Block date must be today or a future date.']);
            exit;
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $blockStart) || !preg_match('/^\d{2}:\d{2}$/', $blockEnd)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select valid start and end times.']);
            exit;
        }
        if (toMinutes($blockEnd) <= toMinutes($blockStart)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Block end time must be later than start time.']);
            exit;
        }
        if (!in_array($blockReason, ['Break', 'Emergency', 'Personal', 'Other'], true)) {
            $blockReason = 'Break';
        }

        $blockUserId = '';
        $resolvedBlockDentistId = $blockDentistId;
        $resolvedDentistTenantId = '';

        if ($blockDentistUserId !== '') {
            $usersLookupStmt = $pdo->prepare("
                SELECT tenant_id, user_id
                FROM tbl_users
                WHERE user_id = ?
                LIMIT 1
            ");
            $usersLookupStmt->execute([$blockDentistUserId]);
            $userRow = $usersLookupStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($userRow) && !empty($userRow)) {
                $blockUserId = trim((string) ($userRow['user_id'] ?? ''));
                $resolvedDentistTenantId = trim((string) ($userRow['tenant_id'] ?? ''));
            }
        }

        if ($blockUserId === '' || $resolvedBlockDentistId === '') {
            $dentistLookupStmt = $pdo->prepare("
                SELECT tenant_id, user_id, dentist_id
                FROM tbl_dentists
                WHERE
                    (? <> '' AND user_id = ?)
                    OR (? <> '' AND dentist_id = ?)
                ORDER BY tenant_id ASC
                LIMIT 1
            ");
            $dentistLookupStmt->execute([
                $blockDentistUserId,
                $blockDentistUserId,
                $blockDentistId,
                $blockDentistId,
            ]);
            $dentistRow = $dentistLookupStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($dentistRow) && !empty($dentistRow)) {
                if ($blockUserId === '') {
                    $blockUserId = trim((string) ($dentistRow['user_id'] ?? ''));
                }
                if ($resolvedBlockDentistId === '') {
                    $resolvedBlockDentistId = trim((string) ($dentistRow['dentist_id'] ?? ''));
                }
                if ($resolvedDentistTenantId === '') {
                    $resolvedDentistTenantId = trim((string) ($dentistRow['tenant_id'] ?? ''));
                }
            }
        }

        if ($blockUserId === '' && $blockDentistId !== '') {
            $dentistEmailLookupStmt = $pdo->prepare("
                SELECT
                    d.tenant_id,
                    d.dentist_id,
                    u.user_id
                FROM tbl_dentists d
                LEFT JOIN tbl_users u
                    ON u.tenant_id = d.tenant_id
                    AND LOWER(TRIM(COALESCE(u.email, ''))) = LOWER(TRIM(COALESCE(d.email, '')))
                    AND u.role = 'dentist'
                WHERE d.dentist_id = ?
                LIMIT 1
            ");
            $dentistEmailLookupStmt->execute([$blockDentistId]);
            $emailResolvedRow = $dentistEmailLookupStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($emailResolvedRow) && !empty($emailResolvedRow)) {
                $emailResolvedUserId = trim((string) ($emailResolvedRow['user_id'] ?? ''));
                if ($emailResolvedUserId !== '') {
                    $blockUserId = $emailResolvedUserId;
                    $resolvedDentistTenantId = trim((string) ($emailResolvedRow['tenant_id'] ?? ''));
                    if ($resolvedBlockDentistId === '') {
                        $resolvedBlockDentistId = trim((string) ($emailResolvedRow['dentist_id'] ?? ''));
                    }
                }
            }
        }

        if ($blockUserId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Selected dentist has no linked user account. Please update dentist account linkage first.']);
            exit;
        }
        if ($resolvedDentistTenantId !== '') {
            $tenantId = $resolvedDentistTenantId;
        }

        $resolvedBlockClinicHours = $resolveClinicHoursByDate($blockDate);
        if ($resolvedBlockClinicHours['is_closed']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'The clinic is closed for the selected date. Block time cannot be added.',
            ]);
            exit;
        }
        $blockClinicOpen = (string) ($resolvedBlockClinicHours['open_time_raw'] ?? '');
        $blockClinicClose = (string) ($resolvedBlockClinicHours['close_time_raw'] ?? '');
        if ($blockClinicOpen === '' || $blockClinicClose === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Clinic hours are unavailable for the selected date.',
            ]);
            exit;
        }
        $blockClinicOpenMinutes = toMinutes($blockClinicOpen);
        $blockClinicCloseMinutes = toMinutes($blockClinicClose);
        $blockStartMinutes = toMinutes($blockStart);
        $blockEndMinutes = toMinutes($blockEnd);
        if ($blockStartMinutes < $blockClinicOpenMinutes || $blockEndMinutes > $blockClinicCloseMinutes) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Block time must fall within clinic operating hours for the selected day.',
            ]);
            exit;
        }

        $blockDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $blockDate, $tz);
        $blockDayName = $blockDateObj instanceof DateTimeImmutable ? $blockDateObj->format('l') : '';
        $shiftLookupStmt = $pdo->prepare("
            SELECT start_time, end_time
            FROM tbl_schedule_blocks
            WHERE tenant_id = ?
              AND user_id = ?
              AND is_active = 1
              AND block_type IN ('shift', 'work')
              AND (
                  block_date = ?
                  OR (
                      (block_date IS NULL OR block_date = '' OR block_date = '0000-00-00')
                      AND day_of_week = ?
                  )
              )
            ORDER BY
                CASE WHEN block_date = ? THEN 0 ELSE 1 END,
                start_time DESC,
                end_time DESC
            LIMIT 1
        ");
        $shiftLookupStmt->execute([$tenantId, $blockUserId, $blockDate, $blockDayName, $blockDate]);
        $shiftRow = $shiftLookupStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($shiftRow) || empty($shiftRow)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'No work shift found for this dentist on the selected date.',
            ]);
            exit;
        }

        $shiftStart = substr((string) ($shiftRow['start_time'] ?? ''), 0, 5);
        $shiftEnd = substr((string) ($shiftRow['end_time'] ?? ''), 0, 5);
        if ($shiftStart === '' || $shiftEnd === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Work shift time is incomplete for the selected date.',
            ]);
            exit;
        }
        $shiftStartMinutes = toMinutes($shiftStart);
        $shiftEndMinutes = toMinutes($shiftEnd);
        if ($blockStartMinutes < $shiftStartMinutes || $blockEndMinutes > $shiftEndMinutes) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Block time must be within the selected dentist work shift (' . formatTimeForUi($shiftStart) . ' - ' . formatTimeForUi($shiftEnd) . ').',
            ]);
            exit;
        }

        $createdByUserId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
        if ($createdByUserId === '') {
            $createdByUserId = null;
        }
        $savedNotes = 'Reason: ' . $blockReason;
        if ($blockNotesInput !== '') {
            $savedNotes .= "\n" . $blockNotesInput;
        }

        $insertBlockStmt = $pdo->prepare("
            INSERT INTO tbl_schedule_blocks
                (tenant_id, user_id, block_date, day_of_week, start_time, end_time, block_type, is_active, notes, created_by)
            VALUES
                (?, ?, ?, NULL, ?, ?, ?, 1, ?, ?)
        ");
        $insertBlockStmt->execute([
            $tenantId,
            $blockUserId,
            $blockDate,
            $blockStart . ':00',
            $blockEnd . ':00',
            strtolower($blockReason),
            $savedNotes,
            $createdByUserId,
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Blocked time saved successfully.',
            'selected_date' => $blockDate,
            'dentist_id' => $resolvedBlockDentistId !== '' ? $resolvedBlockDentistId : $blockDentistId,
            'dentist_user_id' => $blockUserId,
        ]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_set_shift']) && (string) $_POST['save_set_shift'] === '1') {
        header('Content-Type: application/json; charset=utf-8');

        if ($tenantId === '') {
            $resolvedTenantId = clinic_resolve_walkin_tenant_id($pdo);
            if (is_string($resolvedTenantId) && $resolvedTenantId !== '') {
                $tenantId = $resolvedTenantId;
            }
        }
        if ($tenantId === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Unable to resolve tenant for shift saving.',
            ]);
            exit;
        }

        $shiftDentistId = isset($_POST['dentist_id']) ? trim((string) $_POST['dentist_id']) : '';
        $shiftDentistUserId = isset($_POST['dentist_user_id']) ? trim((string) $_POST['dentist_user_id']) : '';
        $shiftWeekAnchor = isset($_POST['shift_week_anchor']) ? trim((string) $_POST['shift_week_anchor']) : '';
        $shiftNotesInput = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';
        $shiftNotes = $shiftNotesInput !== '' ? $shiftNotesInput : null;

        if ($shiftDentistId === '' && $shiftDentistUserId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a dentist.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftWeekAnchor)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a week on the schedule, then try again.']);
            exit;
        }

        $shiftUserId = '';
        $resolvedShiftDentistId = $shiftDentistId;
        $resolvedDentistTenantId = '';

        if ($shiftDentistUserId !== '') {
            $usersLookupStmt = $pdo->prepare("
                SELECT tenant_id, user_id
                FROM tbl_users
                WHERE user_id = ?
                LIMIT 1
            ");
            $usersLookupStmt->execute([$shiftDentistUserId]);
            $userRow = $usersLookupStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($userRow) && !empty($userRow)) {
                $shiftUserId = trim((string) ($userRow['user_id'] ?? ''));
                $resolvedDentistTenantId = trim((string) ($userRow['tenant_id'] ?? ''));
            }
        }

        if ($shiftUserId === '' || $resolvedShiftDentistId === '') {
            $dentistLookupStmt = $pdo->prepare("
                SELECT tenant_id, user_id, dentist_id
                FROM tbl_dentists
                WHERE
                    (? <> '' AND user_id = ?)
                    OR (? <> '' AND dentist_id = ?)
                ORDER BY tenant_id ASC
                LIMIT 1
            ");
            $dentistLookupStmt->execute([
                $shiftDentistUserId,
                $shiftDentistUserId,
                $shiftDentistId,
                $shiftDentistId,
            ]);
            $dentistRow = $dentistLookupStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($dentistRow) && !empty($dentistRow)) {
                if ($shiftUserId === '') {
                    $shiftUserId = trim((string) ($dentistRow['user_id'] ?? ''));
                }
                if ($resolvedShiftDentistId === '') {
                    $resolvedShiftDentistId = trim((string) ($dentistRow['dentist_id'] ?? ''));
                }
                if ($resolvedDentistTenantId === '') {
                    $resolvedDentistTenantId = trim((string) ($dentistRow['tenant_id'] ?? ''));
                }
            }
        }

        if ($shiftUserId === '' && $shiftDentistId !== '') {
            $dentistEmailLookupStmt = $pdo->prepare("
                SELECT
                    d.tenant_id,
                    d.dentist_id,
                    u.user_id
                FROM tbl_dentists d
                LEFT JOIN tbl_users u
                    ON u.tenant_id = d.tenant_id
                    AND LOWER(TRIM(COALESCE(u.email, ''))) = LOWER(TRIM(COALESCE(d.email, '')))
                    AND u.role = 'dentist'
                WHERE d.dentist_id = ?
                LIMIT 1
            ");
            $dentistEmailLookupStmt->execute([$shiftDentistId]);
            $emailResolvedRow = $dentistEmailLookupStmt->fetch(PDO::FETCH_ASSOC);
            if (is_array($emailResolvedRow) && !empty($emailResolvedRow)) {
                $emailResolvedUserId = trim((string) ($emailResolvedRow['user_id'] ?? ''));
                if ($emailResolvedUserId !== '') {
                    $shiftUserId = $emailResolvedUserId;
                    $resolvedDentistTenantId = trim((string) ($emailResolvedRow['tenant_id'] ?? ''));
                    if ($resolvedShiftDentistId === '') {
                        $resolvedShiftDentistId = trim((string) ($emailResolvedRow['dentist_id'] ?? ''));
                    }
                }
            }
        }

        if ($shiftUserId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Selected dentist has no linked user account. Please update dentist account linkage first.']);
            exit;
        }
        if ($resolvedDentistTenantId !== '') {
            $tenantId = $resolvedDentistTenantId;
        }

        $anchorObj = DateTimeImmutable::createFromFormat('Y-m-d', $shiftWeekAnchor, $tz);
        if (!($anchorObj instanceof DateTimeImmutable)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid week reference for shift saving.']);
            exit;
        }
        $sundayObj = $anchorObj->modify('-' . $anchorObj->format('w') . ' days')->setTime(0, 0, 0);
        $dateByDayName = [];
        foreach ($dayNameMap as $idx => $name) {
            $dateByDayName[$name] = $sundayObj->modify('+' . $idx . ' days')->format('Y-m-d');
        }

        $createdByUserId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
        if ($createdByUserId === '') {
            $createdByUserId = null;
        }

        $disableExistingWeeklyStmt = $pdo->prepare("
            UPDATE tbl_schedule_blocks
            SET is_active = 0
            WHERE tenant_id = ?
              AND user_id = ?
              AND block_type = 'shift'
              AND day_of_week = ?
              AND (block_date IS NULL OR block_date = '0000-00-00')
        ");
        $insertWeeklyShiftStmt = $pdo->prepare("
            INSERT INTO tbl_schedule_blocks
                (tenant_id, user_id, block_date, day_of_week, start_time, end_time, block_type, is_active, notes, created_by)
            VALUES
                (?, ?, NULL, ?, ?, ?, 'shift', 1, ?, ?)
        ");

        $failJson = static function (int $code, string $message) {
            http_response_code($code);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        };

        try {
            $pdo->beginTransaction();

            foreach ($dayNameMap as $dayName) {
                $slug = strtolower($dayName);
                $startField = 'week_shift_' . $slug . '_start';
                $endField = 'week_shift_' . $slug . '_end';
                $shiftStart = isset($_POST[$startField]) ? trim((string) $_POST[$startField]) : '';
                $shiftEnd = isset($_POST[$endField]) ? trim((string) $_POST[$endField]) : '';

                $disableExistingWeeklyStmt->execute([$tenantId, $shiftUserId, $dayName]);

                if ($shiftStart === '' && $shiftEnd === '') {
                    continue;
                }

                $refDate = $dateByDayName[$dayName] ?? '';
                if ($refDate === '') {
                    throw new RuntimeException($dayName . ': Unable to resolve that weekday for clinic hours.');
                }
                $resolvedShiftClinicHours = $resolveClinicHoursByDate($refDate);
                $clinicOpen = (string) ($resolvedShiftClinicHours['open_time_raw'] ?? '');
                $clinicClose = (string) ($resolvedShiftClinicHours['close_time_raw'] ?? '');
                $clinicUnavailable = !empty($resolvedShiftClinicHours['is_closed'])
                    || $clinicOpen === ''
                    || $clinicClose === '';

                if ($clinicUnavailable) {
                    // Clinic closed or hours not configured for this calendar weekday: clear weekly shift for this day only; do not block saving other days.
                    continue;
                }

                if ($shiftStart === '' || $shiftEnd === '') {
                    throw new RuntimeException($dayName . ': Enter both start and end times, or leave both empty for a day off.');
                }
                if (!preg_match('/^\d{2}:\d{2}$/', $shiftStart) || !preg_match('/^\d{2}:\d{2}$/', $shiftEnd)) {
                    throw new RuntimeException($dayName . ': Please use valid start and end times.');
                }
                if (toMinutes($shiftEnd) <= toMinutes($shiftStart)) {
                    throw new RuntimeException($dayName . ': End time must be later than start time.');
                }
                $clinicOpenMinutes = toMinutes($clinicOpen);
                $clinicCloseMinutes = toMinutes($clinicClose);
                $shiftStartMinutes = toMinutes($shiftStart);
                $shiftEndMinutes = toMinutes($shiftEnd);
                if ($shiftStartMinutes < $clinicOpenMinutes || $shiftEndMinutes > $clinicCloseMinutes) {
                    throw new RuntimeException($dayName . ': Shift must fall within clinic operating hours for that day.');
                }

                $insertWeeklyShiftStmt->execute([
                    $tenantId,
                    $shiftUserId,
                    $dayName,
                    $shiftStart . ':00',
                    $shiftEnd . ':00',
                    $shiftNotes,
                    $createdByUserId,
                ]);
            }

            $pdo->commit();
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            $failJson(400, $e->getMessage());
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $failJson(500, 'Unable to save weekly shifts. Please try again.');
        }

        echo json_encode([
            'success' => true,
            'message' => 'Weekly shift saved successfully.',
            'selected_date' => $shiftWeekAnchor,
            'dentist_id' => $resolvedShiftDentistId !== '' ? $resolvedShiftDentistId : $shiftDentistId,
            'dentist_user_id' => $shiftUserId,
        ]);
        exit;
    }

    if ($tenantId === '') {
        $resolvedTenantId = clinic_resolve_walkin_tenant_id($pdo);
        if (is_string($resolvedTenantId) && $resolvedTenantId !== '') {
            $tenantId = $resolvedTenantId;
        }
    }

    $dentistSql = "
        SELECT
            COALESCE(NULLIF(TRIM(d.user_id), ''), NULLIF(TRIM(u.user_id), ''), '') AS user_id,
            COALESCE(
                NULLIF(TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))), ''),
                'Dentist'
            ) AS full_name,
            COALESCE(d.dentist_id, '') AS dentist_id,
            COALESCE(d.dentist_display_id, '') AS dentist_display_id,
            COALESCE(d.profile_image, '') AS profile_image
        FROM tbl_dentists d
        LEFT JOIN tbl_users u
            ON u.tenant_id = d.tenant_id
            AND LOWER(TRIM(COALESCE(u.email, ''))) = LOWER(TRIM(COALESCE(d.email, '')))
            AND u.role = 'dentist'
    ";
    $dentistParams = [];
    if ($tenantId !== '') {
        $dentistSql .= " WHERE d.tenant_id = ? ";
        $dentistParams[] = $tenantId;
    }
    $dentistSql .= " ORDER BY d.first_name ASC, d.last_name ASC ";
    $dentistStmt = $pdo->prepare($dentistSql);
    $dentistStmt->execute($dentistParams);
    $dentists = $dentistStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!empty($dentists)) {
        $selectionMatchedDentist = false;
        foreach ($dentists as $dentistRow) {
            $rowUserId = trim((string) ($dentistRow['user_id'] ?? ''));
            $rowDentistId = trim((string) ($dentistRow['dentist_id'] ?? ''));
            $matchesUser = ($selectedDentistUserId !== '' && $rowUserId === $selectedDentistUserId);
            $matchesDentist = ($selectedDentistId !== '' && $rowDentistId === $selectedDentistId);
            if ($matchesUser || $matchesDentist) {
                $selectedDentistUserId = $rowUserId;
                $selectedDentistId = $rowDentistId;
                $selectedDentistName = trim((string) ($dentistRow['full_name'] ?? 'Dentist'));
                $selectionMatchedDentist = true;
                break;
            }
        }
        if (($hasUserFilterParam || $hasDentistIdFilterParam) && !$selectionMatchedDentist) {
            $selectedDentistUserId = '';
            $selectedDentistId = '';
            $selectedDentistName = 'All dentists';
        }
    }

    if ($selectedDentistUserId === '' && $selectedDentistId === '') {
        $selectedDentistName = 'All dentists';
        $isDentistFiltered = false;
    } else {
        $isDentistFiltered = true;
    }

    if ($tenantId !== '') {
            $weekDayNames = array_values(array_map(static function ($d) {
                return (string) $d['day_name'];
            }, $weekDays));
            $weekdayPlaceholders = implode(',', array_fill(0, count($weekDayNames), '?'));

            $scheduleRows = [];
            $blocksSql = "
                SELECT
                    sb.block_date,
                    sb.day_of_week,
                    sb.start_time,
                    sb.end_time,
                    sb.block_type,
                    sb.user_id,
                    sb.notes,
                    COALESCE(NULLIF(TRIM(u.full_name), ''), 'Dentist') AS provider_name
                FROM tbl_schedule_blocks sb
                LEFT JOIN tbl_users u
                    ON u.tenant_id = sb.tenant_id
                   AND u.user_id = sb.user_id
                WHERE sb.tenant_id = ?
                  AND sb.is_active = 1
            ";
            $blocksParams = [$tenantId];
            if ($selectedDentistUserId !== '') {
                $blocksSql .= " AND sb.user_id = ? ";
                $blocksParams[] = $selectedDentistUserId;
            }
            $blocksSql .= "
                AND (
                    (block_date BETWEEN ? AND ?)
                    OR (day_of_week IN ($weekdayPlaceholders) AND (block_date IS NULL OR block_date = '0000-00-00'))
                )
            ";
            $blocksParams = array_merge(
                $blocksParams,
                [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')],
                $weekDayNames
            );
            $blockStmt = $pdo->prepare($blocksSql);
            $blockStmt->execute($blocksParams);
            $scheduleRows = $blockStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($scheduleRows as $row) {
                $blockType = strtolower(trim((string) ($row['block_type'] ?? '')));
                $startTime = formatDisplayTime((string) ($row['start_time'] ?? ''));
                $endTime = formatDisplayTime((string) ($row['end_time'] ?? ''));
                $startMin = toMinutes($startTime);
                $endMin = toMinutes($endTime);
                if ($startMin < $gridStartMinutes) {
                    $startMin += 24 * 60;
                }
                if ($endMin < $gridStartMinutes) {
                    $endMin += 24 * 60;
                }
                if ($endMin <= $startMin) {
                    $endMin += 24 * 60;
                }

                $targetDateKey = '';
                $blockDate = trim((string) ($row['block_date'] ?? ''));
                $dayOfWeek = trim((string) ($row['day_of_week'] ?? ''));
                if ($blockDate !== '' && $blockDate !== '0000-00-00') {
                    $targetDateKey = $blockDate;
                } else {
                    foreach ($weekDays as $wDay) {
                        if (strcasecmp($wDay['day_name'], $dayOfWeek) === 0) {
                            $targetDateKey = $wDay['date_key'];
                            break;
                        }
                    }
                }
                if ($targetDateKey === '' || !isset($entriesByDate[$targetDateKey])) {
                    continue;
                }

                if (!in_array($blockType, ['shift', 'work'], true)) {
                    $providerName = trim((string) ($row['provider_name'] ?? 'Dentist'));
                    if ($providerName === '') {
                        $providerName = 'Dentist';
                    }
                    if (stripos($providerName, 'dr.') !== 0) {
                        $providerName = 'Dr. ' . $providerName;
                    }
                    $blockReason = ucfirst($blockType !== '' ? $blockType : 'break');
                    $blockNotes = '';
                    $blockRawNotes = trim((string) ($row['notes'] ?? ''));
                    if ($blockRawNotes !== '') {
                        if (preg_match('/^\s*Reason\s*:\s*([^\r\n]+)\s*(?:\r?\n(.*))?$/is', $blockRawNotes, $noteMatches)) {
                            $parsedReason = isset($noteMatches[1]) ? trim((string) $noteMatches[1]) : '';
                            $parsedNotes = isset($noteMatches[2]) ? trim((string) $noteMatches[2]) : '';
                            if ($parsedReason !== '') {
                                $blockReason = ucfirst(strtolower($parsedReason));
                            }
                            $blockNotes = $parsedNotes;
                        } else {
                            $blockNotes = $blockRawNotes;
                        }
                    }
                    if (!in_array($blockReason, ['Break', 'Emergency', 'Personal', 'Other'], true)) {
                        $blockReason = 'Other';
                    }
                    $entriesByDate[$targetDateKey][] = [
                        'start_min' => $startMin,
                        'end_min' => $endMin,
                        'label' => 'Blocked',
                        'dentist_name' => $providerName,
                        'block_reason' => $blockReason,
                        'block_notes' => $blockNotes,
                        'class' => 'sched-fill--blocked',
                        'kind' => 'break',
                    ];
                } elseif ($blockType === 'work' || $blockType === 'shift') {
                    $providerName = trim((string) ($row['provider_name'] ?? 'Dentist'));
                    if ($providerName === '') {
                        $providerName = 'Dentist';
                    }
                    if (stripos($providerName, 'dr.') !== 0) {
                        $providerName = 'Dr. ' . $providerName;
                    }
                    $entriesByDate[$targetDateKey][] = [
                        'start_min' => $startMin,
                        'end_min' => $endMin,
                        'label' => 'Work Shift',
                        'dentist_name' => $providerName,
                        'class' => 'sched-fill--work',
                        'kind' => 'work',
                    ];
                }
            }

            $dbTables = clinic_resolve_appointment_db_tables($pdo);
            $appointmentsTable = $dbTables['appointments'] ?? null;
            $appointmentServicesTable = $dbTables['appointment_services'] ?? null;
            $servicesTable = $dbTables['services'] ?? null;
            $patientsTable = $dbTables['patients'] ?? null;
            $dentistsTable = $dbTables['dentists'] ?? null;
            $paymentsTable = $dbTables['payments'] ?? null;
            $appointmentsCols = $appointmentsTable !== null ? clinic_table_columns($pdo, $appointmentsTable) : [];
            $appointmentServicesCols = $appointmentServicesTable !== null ? clinic_table_columns($pdo, $appointmentServicesTable) : [];
            $servicesCols = $servicesTable !== null ? clinic_table_columns($pdo, $servicesTable) : [];
            $paymentsCols = $paymentsTable !== null ? clinic_table_columns($pdo, $paymentsTable) : [];

            if ($appointmentsTable !== null) {
                $qAppt = clinic_quote_identifier($appointmentsTable);
                $qPat = $patientsTable !== null ? clinic_quote_identifier($patientsTable) : null;
                $qDent = $dentistsTable !== null ? clinic_quote_identifier($dentistsTable) : null;
                $qPay = $paymentsTable !== null ? clinic_quote_identifier($paymentsTable) : null;

                $patientNameExpr = $qPat !== null
                    ? "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))), ''), COALESCE(NULLIF(TRIM(a.patient_id), ''), 'Patient'))"
                    : "COALESCE(NULLIF(TRIM(a.patient_id), ''), 'Patient')";
                $dentistNameExpr = $qDent !== null
                    ? "COALESCE(NULLIF(TRIM(CONCAT(COALESCE(d.first_name, ''), ' ', COALESCE(d.last_name, ''))), ''), 'Dentist')"
                    : "'Dentist'";
                $visitTypeExpr = in_array('visit_type', $appointmentsCols, true)
                    ? "COALESCE(NULLIF(TRIM(a.visit_type), ''), 'booking')"
                    : "'booking'";
                $serviceMinutesExpr = "NULL";
                $appointmentServiceNamesExpr = 'NULL';
                $serviceDurationPart = in_array('service_duration', $servicesCols, true) ? 'COALESCE(s.service_duration, 0)' : '0';
                $serviceBufferPart = in_array('buffer_time', $servicesCols, true) ? 'COALESCE(s.buffer_time, 0)' : '0';
                $serviceDurationPartDirect = in_array('service_duration', $servicesCols, true) ? 'COALESCE(s2.service_duration, 0)' : '0';
                $serviceBufferPartDirect = in_array('buffer_time', $servicesCols, true) ? 'COALESCE(s2.buffer_time, 0)' : '0';
                if (
                    $appointmentServicesTable !== null
                    && $servicesTable !== null
                    && in_array('tenant_id', $appointmentServicesCols, true)
                    && in_array('service_id', $appointmentServicesCols, true)
                    && in_array('tenant_id', $servicesCols, true)
                    && in_array('service_id', $servicesCols, true)
                ) {
                    $qAps = clinic_quote_identifier($appointmentServicesTable);
                    $qSrv = clinic_quote_identifier($servicesTable);
                    /** Exclude lines added later (e.g. payment popup add-ons) from calendar duration/names vs appointment.created_at */
                    $apsSchedulingBookingAnchorJoin = '';
                    $apsSchedulingTimeClause = '';
                    if (
                        in_array('added_at', $appointmentServicesCols, true)
                        && in_array('created_at', $appointmentsCols, true)
                        && in_array('id', $appointmentsCols, true)
                    ) {
                        $apsSchedulingBookingAnchorJoin = "
                                INNER JOIN {$qAppt} ap_sched_anchor
                                    ON ap_sched_anchor.tenant_id = a.tenant_id
                                   AND ap_sched_anchor.id = a.id ";
                        $apsSchedulingTimeClause = "
                                  AND (
                                      aps.added_at IS NULL
                                      OR ap_sched_anchor.created_at IS NULL
                                      OR aps.added_at <= DATE_ADD(ap_sched_anchor.created_at, INTERVAL 10 MINUTE)
                                  ) ";
                    }
                    $apsLinkConditions = [];
                    if (in_array('appointment_id', $appointmentServicesCols, true) && in_array('id', $appointmentsCols, true)) {
                        $apsLinkConditions[] = 'aps.appointment_id = a.id';
                    }
                    if (in_array('booking_id', $appointmentServicesCols, true) && in_array('booking_id', $appointmentsCols, true)) {
                        $apsLinkConditions[] = "aps.booking_id IS NOT NULL AND aps.booking_id <> '' AND aps.booking_id = a.booking_id";
                    }
                    if (!empty($apsLinkConditions)) {
                        $serviceMinutesExpr = "
                            (
                                SELECT COALESCE(SUM({$serviceDurationPart} + {$serviceBufferPart}), 0)
                                FROM {$qAps} aps
                                LEFT JOIN {$qSrv} s
                                    ON s.tenant_id = aps.tenant_id
                                   AND s.service_id = aps.service_id
                                {$apsSchedulingBookingAnchorJoin}
                                WHERE aps.tenant_id = a.tenant_id
                                  AND (" . implode(' OR ', $apsLinkConditions) . ")
                                {$apsSchedulingTimeClause}
                            )
                        ";

                        $nameOrderExpr = in_array('service_id', $appointmentServicesCols, true)
                            ? 'aps.service_id'
                            : (
                                in_array('service_name', $appointmentServicesCols, true)
                                ? 'aps.service_name'
                                : (
                                    in_array('id', $appointmentServicesCols, true)
                                        ? 'aps.id'
                                        : '1'
                                )
                            );
                        if (in_array('service_name', $appointmentServicesCols, true)) {
                            $combinedNameSql = "
                                COALESCE(
                                    NULLIF(TRIM(COALESCE(aps.service_name, '')), ''),
                                    TRIM(COALESCE(s.service_name, ''))
                                )
                            ";
                        } else {
                            $combinedNameSql = "TRIM(COALESCE(s.service_name, ''))";
                        }

                        $appointmentServiceNamesExpr = "
                            (
                                SELECT GROUP_CONCAT(
                                    NULLIF(TRIM({$combinedNameSql}), '')
                                    ORDER BY {$nameOrderExpr} SEPARATOR '||'
                                )
                                FROM {$qAps} aps
                                LEFT JOIN {$qSrv} s
                                    ON s.tenant_id = aps.tenant_id
                                   AND s.service_id = aps.service_id
                                {$apsSchedulingBookingAnchorJoin}
                                WHERE aps.tenant_id = a.tenant_id
                                  AND (" . implode(' OR ', $apsLinkConditions) . ")
                                {$apsSchedulingTimeClause}
                            )
                        ";
                    }
                }
                if (
                    $servicesTable !== null
                    && in_array('tenant_id', $servicesCols, true)
                    && in_array('service_name', $servicesCols, true)
                    && in_array('service_type', $appointmentsCols, true)
                ) {
                    $qSrv = clinic_quote_identifier($servicesTable);
                    $serviceMinutesExpr = "
                        COALESCE(
                            {$serviceMinutesExpr},
                            (
                                SELECT {$serviceDurationPartDirect} + {$serviceBufferPartDirect}
                                FROM {$qSrv} s2
                                WHERE s2.tenant_id = a.tenant_id
                                  AND LOWER(TRIM(COALESCE(s2.service_name, ''))) = LOWER(TRIM(COALESCE(a.service_type, '')))
                                LIMIT 1
                            ),
                            0
                        )
                    ";
                }
                $paymentOrderParts = [];
                if (in_array('payment_date', $paymentsCols, true)) {
                    $paymentOrderParts[] = 'py.payment_date DESC';
                }
                if (in_array('created_at', $paymentsCols, true)) {
                    $paymentOrderParts[] = 'py.created_at DESC';
                }
                if (in_array('id', $paymentsCols, true)) {
                    $paymentOrderParts[] = 'py.id DESC';
                }
                if (empty($paymentOrderParts)) {
                    $paymentOrderParts[] = 'py.booking_id DESC';
                }
                $paymentStatusExpr = $qPay !== null
                    ? "COALESCE((SELECT COALESCE(NULLIF(TRIM(py.status), ''), 'unpaid') FROM {$qPay} py WHERE py.tenant_id = a.tenant_id AND py.booking_id = a.booking_id ORDER BY " . implode(', ', $paymentOrderParts) . " LIMIT 1), 'unpaid')"
                    : "'unpaid'";

                $appointmentsSql = "
                    SELECT
                        a.appointment_date,
                        a.appointment_time,
                        a.service_type,
                        {$appointmentServiceNamesExpr} AS appointment_service_names_agg,
                        {$serviceMinutesExpr} AS service_total_minutes,
                        COALESCE(a.status, 'pending') AS appointment_status,
                        {$patientNameExpr} AS patient_name,
                        {$dentistNameExpr} AS dentist_name,
                        {$visitTypeExpr} AS visit_type,
                        {$paymentStatusExpr} AS payment_status
                    FROM {$qAppt} a
                ";
                if ($qPat !== null) {
                    $appointmentsSql .= "
                    LEFT JOIN {$qPat} p
                        ON p.tenant_id = a.tenant_id
                       AND p.patient_id = a.patient_id
                    ";
                }
                if ($qDent !== null && in_array('dentist_id', $appointmentsCols, true)) {
                    $appointmentsSql .= "
                    LEFT JOIN {$qDent} d
                        ON d.tenant_id = a.tenant_id
                       AND d.dentist_id = a.dentist_id
                    ";
                }
                $appointmentsSql .= "
                    WHERE a.tenant_id = ?
                      AND a.appointment_date BETWEEN ? AND ?
                ";
            } else {
                $appointmentsSql = "
                    SELECT appointment_date, appointment_time, service_type,
                        CAST(NULL AS CHAR) AS appointment_service_names_agg,
                        0 AS service_total_minutes,
                        COALESCE(status, 'pending') AS appointment_status,
                        'Patient' AS patient_name, 'Dentist' AS dentist_name, 'booking' AS visit_type, 'unpaid' AS payment_status
                    FROM tbl_appointments
                    WHERE tenant_id = ?
                      AND appointment_date BETWEEN ? AND ?
                ";
            }
            $appointmentsParams = [
                $tenantId,
                $startOfWeek->format('Y-m-d'),
                $endOfWeek->format('Y-m-d'),
            ];
            if ($selectedDentistId !== '' && $appointmentsTable !== null && in_array('dentist_id', $appointmentsCols, true)) {
                $appointmentsSql .= " AND a.dentist_id = ? ";
                $appointmentsParams[] = $selectedDentistId;
            }
            $apptStmt = $pdo->prepare($appointmentsSql);
            $apptStmt->execute($appointmentsParams);
            $appointmentRows = $apptStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($appointmentRows as $row) {
                $dateKey = (string) ($row['appointment_date'] ?? '');
                if (!isset($entriesByDate[$dateKey])) {
                    continue;
                }
                $startTime = formatDisplayTime((string) ($row['appointment_time'] ?? ''));
                $startMin = toMinutes($startTime);
                if ($startMin < $gridStartMinutes) {
                    $startMin += 24 * 60;
                }
                $serviceTotalMinutes = (int) ($row['service_total_minutes'] ?? 0);
                if ($serviceTotalMinutes <= 0) {
                    $serviceTotalMinutes = 60;
                }
                $endMin = $startMin + $serviceTotalMinutes;
                $mapped = mapAppointmentClass((string) ($row['service_type'] ?? ''));
                $appointmentStatus = normalizeAppointmentStatus((string) ($row['appointment_status'] ?? 'pending'));
                if (!$showCompletedAppointments && $appointmentStatus === 'completed') {
                    continue;
                }
                $patientName = trim((string) ($row['patient_name'] ?? 'Patient'));
                if ($patientName === '') {
                    $patientName = 'Patient';
                }
                $dentistName = trim((string) ($row['dentist_name'] ?? 'Dentist'));
                if ($dentistName === '') {
                    $dentistName = 'Dentist';
                }
                if (stripos($dentistName, 'dr.') !== 0) {
                    $dentistName = 'Dr. ' . $dentistName;
                }
                $serviceName = trim((string) ($row['service_type'] ?? 'Treatment'));
                if ($serviceName === '') {
                    $serviceName = 'Treatment';
                }
                $namesAggRaw = trim((string) ($row['appointment_service_names_agg'] ?? ''));
                $tooltipServiceNames = [];
                if ($namesAggRaw !== '') {
                    foreach (explode('||', $namesAggRaw) as $segment) {
                        $segmentTrim = trim((string) $segment);
                        if ($segmentTrim !== '') {
                            $tooltipServiceNames[] = $segmentTrim;
                        }
                    }
                }
                $tooltipServiceNames = array_values(array_unique($tooltipServiceNames));
                if ($tooltipServiceNames === []) {
                    $tooltipServiceNames = scheduling_fallback_service_display_names((string) ($row['service_type'] ?? ''));
                }
                $tooltipServicesJsonRaw = json_encode($tooltipServiceNames, JSON_UNESCAPED_UNICODE);
                $tooltipServicesJson = is_string($tooltipServicesJsonRaw) ? $tooltipServicesJsonRaw : '[]';
                $visitTypeLabel = formatVisitTypeLabel((string) ($row['visit_type'] ?? 'booking'));
                $paymentStatusLabel = formatPaymentStatusLabel((string) ($row['payment_status'] ?? 'unpaid'));
                $entriesByDate[$dateKey][] = [
                    'start_min' => $startMin,
                    'end_min' => $endMin,
                    'label' => 'Appointment',
                    'status_label' => formatAppointmentStatusLabel($appointmentStatus),
                    'patient_name' => $patientName,
                    'dentist_name' => $dentistName,
                    'service_name' => $serviceName,
                    'tooltip_services_json' => $tooltipServicesJson,
                    'visit_type_label' => $visitTypeLabel,
                    'payment_status_label' => $paymentStatusLabel,
                    'appointment_status_label' => formatAppointmentStatusLabel($appointmentStatus),
                    'service_label' => (string) ($mapped['label'] ?? 'Treatment'),
                    'class' => mapAppointmentStatusClass($appointmentStatus),
                    'kind' => 'appointment',
                ];
            }
    }
} catch (Throwable $e) {
    // Keep empty-state UI when data is unavailable.
}

$workDentistLaneByDay = [];
foreach ($entriesByDate as $dayKey => $entries) {
    usort($entries, static function ($a, $b) {
        $startCompare = ((int) $a['start_min']) <=> ((int) $b['start_min']);
        if ($startCompare !== 0) {
            return $startCompare;
        }
        return ((int) $a['end_min']) <=> ((int) $b['end_min']);
    });

    $workEntryIndexes = [];
    foreach ($entries as $entryIndex => $entry) {
        if ((string) ($entry['kind'] ?? '') === 'work') {
            $workEntryIndexes[] = $entryIndex;
        }
    }
    usort($workEntryIndexes, static function ($a, $b) use ($entries) {
        $startCompare = ((int) $entries[$a]['start_min']) <=> ((int) $entries[$b]['start_min']);
        if ($startCompare !== 0) {
            return $startCompare;
        }
        return ((int) $entries[$a]['end_min']) <=> ((int) $entries[$b]['end_min']);
    });
    assignTimeOverlappingLaneLayout($entries, $workEntryIndexes);

    $workDentistLaneByDay[$dayKey] = [];
    foreach ($entries as $entry) {
        if ((string) ($entry['kind'] ?? '') !== 'work') {
            continue;
        }
        $dentKey = scheduling_normalize_dentist_key((string) ($entry['dentist_name'] ?? ''));
        if ($dentKey === '') {
            continue;
        }
        if (!array_key_exists($dentKey, $workDentistLaneByDay[$dayKey])) {
            $workDentistLaneByDay[$dayKey][$dentKey] = (int) ($entry['lane_index'] ?? 0);
        }
    }

    $appointmentIndexesByDentist = [];
    foreach ($entries as $entryIndex => $entry) {
        if ((string) ($entry['kind'] ?? '') !== 'appointment') {
            continue;
        }
        $dentKey = scheduling_normalize_dentist_key((string) ($entry['dentist_name'] ?? ''));
        $appointmentIndexesByDentist[$dentKey][] = $entryIndex;
    }
    foreach ($appointmentIndexesByDentist as $groupIndexes) {
        usort($groupIndexes, static function ($a, $b) use ($entries) {
            $startCompare = ((int) $entries[$a]['start_min']) <=> ((int) $entries[$b]['start_min']);
            if ($startCompare !== 0) {
                return $startCompare;
            }

            return ((int) $entries[$a]['end_min']) <=> ((int) $entries[$b]['end_min']);
        });
        assignTimeOverlappingLaneLayout($entries, $groupIndexes);
    }

    $entriesByDate[$dayKey] = $entries;
}

$dayMaxLaneCount = [];
foreach ($weekDays as $d) {
    $dayMaxLaneCount[$d['date_key']] = 1;
}
foreach ($entriesByDate as $dayKey => $ents) {
    foreach ($ents as $e) {
        $eKind = (string) ($e['kind'] ?? '');
        if ($eKind === 'work' || $eKind === 'appointment') {
            $lt = max(1, (int) ($e['lane_total'] ?? 1));
            if ($lt > $dayMaxLaneCount[$dayKey]) {
                $dayMaxLaneCount[$dayKey] = $lt;
            }
        }
    }
}
$schedulePerLaneMinPx = 100;
$scheduleGridColParts = ['84px'];
$scheduleGridMinWidth = 84;
foreach ($weekDays as $d) {
    $lanes = max(1, (int) ($dayMaxLaneCount[$d['date_key']] ?? 1));
    $scheduleGridColParts[] = 'minmax(' . ($lanes * $schedulePerLaneMinPx) . 'px,' . $lanes . 'fr)';
    $scheduleGridMinWidth += $lanes * $schedulePerLaneMinPx;
}
$scheduleGridMinWidth = max(900, $scheduleGridMinWidth);
$scheduleGridTemplateColumns = implode(' ', $scheduleGridColParts);

$dentistsSeedData = array_map(static function ($dentist) {
    return [
        'user_id' => (string) ($dentist['user_id'] ?? ''),
        'full_name' => trim((string) ($dentist['full_name'] ?? 'Dentist')),
        'dentist_display_id' => trim((string) ($dentist['dentist_display_id'] ?? '')),
        'dentist_id' => trim((string) ($dentist['dentist_id'] ?? '')),
        'profile_image' => trim((string) ($dentist['profile_image'] ?? '')),
    ];
}, $dentists);
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
            box-sizing: border-box;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .set-shift-time-input--clinic-closed {
            cursor: not-allowed;
            background-color: #f1f5f9;
            color: #94a3b8;
            border-color: #e2e8f0;
        }
        .set-shift-time-input--clinic-closed::placeholder {
            color: #cbd5e1;
            letter-spacing: 0.02em;
        }
        /* Keep schedule blocks below sticky top header (z-30) while preserving in-grid layering */
        .schedule-block-layer-appointment,
        .schedule-block-layer-appointment_past {
            z-index: 18;
        }
        .schedule-block-layer-blocked {
            z-index: 16;
        }
        .schedule-block-layer-work {
            z-index: 14;
        }
        .schedule-block-layer-default {
            z-index: 12;
        }
        .schedule-block:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 18px -10px rgba(15, 23, 42, 0.6);
        }
        /* Main grid side-by-side appointment blocks: responsive detail via size container queries */
        .schedule-block--appt-overlap {
            container: sched-appt / size;
        }
        .appt-grid-inner {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            height: 100%;
            min-height: 0;
            min-width: 0;
            overflow: hidden;
            row-gap: 0.16rem;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .appt-grid-line {
            margin: 0;
            font-size: 10px;
            line-height: 1.2;
            min-width: 0;
            width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-align: center;
        }
        /* Full word "APPOINTMENT" must not ellipsis — wrap or scale instead of "APPOINTME…" */
        .appt-grid-line--title {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            white-space: normal;
            text-overflow: clip;
            overflow-wrap: anywhere;
            line-height: 1.15;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            text-align: center;
        }
        @container sched-appt (max-width: 90px) {
            .appt-grid-line--title {
                font-size: 8.5px;
                letter-spacing: 0.04em;
            }
        }
        @container sched-appt (max-width: 60px) {
            .appt-grid-line--title {
                font-size: 7.5px;
                letter-spacing: 0.02em;
            }
        }
        .appt-grid-line--status {
            display: none;
            font-weight: 600;
        }
        .appt-grid-line--time {
            display: none;
            font-weight: 600;
            opacity: 0.95;
        }
        @container sched-appt (min-width: 68px) and (min-height: 36px) {
            .appt-grid-line--status {
                display: block;
            }
        }
        @container sched-appt (min-width: 96px) and (min-height: 50px) {
            .appt-grid-line--time {
                display: block;
            }
        }
        /* Main schedule grid + legend — single source; keep in sync with mapAppointmentStatusClass() / work & break entry classes */
        .sched-fill--pending {
            background-color: #dbeafe;
            border-color: #93c5fd;
            color: #0c4a6e;
        }
        .sched-fill--ongoing {
            background-color: #2b8beb;
            border-color: #2b8beb;
            color: #ffffff;
        }
        .sched-fill--completed {
            background-color: #e2e8f0;
            border-color: #cbd5e1;
            color: #64748b;
        }
        .sched-fill--cancelled {
            background-color: #fee2e2;
            border-color: #fecaca;
            color: #991b1b;
        }
        .sched-fill--no_show {
            background-color: #ffedd5;
            border-color: #fdba74;
            color: #9a3412;
        }
        .sched-fill--work {
            background-color: #dcfce7;
            border-color: #86efac;
            color: #14532d;
        }
        .sched-fill--blocked {
            background-color: #52525b;
            border-color: #3f3f46;
            color: #f4f4f5;
        }
        .sched-fill--clinic-closed {
            background-color: #f1f5f9;
            border-color: #e2e8f0;
            color: #64748b;
        }
        .schedule-closed-slot-overlay {
            position: absolute;
            left: 0;
            right: 0;
            background: #f1f5f9;
            z-index: 11;
            pointer-events: auto;
        }
        .sched-legend-swatch {
            min-width: 2.75rem;
            height: 0.9rem;
            border-width: 1px;
            border-style: solid;
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
        .alert-popup-enter {
            animation: alert-popup-in 0.28s cubic-bezier(0.22, 1, 0.36, 1) forwards;
        }
        .schedule-popup-enter {
            animation: schedule-popup-in 0.3s cubic-bezier(0.22, 1, 0.36, 1) forwards;
            transform-origin: center;
        }
        @keyframes alert-popup-in {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes schedule-popup-in {
            from { opacity: 0; transform: translateY(14px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .shift-tooltip-card {
            position: fixed;
            z-index: 95;
            pointer-events: none;
            width: min(19rem, calc(100vw - 1.5rem));
            border: 1px solid rgba(16, 185, 129, 0.35);
            border-radius: 0.9rem;
            background: rgba(255, 255, 255, 0.97);
            box-shadow: 0 18px 34px -20px rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(3px);
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 0.14s ease, transform 0.14s ease;
        }
        .shift-tooltip-card.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        .appointment-tooltip-card {
            position: fixed;
            z-index: 96;
            pointer-events: none;
            width: min(22rem, calc(100vw - 1.5rem));
            border: 1px solid rgba(43, 139, 235, 0.35);
            border-radius: 0.95rem;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 20px 38px -22px rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(4px);
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 0.14s ease, transform 0.14s ease;
        }
        .appointment-tooltip-card.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        .block-tooltip-card {
            position: fixed;
            z-index: 97;
            pointer-events: none;
            width: min(22rem, calc(100vw - 1.5rem));
            border: 1px solid rgba(239, 68, 68, 0.32);
            border-radius: 0.95rem;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 20px 38px -22px rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(4px);
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 0.14s ease, transform 0.14s ease;
        }
        .block-tooltip-card.is-visible {
            opacity: 1;
            transform: translateY(0);
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
                <div class="mt-5 xl:mt-14 flex flex-wrap items-center gap-2.5 xl:justify-end">
                    <button id="openBlockTimeModalBtn" type="button" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-base">add</span>
                        Block Time
                    </button>
                    <button id="openSetShiftModalBtn" type="button" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-primary/30 bg-primary/10 text-primary hover:bg-primary/15 font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-base">schedule</span>
                        Set Shift
                    </button>
                </div>
            </div>
        </section>

        <section class="elevated-card p-6 rounded-3xl">
            <form method="get" id="scheduleFilterForm" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-4">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Dentist</label>
                    <input id="selectedDentistUserId" type="hidden" name="user_id" value="<?php echo htmlspecialchars($selectedDentistUserId, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <input id="selectedDentistId" type="hidden" name="dentist_id" value="<?php echo htmlspecialchars($selectedDentistId, ENT_QUOTES, 'UTF-8'); ?>"/>
                    <div class="flex items-center gap-2">
                        <button id="chooseDentistBtn" type="button" class="schedule-input w-full py-3 px-4 text-left inline-flex items-center justify-between">
                            <span id="selectedDentistLabel"><?php echo htmlspecialchars($selectedDentistName, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="material-symbols-outlined text-[18px] text-slate-500">keyboard_arrow_down</span>
                        </button>
                        <button id="clearDentistBtn" type="button" class="px-3 py-3 rounded-xl border border-slate-200 bg-white text-slate-600 text-xs font-bold uppercase tracking-wider hover:text-primary hover:border-primary/30 transition-colors <?php echo ($selectedDentistUserId === '' && $selectedDentistId === '') ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo ($selectedDentistUserId === '' && $selectedDentistId === '') ? 'disabled' : ''; ?>>
                            Clear
                        </button>
                    </div>
                </div>
                <div class="lg:col-span-4">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Week Reference Date</label>
                    <input id="weekReferenceDateInput" type="date" name="selected_date" value="<?php echo htmlspecialchars($selectedDate->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" class="w-full py-3 px-4 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
                </div>
                <div class="lg:col-span-4">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Completed Appointments</label>
                    <label class="h-[46px] px-4 rounded-xl border border-slate-200 bg-white inline-flex items-center gap-3 text-sm font-semibold text-slate-700 cursor-pointer select-none w-full">
                        <input type="hidden" name="show_completed" value="0"/>
                        <input id="showCompletedToggle" type="checkbox" name="show_completed" value="1" <?php echo $showCompletedAppointments ? 'checked' : ''; ?> class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary/30"/>
                        <span>Show Completed</span>
                    </label>
                </div>
                <?php if ($currentTenantSlug !== ''): ?>
                    <input type="hidden" name="clinic_slug" value="<?php echo htmlspecialchars($currentTenantSlug, ENT_QUOTES, 'UTF-8'); ?>"/>
                <?php endif; ?>
            </form>
        </section>

        <section class="grid grid-cols-1 2xl:grid-cols-12 gap-6 items-start">
            <aside class="2xl:col-span-3 space-y-6">
                <div class="elevated-card rounded-3xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em]">Calendar</h2>
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="h-7 w-7 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-primary hover:border-primary/35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25 transition-colors"
                                data-mini-calendar-month-nav="prev"
                                aria-label="Go to previous month"
                            >
                                <span class="material-symbols-outlined text-[18px]">chevron_left</span>
                            </button>
                            <span class="text-xs font-bold text-slate-500 min-w-[96px] text-center"><?php echo htmlspecialchars($monthLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            <button
                                type="button"
                                class="h-7 w-7 inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-primary hover:border-primary/35 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25 transition-colors"
                                data-mini-calendar-month-nav="next"
                                aria-label="Go to next month"
                            >
                                <span class="material-symbols-outlined text-[18px]">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-7 gap-1.5 text-center text-[11px] font-bold uppercase tracking-widest text-slate-400 mb-2">
                        <span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span>
                    </div>
                    <div class="space-y-1.5">
                        <?php foreach ($miniCalendar as $weekIndex => $weekRow): ?>
                            <div class="grid grid-cols-7 gap-1.5">
                                <?php foreach ($weekRow as $cellDate): ?>
                                    <?php
                                    $dateNumber = (int) $cellDate->format('j');
                                    $isCurrentDate = $cellDate->format('Y-m-d') === $selectedDate->format('Y-m-d');
                                    $isActiveWeek = $cellDate >= $startOfWeek && $cellDate <= $endOfWeek;
                                    $dateClasses = 'h-8 rounded-lg text-xs font-bold flex items-center justify-center border';
                                    if ($isCurrentDate) {
                                        $dateClasses .= ' bg-primary text-white border-primary';
                                    } elseif ($isActiveWeek) {
                                        $dateClasses .= ' bg-primary/10 text-primary border-primary/20';
                                    } else {
                                        $dateClasses .= ' bg-white text-slate-500 border-slate-100';
                                    }
                                    ?>
                                    <button
                                        type="button"
                                        class="<?php echo $dateClasses; ?> w-full hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/25"
                                        data-mini-calendar-date="<?php echo htmlspecialchars($cellDate->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
                                        aria-label="View schedule for <?php echo htmlspecialchars($cellDate->format('F j, Y'), ENT_QUOTES, 'UTF-8'); ?>"
                                    >
                                        <?php echo htmlspecialchars((string) $dateNumber, ENT_QUOTES, 'UTF-8'); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="elevated-card rounded-3xl p-6">
                    <h3 class="text-sm font-black text-slate-500 uppercase tracking-[0.2em] mb-4">Legend</h3>
                    <div class="space-y-4">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-1.5">Appointments</p>
                            <p class="text-[10px] font-bold text-slate-400 tracking-wide mb-2">Color by status</p>
                            <div class="space-y-1.5">
                                <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-2.5 py-2">
                                    <span class="sched-legend-swatch shrink-0 rounded-xl sched-fill--pending" aria-hidden="true"></span>
                                    <span class="text-sm font-semibold text-slate-700">Pending / Scheduled</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-2.5 py-2">
                                    <span class="sched-legend-swatch shrink-0 rounded-xl sched-fill--ongoing" aria-hidden="true"></span>
                                    <span class="text-sm font-semibold text-slate-700">Ongoing</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-2.5 py-2">
                                    <span class="sched-legend-swatch shrink-0 rounded-xl sched-fill--completed" aria-hidden="true"></span>
                                    <span class="text-sm font-semibold text-slate-700">Completed</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-2.5 py-2">
                                    <span class="sched-legend-swatch shrink-0 rounded-xl sched-fill--cancelled" aria-hidden="true"></span>
                                    <span class="text-sm font-semibold text-slate-700">Cancelled</span>
                                </div>
                                <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-2.5 py-2">
                                    <span class="sched-legend-swatch shrink-0 rounded-xl sched-fill--no_show" aria-hidden="true"></span>
                                    <span class="text-sm font-semibold text-slate-700">No show</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-1.5">Work shift / available slot</p>
                            <p class="text-[10px] font-bold text-slate-400 tracking-wide mb-2">Dentist hours &amp; open capacity</p>
                            <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-2.5 py-2">
                                <span class="sched-legend-swatch shrink-0 rounded-xl sched-fill--work" aria-hidden="true"></span>
                                <span class="text-sm font-semibold text-slate-700">Work shift</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-1.5">Blocked time / schedule</p>
                            <p class="text-[10px] font-bold text-slate-400 tracking-wide mb-2">Breaks &amp; personal blocks</p>
                            <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-2.5 py-2">
                                <span class="sched-legend-swatch shrink-0 rounded-xl sched-fill--blocked" aria-hidden="true"></span>
                                <span class="text-sm font-semibold text-slate-700">Blocked</span>
                            </div>
                        </div>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-1.5">Clinic hours status</p>
                            <p class="text-[10px] font-bold text-slate-400 tracking-wide mb-2">Light Grey = Clinic Closed / Unavailable</p>
                            <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-2.5 py-2">
                                <span class="sched-legend-swatch shrink-0 rounded-xl sched-fill--clinic-closed" aria-hidden="true"></span>
                                <span class="text-sm font-semibold text-slate-700">Clinic Closed / Unavailable</span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-5 pt-5 border-t border-slate-100 space-y-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Entry type (icons in grid)</p>
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
                <div class="mb-4 inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-extrabold uppercase tracking-[0.15em] <?php echo $isDentistFiltered ? 'border-primary/30 bg-primary/10 text-primary' : 'border-slate-200 bg-slate-50 text-slate-600'; ?>">
                    <span class="material-symbols-outlined text-base"><?php echo $isDentistFiltered ? 'filter_alt' : 'groups'; ?></span>
                    <?php echo htmlspecialchars($isDentistFiltered ? ('Filtered: ' . $selectedDentistName) : 'Overall View: All Dentists', ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="overflow-x-auto min-w-0 w-full" style="-webkit-overflow-scrolling: touch;">
                    <div class="border border-slate-200 rounded-2xl overflow-hidden bg-white shrink-0" style="min-width: <?php echo (int) $scheduleGridMinWidth; ?>px;">
                        <div class="grid bg-slate-50 border-b border-slate-200" style="grid-template-columns: <?php echo htmlspecialchars($scheduleGridTemplateColumns, ENT_QUOTES, 'UTF-8'); ?>;">
                            <div class="px-3 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 sticky left-0 z-20 bg-slate-50 border-r border-slate-200 shadow-[2px_0_12px_-4px_rgba(15,23,42,0.1)]">Time</div>
                            <?php foreach ($weekDays as $weekDay): ?>
                                <?php
                                $dayLaneCount = (int) ($dayMaxLaneCount[$weekDay['date_key']] ?? 1);
                                ?>
                                <div class="px-3 py-3 border-l border-slate-200 min-w-0">
                                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400"><?php echo htmlspecialchars($weekDay['short'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-sm font-bold text-slate-700 mt-1"><?php echo htmlspecialchars((string) $weekDay['date'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php if ($dayLaneCount > 1): ?>
                                        <p class="text-[9px] font-bold uppercase tracking-wider text-primary/80 mt-1.5"><?php echo (int) $dayLaneCount; ?> concurrent</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="grid" style="grid-template-columns: <?php echo htmlspecialchars($scheduleGridTemplateColumns, ENT_QUOTES, 'UTF-8'); ?>;">
                            <div class="border-r border-slate-100 bg-slate-50/80 backdrop-blur-sm sticky left-0 z-10 shadow-[2px_0_14px_-4px_rgba(15,23,42,0.12)]">
                                <?php foreach ($timeSlots as $slotTime): ?>
                                    <div class="h-16 px-3 py-3 text-xs font-bold text-slate-500 border-b border-slate-100 last:border-b-0 bg-slate-50/70">
                                        <?php echo htmlspecialchars($slotTime, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php foreach ($weekDays as $weekDay): ?>
                                <?php
                                $dayEntries = $entriesByDate[$weekDay['date_key']] ?? [];
                                $dayClinicHours = $clinicHoursByDate[$weekDay['date_key']] ?? ['is_closed' => true, 'open_time_raw' => '', 'close_time_raw' => ''];
                                $closedSegments = [];
                                if (!empty($dayClinicHours['is_closed'])) {
                                    $closedSegments[] = ['top' => 0, 'height' => $gridHeightPx];
                                } else {
                                    $openRaw = (string) ($dayClinicHours['open_time_raw'] ?? '');
                                    $closeRaw = (string) ($dayClinicHours['close_time_raw'] ?? '');
                                    if ($openRaw !== '' && $closeRaw !== '') {
                                        $openMin = toMinutes($openRaw);
                                        $closeMin = toMinutes($closeRaw);
                                        if ($openMin < $gridStartMinutes) {
                                            $openMin += 24 * 60;
                                        }
                                        if ($closeMin < $gridStartMinutes) {
                                            $closeMin += 24 * 60;
                                        }
                                        if ($closeMin <= $openMin) {
                                            $closeMin += 24 * 60;
                                        }
                                        $openTop = max(0, min($gridHeightPx, (int) round((($openMin - $gridStartMinutes) / 60) * $pixelsPerHour)));
                                        $closeTop = max(0, min($gridHeightPx, (int) round((($closeMin - $gridStartMinutes) / 60) * $pixelsPerHour)));
                                        if ($openTop > 0) {
                                            $closedSegments[] = ['top' => 0, 'height' => $openTop];
                                        }
                                        if ($closeTop < $gridHeightPx) {
                                            $closedSegments[] = ['top' => $closeTop, 'height' => $gridHeightPx - $closeTop];
                                        }
                                    } else {
                                        $closedSegments[] = ['top' => 0, 'height' => $gridHeightPx];
                                    }
                                }
                                ?>
                                <div class="relative border-l border-slate-100" style="height: <?php echo (int) $gridHeightPx; ?>px;">
                                    <?php for ($line = 1; $line <= $gridHourSegments; $line++): ?>
                                        <div class="absolute left-0 right-0 border-t border-slate-100" style="top: <?php echo (int) ($line * $pixelsPerHour); ?>px;"></div>
                                    <?php endfor; ?>
                                    <?php foreach ($closedSegments as $closedSegment): ?>
                                        <?php if ((int) ($closedSegment['height'] ?? 0) <= 0) { continue; } ?>
                                        <div
                                            class="schedule-closed-slot-overlay"
                                            style="top: <?php echo (int) ($closedSegment['top'] ?? 0); ?>px; height: <?php echo (int) ($closedSegment['height'] ?? 0); ?>px;"
                                            title="Clinic Closed / Unavailable"
                                            aria-hidden="true"
                                        ></div>
                                    <?php endforeach; ?>
                                    <?php foreach ($dayEntries as $entry): ?>
                                        <?php
                                        $entryStart = max((int) $entry['start_min'], $gridStartMinutes);
                                        $entryEnd = min((int) $entry['end_min'], $gridEndMinutes);
                                        if ($entryEnd <= $entryStart) {
                                            continue;
                                        }
                                        $topPx = (int) round((($entryStart - $gridStartMinutes) / 60) * $pixelsPerHour);
                                        $heightPx = max(18, (int) round((($entryEnd - $entryStart) / 60) * $pixelsPerHour));
                                        $isWork = (string) ($entry['kind'] ?? '') === 'work';
                                        $isAppointmentEntry = (string) ($entry['kind'] ?? '') === 'appointment';
                                        $isBlockedEntry = (string) ($entry['kind'] ?? '') === 'break';
                                        $entryClass = (string) ($entry['class'] ?? 'sched-fill--blocked');
                                        ?>
                                        <?php
                                        $displayStartHour = intdiv((int) $entry['start_min'], 60) % 24;
                                        $displayStartMin = ((int) $entry['start_min']) % 60;
                                        $displayEndHour = intdiv((int) $entry['end_min'], 60) % 24;
                                        $displayEndMin = ((int) $entry['end_min']) % 60;
                                        $timeRangeLabel = sprintf(
                                            '%s - %s',
                                            formatTimeForUi(sprintf('%02d:%02d', $displayStartHour, $displayStartMin)),
                                            formatTimeForUi(sprintf('%02d:%02d', $displayEndHour, $displayEndMin))
                                        );
                                        $apptTimeRangeForGrid = sprintf(
                                            '%s – %s',
                                            formatTimeForUi(sprintf('%02d:%02d', $displayStartHour, $displayStartMin)),
                                            formatTimeForUi(sprintf('%02d:%02d', $displayEndHour, $displayEndMin))
                                        );
                                        $fullDentistName = (string) ($entry['dentist_name'] ?? 'Dr. Dentist');
                                        $shortDentistName = formatShiftDentistShortName($fullDentistName);
                                        $entryKind = (string) ($entry['kind'] ?? '');
                                        if ($entryKind === 'appointment' || $entryKind === 'appointment_past') {
                                            $zLayerClass = 'schedule-block-layer-appointment' . ($entryKind === 'appointment_past' ? '_past' : '');
                                        } elseif ($entryKind === 'break') {
                                            $zLayerClass = 'schedule-block-layer-blocked';
                                        } elseif ($entryKind === 'work') {
                                            $zLayerClass = 'schedule-block-layer-work';
                                        } else {
                                            $zLayerClass = 'schedule-block-layer-default';
                                        }
                                        $entryStyle = 'top: ' . $topPx . 'px; height: ' . $heightPx . 'px;';
                                        $isCompactBlockedBlock = $isBlockedEntry && $heightPx < 44;
                                        $apptBlockExtraClass = $isAppointmentEntry
                                            ? ' flex flex-col min-w-0 overflow-hidden'
                                            : '';
                                        $blockCompactClass = $isCompactBlockedBlock ? ' flex items-center justify-center' : $apptBlockExtraClass;
                                        $laneGapPercent = 1.5;
                                        $laneTotal = 1;
                                        if ($isWork) {
                                            $laneIndex = max(0, (int) ($entry['lane_index'] ?? 0));
                                            $laneTotal = max(1, (int) ($entry['lane_total'] ?? 1));
                                            $rect = scheduling_lane_rect_percent((float) $laneIndex, $laneTotal, $laneGapPercent);
                                            $entryStyle .= ' left: calc(6px + ' . number_format($rect['left'], 4, '.', '') . '%);';
                                            $entryStyle .= ' width: calc(' . number_format($rect['width'], 4, '.', '') . '% - 8px);';
                                        } elseif ($isAppointmentEntry) {
                                            $apptSubIndex = max(0, (int) ($entry['lane_index'] ?? 0));
                                            $laneTotal = max(1, (int) ($entry['lane_total'] ?? 1));
                                            $dayKeyForLanes = (string) ($weekDay['date_key'] ?? '');
                                            $dayMaxLanes = max(1, (int) ($dayMaxLaneCount[$dayKeyForLanes] ?? 1));
                                            if ($dayMaxLanes > 1) {
                                                $dentKey = scheduling_normalize_dentist_key((string) ($entry['dentist_name'] ?? ''));
                                                $dentistSlots = $workDentistLaneByDay[$dayKeyForLanes] ?? [];
                                                $dentistCol = array_key_exists($dentKey, $dentistSlots)
                                                    ? (int) $dentistSlots[$dentKey]
                                                    : 0;
                                                $dentistCol = max(0, min($dentistCol, $dayMaxLanes - 1));
                                                $rect = scheduling_appointment_lane_rect_percent($dentistCol, $dayMaxLanes, $apptSubIndex, $laneTotal, $laneGapPercent);
                                            } else {
                                                $rect = scheduling_lane_rect_percent((float) $apptSubIndex, $laneTotal, $laneGapPercent);
                                            }
                                            $entryStyle .= ' left: calc(6px + ' . number_format($rect['left'], 4, '.', '') . '%);';
                                            $entryStyle .= ' width: calc(' . number_format($rect['width'], 4, '.', '') . '% - 8px);';
                                        } else {
                                            $entryStyle .= ' left: 6px; right: 6px;';
                                        }
                                        $isSideBySideAppointment = $isAppointmentEntry && $laneTotal > 1;
                                        $apptResponsiveClass = $isSideBySideAppointment ? ' schedule-block--appt-overlap' : '';
                                        ?>
                                        <div class="schedule-block absolute rounded-xl border px-2 py-1.5 <?php echo htmlspecialchars($entryClass, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($zLayerClass, ENT_QUOTES, 'UTF-8'); ?><?php echo $blockCompactClass; ?><?php echo $apptResponsiveClass; ?>" style="<?php echo htmlspecialchars($entryStyle, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isWork ? ('data-shift-tooltip="1" data-shift-full-name="' . htmlspecialchars($fullDentistName, ENT_QUOTES, 'UTF-8') . '" data-shift-time="' . htmlspecialchars($timeRangeLabel, ENT_QUOTES, 'UTF-8') . '"') : ''; ?> <?php echo (!$isWork && $entryKind === 'appointment') ? ('data-appointment-tooltip="1" data-appt-patient-name="' . htmlspecialchars((string) ($entry['patient_name'] ?? 'Patient'), ENT_QUOTES, 'UTF-8') . '" data-appt-dentist-name="' . htmlspecialchars((string) ($entry['dentist_name'] ?? 'Dentist'), ENT_QUOTES, 'UTF-8') . '" data-appt-time="' . htmlspecialchars($timeRangeLabel, ENT_QUOTES, 'UTF-8') . '" data-appt-service-name="' . htmlspecialchars((string) ($entry['service_name'] ?? 'Treatment'), ENT_QUOTES, 'UTF-8') . '" data-appt-services-json="' . htmlspecialchars((string) ($entry['tooltip_services_json'] ?? '[]'), ENT_QUOTES, 'UTF-8') . '" data-appt-type="' . htmlspecialchars((string) ($entry['visit_type_label'] ?? 'Booking'), ENT_QUOTES, 'UTF-8') . '" data-appt-payment-status="' . htmlspecialchars((string) ($entry['payment_status_label'] ?? 'Unpaid'), ENT_QUOTES, 'UTF-8') . '" data-appt-status="' . htmlspecialchars((string) ($entry['appointment_status_label'] ?? 'Pending'), ENT_QUOTES, 'UTF-8') . '"') : ''; ?> <?php echo $isBlockedEntry ? ('data-block-tooltip="1" data-block-dentist-name="' . htmlspecialchars((string) ($entry['dentist_name'] ?? 'Dr. Dentist'), ENT_QUOTES, 'UTF-8') . '" data-block-time="' . htmlspecialchars($timeRangeLabel, ENT_QUOTES, 'UTF-8') . '" data-block-reason="' . htmlspecialchars((string) ($entry['block_reason'] ?? 'Break'), ENT_QUOTES, 'UTF-8') . '" data-block-notes="' . htmlspecialchars((string) ($entry['block_notes'] ?? ''), ENT_QUOTES, 'UTF-8') . '"') : ''; ?>>
                                            <?php if ($isWork): ?>
                                                <p class="text-[9px] font-black uppercase tracking-[0.12em] text-current/80 leading-tight">WORK SHIFT</p>
                                                <p class="mt-0.5 text-[10px] font-black text-current truncate"><?php echo htmlspecialchars($shortDentistName, ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="mt-1 text-[10px] font-semibold text-current/90 truncate"><?php echo htmlspecialchars($timeRangeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php elseif ($isAppointmentEntry): ?>
                                                <?php if ($isSideBySideAppointment): ?>
                                                    <div class="appt-grid-inner w-full">
                                                        <p class="appt-grid-line appt-grid-line--title">APPOINTMENT</p>
                                                        <?php if (!empty($entry['status_label'])): ?>
                                                            <p class="appt-grid-line appt-grid-line--status"><?php echo htmlspecialchars((string) $entry['status_label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                        <?php endif; ?>
                                                        <p class="appt-grid-line appt-grid-line--time"><?php echo htmlspecialchars($apptTimeRangeForGrid, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    </div>
                                                <?php else: ?>
                                                    <p class="text-[10px] font-black uppercase tracking-[0.12em] leading-tight">APPOINTMENT</p>
                                                    <?php if (!empty($entry['status_label'])): ?>
                                                        <p class="mt-0.5 text-[10px] font-semibold truncate"><?php echo htmlspecialchars((string) $entry['status_label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <?php endif; ?>
                                                    <p class="mt-1 text-[10px] font-semibold opacity-95 truncate"><?php echo htmlspecialchars($apptTimeRangeForGrid, ENT_QUOTES, 'UTF-8'); ?></p>
                                                <?php endif; ?>
                                            <?php elseif ($isCompactBlockedBlock): ?>
                                                <p class="text-[10px] font-black uppercase tracking-[0.12em] leading-tight text-center">BLOCKED</p>
                                            <?php else: ?>
                                                <p class="text-[10px] font-black uppercase tracking-[0.12em]"><?php echo htmlspecialchars((string) $entry['label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <?php if (!empty($entry['status_label'])): ?>
                                                    <p class="text-[10px] font-semibold"><?php echo htmlspecialchars((string) $entry['status_label'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <?php endif; ?>
                                                <p class="text-[10px] font-semibold opacity-95"><?php echo htmlspecialchars($timeRangeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</main>
<div id="chooseDentistModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/45"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="w-full max-w-5xl rounded-2xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Dentist Selection</p>
                    <h3 class="text-lg font-extrabold text-slate-900">Choose Dentist</h3>
                </div>
                <button id="closeChooseDentistModalBtn" type="button" class="w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            <div id="dentistListContainer" class="p-5 flex flex-wrap justify-center gap-4"></div>
            <div id="dentistListEmptyState" class="hidden px-5 pb-5 text-sm font-semibold text-slate-500"></div>
        </div>
    </div>
</div>
<div id="setShiftModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="staff-modal-panel schedule-popup-enter bg-white rounded-3xl shadow-[0_24px_64px_-12px_rgba(15,23,42,0.25)] border border-slate-100 w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col">
            <div class="shrink-0 px-6 sm:px-8 pt-7 pb-5 border-b border-slate-100 flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                    <span class="material-symbols-outlined text-2xl text-primary">schedule</span>
                </div>
                <div class="min-w-0 flex-1 pr-2">
                    <h3 class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight">Set Dentist Shift</h3>
                    <p class="text-sm text-slate-500 mt-1 leading-relaxed">Set recurring weekly hours for each day of the week</p>
                </div>
                <button id="closeSetShiftModalBtn" type="button" class="shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                    <span class="material-symbols-outlined text-[22px]">close</span>
                </button>
            </div>
            <div class="px-6 sm:px-8 pt-3 pb-5 space-y-6 overflow-y-auto">
                <form id="setShiftForm" class="space-y-6">
                    <section>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-primary text-[22px]">info</span>
                            <h4 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Basic Information</h4>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label for="setShiftDentistId" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">badge</span>
                                    Dentist
                                </label>
                                <select id="setShiftDentistId" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer">
                                    <?php foreach ($dentists as $dentist): ?>
                                        <?php
                                        $shiftDentistId = trim((string) ($dentist['dentist_id'] ?? ''));
                                        $shiftDentistUserId = trim((string) ($dentist['user_id'] ?? ''));
                                        $shiftDentistName = trim((string) ($dentist['full_name'] ?? 'Dentist'));
                                        ?>
                                        <option
                                            value="<?php echo htmlspecialchars($shiftDentistUserId, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-dentist-id="<?php echo htmlspecialchars($shiftDentistId, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-has-user-id="<?php echo $shiftDentistUserId !== '' ? '1' : '0'; ?>"
                                            <?php echo (($selectedDentistId !== '' && $selectedDentistId === $shiftDentistId) || ($selectedDentistUserId !== '' && $selectedDentistUserId === $shiftDentistUserId)) ? 'selected' : ''; ?>
                                            <?php echo $shiftDentistUserId === '' ? 'disabled' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($shiftDentistName . ($shiftDentistUserId === '' ? ' (No linked account)' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mb-3 mt-6">
                            <span class="material-symbols-outlined text-primary text-[22px]">calendar_month</span>
                            <h4 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Weekly schedule</h4>
                        </div>
                        <p class="text-xs text-slate-500 mb-4 leading-relaxed">Leave both times empty on a day off. Shift times are validated against clinic hours for each weekday in the week you are viewing. Days when the clinic is closed or has no hours cannot be edited; saving still applies shifts for the other days.</p>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/40 divide-y divide-slate-200/80 overflow-hidden">
                            <?php
                            $setShiftWeekRows = [
                                ['Sunday', 'sunday'],
                                ['Monday', 'monday'],
                                ['Tuesday', 'tuesday'],
                                ['Wednesday', 'wednesday'],
                                ['Thursday', 'thursday'],
                                ['Friday', 'friday'],
                                ['Saturday', 'saturday'],
                            ];
                            foreach ($setShiftWeekRows as $setShiftWeekRow) {
                                $setShiftDayLabel = $setShiftWeekRow[0];
                                $setShiftDaySlug = $setShiftWeekRow[1];
                                $setShiftStartId = 'setShiftWeek' . ucfirst($setShiftDaySlug) . 'Start';
                                $setShiftEndId = 'setShiftWeek' . ucfirst($setShiftDaySlug) . 'End';
                                $setShiftLoaderId = 'setShiftWeek' . ucfirst($setShiftDaySlug) . 'Loader';
                                $setShiftStatusId = 'setShiftWeek' . ucfirst($setShiftDaySlug) . 'Status';
                                ?>
                            <div class="px-4 py-3 sm:px-5 sm:py-3.5 bg-white/80">
                                <div class="flex flex-col gap-3 sm:gap-4 lg:flex-row lg:items-center lg:gap-5">
                                    <div class="w-full lg:w-32 shrink-0 flex items-center min-h-[2.5rem] lg:min-h-0">
                                        <p class="text-sm font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($setShiftDayLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2.5 sm:gap-4 flex-1 min-w-0 max-w-full items-start lg:min-w-0">
                                        <div class="min-w-0 flex flex-col">
                                            <label for="<?php echo htmlspecialchars($setShiftStartId, ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-1 text-xs sm:text-sm font-semibold text-slate-800 mb-1.5 sm:mb-2 min-h-[1.25rem] min-w-0">
                                                <span class="material-symbols-outlined text-[16px] sm:text-[18px] text-slate-500 shrink-0">timer</span>
                                                <span class="min-w-0 leading-tight">Start Time</span>
                                            </label>
                                            <input id="<?php echo htmlspecialchars($setShiftStartId, ENT_QUOTES, 'UTF-8'); ?>" name="week_shift_<?php echo htmlspecialchars($setShiftDaySlug, ENT_QUOTES, 'UTF-8'); ?>_start" type="time" step="60" value="" data-set-shift-input-name="week_shift_<?php echo htmlspecialchars($setShiftDaySlug, ENT_QUOTES, 'UTF-8'); ?>_start" class="set-shift-time-input w-full h-12 min-h-[3rem] box-border px-2.5 sm:px-4 py-0 rounded-xl border border-slate-200 bg-white text-slate-900 text-[13px] sm:text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all"/>
                                        </div>
                                        <div class="min-w-0 flex flex-col">
                                            <label for="<?php echo htmlspecialchars($setShiftEndId, ENT_QUOTES, 'UTF-8'); ?>" class="flex items-center gap-1 text-xs sm:text-sm font-semibold text-slate-800 mb-1.5 sm:mb-2 min-h-[1.25rem] min-w-0">
                                                <span class="material-symbols-outlined text-[16px] sm:text-[18px] text-slate-500 shrink-0">timer_off</span>
                                                <span class="min-w-0 leading-tight">End Time</span>
                                            </label>
                                            <input id="<?php echo htmlspecialchars($setShiftEndId, ENT_QUOTES, 'UTF-8'); ?>" name="week_shift_<?php echo htmlspecialchars($setShiftDaySlug, ENT_QUOTES, 'UTF-8'); ?>_end" type="time" step="60" value="" data-set-shift-input-name="week_shift_<?php echo htmlspecialchars($setShiftDaySlug, ENT_QUOTES, 'UTF-8'); ?>_end" class="set-shift-time-input w-full h-12 min-h-[3rem] box-border px-2.5 sm:px-4 py-0 rounded-xl border border-slate-200 bg-white text-slate-900 text-[13px] sm:text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all"/>
                                        </div>
                                    </div>
                                    <div class="flex w-full shrink-0 items-center justify-end gap-2 sm:gap-2.5 pt-2 sm:pt-3 lg:pt-0 border-t border-slate-100/80 lg:border-t-0 lg:pl-1 pr-4 sm:pr-6 lg:pr-4 lg:w-[14rem] lg:flex-none">
                                        <span
                                            id="<?php echo htmlspecialchars($setShiftLoaderId, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="set-shift-day-state relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-slate-200/80 bg-slate-50/90 shadow-sm"
                                            aria-hidden="true"
                                        >
                                            <span class="set-shift-day-layer set-shift-day-loading absolute inset-0 flex items-center justify-center hidden">
                                                <span class="material-symbols-outlined set-shift-day-spinner text-[20px] leading-none text-slate-500 animate-spin">progress_activity</span>
                                            </span>
                                            <span class="set-shift-day-layer set-shift-day-success absolute inset-0 flex items-center justify-center hidden">
                                                <span class="material-symbols-outlined text-[22px] leading-none text-emerald-600" aria-hidden="true">check_circle</span>
                                            </span>
                                            <span class="set-shift-day-layer set-shift-day-error absolute inset-0 flex items-center justify-center hidden">
                                                <span class="material-symbols-outlined text-[22px] leading-none text-rose-600" aria-hidden="true">cancel</span>
                                            </span>
                                        </span>
                                        <span
                                            id="<?php echo htmlspecialchars($setShiftStatusId, ENT_QUOTES, 'UTF-8'); ?>"
                                            class="set-shift-day-status text-[11px] sm:text-xs font-semibold text-right leading-snug min-w-0 max-w-[10rem] sm:max-w-[13rem] text-slate-400"
                                            aria-live="polite"
                                        ></span>
                                    </div>
                                </div>
                            </div>
                                <?php
                            }
                            ?>
                        </div>
                        <div class="mt-6">
                            <label for="setShiftNotes" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                <span class="material-symbols-outlined text-[18px] text-slate-500">description</span>
                                Notes
                            </label>
                            <textarea id="setShiftNotes" name="notes" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all resize-y min-h-[100px]" placeholder="Optional text"></textarea>
                        </div>
                    </section>
                    <div class="border-t border-slate-100 bg-slate-50/50 px-0 py-4 flex flex-wrap items-center justify-end gap-3">
                        <button id="cancelSetShiftBtn" type="button" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:bg-slate-50 transition-all shadow-sm">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                            Cancel
                        </button>
                        <button id="saveSetShiftBtn" type="button" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-primary hover:bg-primary/92 text-white text-sm font-bold shadow-lg shadow-primary/25 transition-all">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            Save Shift
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div id="blockTimeModal" class="hidden fixed inset-0 z-[70]">
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-[2px]"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="staff-modal-panel schedule-popup-enter bg-white rounded-3xl shadow-[0_24px_64px_-12px_rgba(15,23,42,0.25)] border border-slate-100 w-full max-w-2xl max-h-[92vh] overflow-hidden flex flex-col">
            <div class="shrink-0 px-6 sm:px-8 pt-7 pb-5 border-b border-slate-100 flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10 ring-1 ring-primary/15">
                    <span class="material-symbols-outlined text-2xl text-primary">block</span>
                </div>
                <div class="min-w-0 flex-1 pr-2">
                    <h3 class="text-xl sm:text-2xl font-extrabold font-headline text-on-background tracking-tight">Block Time</h3>
                    <p class="text-sm text-slate-500 mt-1 leading-relaxed">Reserve unavailable hours for dentist schedules</p>
                </div>
                <button id="closeBlockTimeModalBtn" type="button" class="shrink-0 p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors" aria-label="Close">
                    <span class="material-symbols-outlined text-[22px]">close</span>
                </button>
            </div>
            <div class="px-6 sm:px-8 pt-3 pb-5 space-y-6 overflow-y-auto">
                <form id="blockTimeForm" class="space-y-6">
                    <section>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-primary text-[22px]">info</span>
                            <h4 class="text-sm font-extrabold text-slate-800 uppercase tracking-wide">Basic Information</h4>
                        </div>
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label for="blockTimeDentistId" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">badge</span>
                                    Dentist
                                </label>
                                <select id="blockTimeDentistId" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer">
                                    <?php foreach ($dentists as $dentist): ?>
                                        <?php
                                        $blockDentistId = trim((string) ($dentist['dentist_id'] ?? ''));
                                        $blockDentistUserId = trim((string) ($dentist['user_id'] ?? ''));
                                        $blockDentistName = trim((string) ($dentist['full_name'] ?? 'Dentist'));
                                        ?>
                                        <option
                                            value="<?php echo htmlspecialchars($blockDentistUserId, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-dentist-id="<?php echo htmlspecialchars($blockDentistId, ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php echo (($selectedDentistId !== '' && $selectedDentistId === $blockDentistId) || ($selectedDentistUserId !== '' && $selectedDentistUserId === $blockDentistUserId)) ? 'selected' : ''; ?>
                                            <?php echo $blockDentistUserId === '' ? 'disabled' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($blockDentistName . ($blockDentistUserId === '' ? ' (No linked account)' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="blockTimeDate" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">event</span>
                                    Date
                                </label>
                                <div class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/15 transition-all inline-flex items-center gap-2.5">
                                    <input id="blockTimeDate" type="date" min="<?php echo htmlspecialchars($todayDateOnly, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($setShiftDefaultDate, ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-transparent p-0 border-0 text-[15px] font-semibold text-slate-900 focus:ring-0"/>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 sm:p-5 space-y-3">
                            <p id="blockTimeShiftHoursLabel" class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Selected Dentist Work Shift for <?php echo htmlspecialchars((new DateTimeImmutable($setShiftDefaultDate, $tz))->format('F j, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                                <div>
                                    <label for="blockTimeShiftStartReadonly" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                        <span class="material-symbols-outlined text-[18px] text-slate-500">schedule</span>
                                        Shift Start Time (Read Only)
                                    </label>
                                    <input id="blockTimeShiftStartReadonly" type="text" value="-" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-700 text-[15px] font-semibold cursor-not-allowed"/>
                                </div>
                                <div>
                                    <label for="blockTimeShiftEndReadonly" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                        <span class="material-symbols-outlined text-[18px] text-slate-500">schedule</span>
                                        Shift End Time (Read Only)
                                    </label>
                                    <input id="blockTimeShiftEndReadonly" type="text" value="-" readonly class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-100 text-slate-700 text-[15px] font-semibold cursor-not-allowed"/>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label for="blockTimeStartTime" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">timer</span>
                                    Start Time
                                </label>
                                <input id="blockTimeStartTime" type="time" step="60" value="12:00" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all"/>
                            </div>
                            <div>
                                <label for="blockTimeEndTime" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                    <span class="material-symbols-outlined text-[18px] text-slate-500">timer_off</span>
                                    End Time
                                </label>
                                <input id="blockTimeEndTime" type="time" step="60" value="13:00" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all"/>
                            </div>
                        </div>
                        <div>
                            <label for="blockTimeReason" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                <span class="material-symbols-outlined text-[18px] text-slate-500">label</span>
                                Reason
                            </label>
                            <select id="blockTimeReason" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all cursor-pointer">
                                <option value="Break" selected>Break</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Personal">Personal</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="blockTimeNotes" class="flex items-center gap-1.5 text-sm font-semibold text-slate-800 mb-2">
                                <span class="material-symbols-outlined text-[18px] text-slate-500">description</span>
                                Notes
                            </label>
                            <textarea id="blockTimeNotes" rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-white text-slate-900 placeholder:text-slate-400 text-[15px] shadow-sm focus:border-primary focus:ring-2 focus:ring-primary/15 transition-all resize-y min-h-[100px]" placeholder="Optional text"></textarea>
                        </div>
                        </div>
                    </section>
                    <div class="border-t border-slate-100 bg-slate-50/50 px-0 py-4 flex flex-wrap items-center justify-end gap-3">
                        <button id="cancelBlockTimeBtn" type="button" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 text-sm font-bold hover:bg-slate-50 transition-all shadow-sm">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                            Cancel
                        </button>
                        <button id="saveBlockTimeBtn" type="button" class="inline-flex items-center justify-center gap-2 px-6 py-2.5 rounded-xl bg-primary hover:bg-primary/92 text-white text-sm font-bold shadow-lg shadow-primary/25 transition-all">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            Save Block Time
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div id="pageAlertModal" class="hidden fixed inset-0 z-[80]">
    <div class="absolute inset-0 bg-slate-900/50"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="alert-popup-enter w-full max-w-md rounded-3xl border border-primary/20 bg-white shadow-2xl overflow-hidden">
            <div class="px-6 py-5 flex items-start gap-4">
                <span class="inline-flex w-11 h-11 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-[22px]">info</span>
                </span>
                <div class="min-w-0">
                    <h3 class="font-headline text-xl font-extrabold text-slate-900">Notice</h3>
                    <p id="pageAlertMessage" class="mt-1 text-sm font-semibold text-slate-600 leading-relaxed"></p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50/70 flex justify-end">
                <button id="pageAlertOkBtn" type="button" class="inline-flex items-center justify-center min-w-[7rem] px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>
<div id="shiftInfoTooltip" class="shift-tooltip-card hidden" role="tooltip" aria-hidden="true">
    <div class="px-3.5 py-3">
        <div class="flex items-start gap-2.5">
            <span class="inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                <span class="material-symbols-outlined text-[17px]">person</span>
            </span>
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">WORK SHIFT</p>
                <p id="shiftTooltipDentistName" class="text-sm font-extrabold text-slate-800 leading-snug break-words mt-0.5"></p>
            </div>
        </div>
        <div class="mt-2.5 flex items-start gap-2.5">
            <span class="inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <span class="material-symbols-outlined text-[17px]">schedule</span>
            </span>
            <div class="min-w-0">
                <p id="shiftTooltipTimeRange" class="text-sm font-semibold text-slate-600 leading-snug break-words"></p>
            </div>
        </div>
    </div>
</div>
<div id="appointmentInfoTooltip" class="appointment-tooltip-card hidden" role="tooltip" aria-hidden="true">
    <div class="px-3.5 py-3.5 space-y-2.5">
        <div class="flex items-start gap-2.5">
            <span class="inline-flex w-7 h-7 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 text-[15px] font-black">👤</span>
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Patient</p>
                <p id="apptTooltipPatientName" class="text-sm font-extrabold text-slate-800 leading-snug break-words mt-0.5"></p>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-2">
            <p id="apptTooltipDentistName" class="text-sm font-semibold text-slate-700 break-words">🦷</p>
            <p id="apptTooltipTimeRange" class="text-sm font-semibold text-slate-700 break-words">🕒</p>
        </div>
        <div class="h-px bg-slate-200"></div>
        <div class="space-y-1.5">
            <p class="text-[10px] font-black uppercase tracking-[0.15em] text-slate-500">Services</p>
            <ul id="apptTooltipServiceList" class="list-disc pl-5 m-0 mt-1 space-y-1 text-sm font-semibold text-slate-700 leading-snug break-words"></ul>
            <p id="apptTooltipType" class="text-sm font-semibold text-slate-700 break-words">📍</p>
        </div>
        <div class="space-y-1.5 pt-1">
            <p id="apptTooltipPaymentStatus" class="text-sm font-semibold text-slate-700 break-words">💳</p>
            <p id="apptTooltipAppointmentStatus" class="text-sm font-semibold text-slate-700 break-words">📊</p>
        </div>
    </div>
</div>
<div id="blockInfoTooltip" class="block-tooltip-card hidden" role="tooltip" aria-hidden="true">
    <div class="px-3.5 py-3.5 space-y-2.5">
        <p class="text-sm font-extrabold text-slate-800 leading-tight">🚫 Blocked Time</p>
        <div class="grid grid-cols-1 gap-1.5">
            <p id="blockTooltipDentistName" class="text-sm font-semibold text-slate-700 break-words">👤 Dentist: -</p>
            <p id="blockTooltipTimeRange" class="text-sm font-semibold text-slate-700 break-words">🕒 Time: -</p>
        </div>
        <div class="h-px bg-slate-200"></div>
        <div class="space-y-1.5">
            <p id="blockTooltipReason" class="text-sm font-semibold text-slate-700 break-words">📌 Reason: -</p>
            <p id="blockTooltipNotes" class="hidden text-sm font-semibold text-slate-700 break-words">📝 Notes: -</p>
        </div>
    </div>
</div>
<script>
    (function () {
        const openSetShiftModalBtn = document.getElementById('openSetShiftModalBtn');
        const openBlockTimeModalBtn = document.getElementById('openBlockTimeModalBtn');
        const setShiftModal = document.getElementById('setShiftModal');
        const blockTimeModal = document.getElementById('blockTimeModal');
        const closeSetShiftModalBtn = document.getElementById('closeSetShiftModalBtn');
        const closeBlockTimeModalBtn = document.getElementById('closeBlockTimeModalBtn');
        const cancelSetShiftBtn = document.getElementById('cancelSetShiftBtn');
        const cancelBlockTimeBtn = document.getElementById('cancelBlockTimeBtn');
        const saveSetShiftBtn = document.getElementById('saveSetShiftBtn');
        const saveBlockTimeBtn = document.getElementById('saveBlockTimeBtn');
        const blockTimeDentistId = document.getElementById('blockTimeDentistId');
        const blockTimeDate = document.getElementById('blockTimeDate');
        const blockTimeStartTime = document.getElementById('blockTimeStartTime');
        const blockTimeEndTime = document.getElementById('blockTimeEndTime');
        const blockTimeReason = document.getElementById('blockTimeReason');
        const blockTimeNotes = document.getElementById('blockTimeNotes');
        const blockTimeShiftHoursLabel = document.getElementById('blockTimeShiftHoursLabel');
        const blockTimeShiftStartReadonly = document.getElementById('blockTimeShiftStartReadonly');
        const blockTimeShiftEndReadonly = document.getElementById('blockTimeShiftEndReadonly');
        const setShiftDentistId = document.getElementById('setShiftDentistId');
        const setShiftForm = document.getElementById('setShiftForm');
        const setShiftNotes = document.getElementById('setShiftNotes');
        const chooseDentistBtn = document.getElementById('chooseDentistBtn');
        const clearDentistBtn = document.getElementById('clearDentistBtn');
        const selectedDentistLabel = document.getElementById('selectedDentistLabel');
        const selectedDentistUserIdInput = document.getElementById('selectedDentistUserId');
        const selectedDentistIdInput = document.getElementById('selectedDentistId');
        const weekReferenceDateInput = document.getElementById('weekReferenceDateInput');
        const showCompletedToggle = document.getElementById('showCompletedToggle');
        const chooseDentistModal = document.getElementById('chooseDentistModal');
        const closeChooseDentistModalBtn = document.getElementById('closeChooseDentistModalBtn');
        const dentistListContainer = document.getElementById('dentistListContainer');
        const dentistListEmptyState = document.getElementById('dentistListEmptyState');
        const pageAlertModal = document.getElementById('pageAlertModal');
        const pageAlertMessage = document.getElementById('pageAlertMessage');
        const pageAlertOkBtn = document.getElementById('pageAlertOkBtn');
        const shiftInfoTooltip = document.getElementById('shiftInfoTooltip');
        const shiftTooltipDentistName = document.getElementById('shiftTooltipDentistName');
        const shiftTooltipTimeRange = document.getElementById('shiftTooltipTimeRange');
        const appointmentInfoTooltip = document.getElementById('appointmentInfoTooltip');
        const apptTooltipPatientName = document.getElementById('apptTooltipPatientName');
        const apptTooltipDentistName = document.getElementById('apptTooltipDentistName');
        const apptTooltipTimeRange = document.getElementById('apptTooltipTimeRange');
        const apptTooltipServiceList = document.getElementById('apptTooltipServiceList');
        const apptTooltipType = document.getElementById('apptTooltipType');
        const apptTooltipPaymentStatus = document.getElementById('apptTooltipPaymentStatus');
        const apptTooltipAppointmentStatus = document.getElementById('apptTooltipAppointmentStatus');
        const blockInfoTooltip = document.getElementById('blockInfoTooltip');
        const blockTooltipDentistName = document.getElementById('blockTooltipDentistName');
        const blockTooltipTimeRange = document.getElementById('blockTooltipTimeRange');
        const blockTooltipReason = document.getElementById('blockTooltipReason');
        const blockTooltipNotes = document.getElementById('blockTooltipNotes');
        const scheduleFilterForm = document.getElementById('scheduleFilterForm');
        const dentistsSeedData = <?php echo json_encode($dentistsSeedData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const stockDentistImage = 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&w=300&q=60';
        const clinicAssetBaseUrl = <?php echo json_encode(rtrim(BASE_URL, '/') . '/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const todayDateOnly = <?php echo json_encode($todayDateOnly, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const clinicHoursSnapshotByDayName = <?php echo json_encode($clinicHoursSnapshotByDayName ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const scheduleWeekAnchorDefault = <?php echo json_encode($selectedDate->format('Y-m-d'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const SET_SHIFT_CLINIC_VALIDATE_MS = 220;
        const setShiftRowValidateTimers = {};
        const setShiftWeekFieldDefs = [
            { dayName: 'Sunday', startId: 'setShiftWeekSundayStart', endId: 'setShiftWeekSundayEnd', loaderId: 'setShiftWeekSundayLoader', statusId: 'setShiftWeekSundayStatus' },
            { dayName: 'Monday', startId: 'setShiftWeekMondayStart', endId: 'setShiftWeekMondayEnd', loaderId: 'setShiftWeekMondayLoader', statusId: 'setShiftWeekMondayStatus' },
            { dayName: 'Tuesday', startId: 'setShiftWeekTuesdayStart', endId: 'setShiftWeekTuesdayEnd', loaderId: 'setShiftWeekTuesdayLoader', statusId: 'setShiftWeekTuesdayStatus' },
            { dayName: 'Wednesday', startId: 'setShiftWeekWednesdayStart', endId: 'setShiftWeekWednesdayEnd', loaderId: 'setShiftWeekWednesdayLoader', statusId: 'setShiftWeekWednesdayStatus' },
            { dayName: 'Thursday', startId: 'setShiftWeekThursdayStart', endId: 'setShiftWeekThursdayEnd', loaderId: 'setShiftWeekThursdayLoader', statusId: 'setShiftWeekThursdayStatus' },
            { dayName: 'Friday', startId: 'setShiftWeekFridayStart', endId: 'setShiftWeekFridayEnd', loaderId: 'setShiftWeekFridayLoader', statusId: 'setShiftWeekFridayStatus' },
            { dayName: 'Saturday', startId: 'setShiftWeekSaturdayStart', endId: 'setShiftWeekSaturdayEnd', loaderId: 'setShiftWeekSaturdayLoader', statusId: 'setShiftWeekSaturdayStatus' }
        ];
        const SET_SHIFT_CLOSED_PLACEHOLDER = '_ _ : _ _';

        function isSetShiftRowClinicUnavailable(row) {
            const snap = clinicHoursSnapshotByDayName[row.dayName];
            if (!snap) {
                return true;
            }
            if (snap.is_closed) {
                return true;
            }
            const openRaw = String(snap.open_time_raw || '').trim();
            const closeRaw = String(snap.close_time_raw || '').trim();
            return openRaw === '' || closeRaw === '';
        }

        function syncSetShiftRowClinicLock(row) {
            const startEl = document.getElementById(row.startId);
            const endEl = document.getElementById(row.endId);
            if (!startEl || !endEl) {
                return;
            }
            const startName = String(startEl.getAttribute('data-set-shift-input-name') || '').trim();
            const endName = String(endEl.getAttribute('data-set-shift-input-name') || '').trim();
            if (isSetShiftRowClinicUnavailable(row)) {
                startEl.value = '';
                endEl.value = '';
                startEl.disabled = true;
                endEl.disabled = true;
                startEl.removeAttribute('name');
                endEl.removeAttribute('name');
                startEl.setAttribute('type', 'text');
                endEl.setAttribute('type', 'text');
                startEl.setAttribute('placeholder', SET_SHIFT_CLOSED_PLACEHOLDER);
                endEl.setAttribute('placeholder', SET_SHIFT_CLOSED_PLACEHOLDER);
                startEl.setAttribute('autocomplete', 'off');
                endEl.setAttribute('autocomplete', 'off');
                startEl.setAttribute('inputmode', 'none');
                endEl.setAttribute('inputmode', 'none');
                startEl.classList.add('set-shift-time-input--clinic-closed');
                endEl.classList.add('set-shift-time-input--clinic-closed');
                const statusEl = document.getElementById(row.statusId);
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(statusEl, 'Clinic closed', 'closed');
            } else {
                startEl.disabled = false;
                endEl.disabled = false;
                startEl.setAttribute('type', 'time');
                endEl.setAttribute('type', 'time');
                startEl.setAttribute('step', '60');
                endEl.setAttribute('step', '60');
                startEl.removeAttribute('placeholder');
                endEl.removeAttribute('placeholder');
                startEl.removeAttribute('autocomplete');
                endEl.removeAttribute('autocomplete');
                startEl.removeAttribute('inputmode');
                endEl.removeAttribute('inputmode');
                startEl.classList.remove('set-shift-time-input--clinic-closed');
                endEl.classList.remove('set-shift-time-input--clinic-closed');
                if (startName) {
                    startEl.setAttribute('name', startName);
                }
                if (endName) {
                    endEl.setAttribute('name', endName);
                }
            }
        }

        function syncSetShiftRowClinicLockForAllRows() {
            setShiftWeekFieldDefs.forEach(syncSetShiftRowClinicLock);
        }
        let selectedWorkShiftForBlockTime = {
            hasRecord: false,
            startTimeRaw: '',
            endTimeRaw: ''
        };

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getInitials(fullName) {
            const parts = String(fullName || '').trim().split(/\s+/).filter(Boolean);
            if (!parts.length) return 'DR';
            if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
            return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
        }

        function resolveDentistProfileImageUrl(raw) {
            const path = String(raw || '').trim();
            if (!path) return stockDentistImage;
            if (/^https?:\/\//i.test(path)) return path;
            return clinicAssetBaseUrl.replace(/\/?$/, '/') + path.replace(/^\/+/, '');
        }

        function showPageAlert(message) {
            if (!pageAlertMessage || !pageAlertModal) {
                return;
            }

            pageAlertMessage.textContent = String(message != null ? message : '');
            pageAlertModal.classList.remove('hidden');
            syncModalBodyScrollLock();
            if (pageAlertOkBtn) {
                pageAlertOkBtn.focus();
            }
        }

        function closePageAlert() {
            if (!pageAlertModal) return;
            pageAlertModal.classList.add('hidden');
            syncModalBodyScrollLock();
        }

        function hideShiftTooltip() {
            if (!shiftInfoTooltip) return;
            shiftInfoTooltip.classList.remove('is-visible');
            shiftInfoTooltip.classList.add('hidden');
            shiftInfoTooltip.setAttribute('aria-hidden', 'true');
        }

        function hideAppointmentTooltip() {
            if (!appointmentInfoTooltip) return;
            appointmentInfoTooltip.classList.remove('is-visible');
            appointmentInfoTooltip.classList.add('hidden');
            appointmentInfoTooltip.setAttribute('aria-hidden', 'true');
        }

        function hideBlockTooltip() {
            if (!blockInfoTooltip) return;
            blockInfoTooltip.classList.remove('is-visible');
            blockInfoTooltip.classList.add('hidden');
            blockInfoTooltip.setAttribute('aria-hidden', 'true');
        }

        function positionShiftTooltip(anchorRect, pointerX, pointerY) {
            if (!shiftInfoTooltip) return;

            const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            const tooltipWidth = shiftInfoTooltip.offsetWidth || 280;
            const tooltipHeight = shiftInfoTooltip.offsetHeight || 92;
            const gap = 12;

            let left = pointerX + gap;
            if (left + tooltipWidth > viewportWidth - 8) {
                left = Math.max(8, pointerX - tooltipWidth - gap);
            }

            let top = (anchorRect.top + anchorRect.bottom - tooltipHeight) / 2;
            if (!Number.isFinite(top)) {
                top = pointerY - (tooltipHeight / 2);
            }
            if (top + tooltipHeight > viewportHeight - 8) {
                top = viewportHeight - tooltipHeight - 8;
            }
            if (top < 8) {
                top = 8;
            }

            shiftInfoTooltip.style.left = Math.round(left) + 'px';
            shiftInfoTooltip.style.top = Math.round(top) + 'px';
        }

        function showShiftTooltip(target, event) {
            if (!shiftInfoTooltip || !shiftTooltipDentistName || !shiftTooltipTimeRange || !target) return;
            const fullName = String(target.getAttribute('data-shift-full-name') || '').trim();
            const timeRange = String(target.getAttribute('data-shift-time') || '').trim();
            if (!fullName || !timeRange) return;

            hideAppointmentTooltip();
            hideBlockTooltip();
            shiftTooltipDentistName.textContent = fullName;
            shiftTooltipTimeRange.textContent = timeRange;
            shiftInfoTooltip.classList.remove('hidden');
            shiftInfoTooltip.setAttribute('aria-hidden', 'false');
            positionShiftTooltip(target.getBoundingClientRect(), event.clientX, event.clientY);
            shiftInfoTooltip.classList.add('is-visible');
        }

        function positionAppointmentTooltip(anchorRect, pointerX, pointerY) {
            if (!appointmentInfoTooltip) return;

            const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            const tooltipWidth = appointmentInfoTooltip.offsetWidth || 320;
            const tooltipHeight = appointmentInfoTooltip.offsetHeight || 210;
            const gap = 12;

            let left = pointerX + gap;
            if (left + tooltipWidth > viewportWidth - 8) {
                left = Math.max(8, pointerX - tooltipWidth - gap);
            }

            let top = (anchorRect.top + anchorRect.bottom - tooltipHeight) / 2;
            if (!Number.isFinite(top)) {
                top = pointerY - (tooltipHeight / 2);
            }
            if (top + tooltipHeight > viewportHeight - 8) {
                top = viewportHeight - tooltipHeight - 8;
            }
            if (top < 8) {
                top = 8;
            }

            appointmentInfoTooltip.style.left = Math.round(left) + 'px';
            appointmentInfoTooltip.style.top = Math.round(top) + 'px';
        }

        function populateAppointmentTooltipServiceList(names) {
            if (!apptTooltipServiceList) return;
            while (apptTooltipServiceList.firstChild) {
                apptTooltipServiceList.removeChild(apptTooltipServiceList.firstChild);
            }
            let list = Array.isArray(names) ? names.slice() : [];
            list = list.map(function (n) {
                return String(n || '').trim();
            }).filter(Boolean);
            if (!list.length) {
                list = ['Treatment'];
            }
            list.forEach(function (svc) {
                const li = document.createElement('li');
                li.textContent = svc;
                apptTooltipServiceList.appendChild(li);
            });
        }

        function showAppointmentTooltip(target, event) {
            if (!appointmentInfoTooltip || !target) return;
            if (!apptTooltipPatientName || !apptTooltipDentistName || !apptTooltipTimeRange || !apptTooltipServiceList || !apptTooltipType || !apptTooltipPaymentStatus || !apptTooltipAppointmentStatus) return;

            const patientName = String(target.getAttribute('data-appt-patient-name') || '').trim();
            const dentistName = String(target.getAttribute('data-appt-dentist-name') || '').trim();
            const timeRange = String(target.getAttribute('data-appt-time') || '').trim();
            const serviceName = String(target.getAttribute('data-appt-service-name') || '').trim();
            const rawServicesAttr = target.getAttribute('data-appt-services-json');
            const typeLabel = String(target.getAttribute('data-appt-type') || '').trim();
            const paymentStatus = String(target.getAttribute('data-appt-payment-status') || '').trim();
            const appointmentStatus = String(target.getAttribute('data-appt-status') || '').trim();
            if (!patientName || !dentistName || !timeRange) return;

            hideShiftTooltip();
            hideBlockTooltip();
            apptTooltipPatientName.textContent = patientName;
            apptTooltipDentistName.textContent = '🦷 Dentist: ' + (dentistName || '-');
            apptTooltipTimeRange.textContent = '🕒 Time: ' + (timeRange || '-');
            let parsedServices = null;
            if (typeof rawServicesAttr === 'string' && rawServicesAttr.trim() !== '') {
                try {
                    parsedServices = JSON.parse(rawServicesAttr);
                } catch (ignored) {
                    parsedServices = null;
                }
            }
            if (Array.isArray(parsedServices) && parsedServices.length) {
                populateAppointmentTooltipServiceList(parsedServices);
            } else if (serviceName) {
                populateAppointmentTooltipServiceList([serviceName]);
            } else {
                populateAppointmentTooltipServiceList([]);
            }
            apptTooltipType.textContent = '📍 Type: ' + (typeLabel || '-');
            apptTooltipPaymentStatus.textContent = '💳 Status: ' + (paymentStatus || '-');
            apptTooltipAppointmentStatus.textContent = '📊 Appointment: ' + (appointmentStatus || '-');
            appointmentInfoTooltip.classList.remove('hidden');
            appointmentInfoTooltip.setAttribute('aria-hidden', 'false');
            positionAppointmentTooltip(target.getBoundingClientRect(), event.clientX, event.clientY);
            appointmentInfoTooltip.classList.add('is-visible');
        }

        function positionBlockTooltip(anchorRect, pointerX, pointerY) {
            if (!blockInfoTooltip) return;

            const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
            const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
            const tooltipWidth = blockInfoTooltip.offsetWidth || 320;
            const tooltipHeight = blockInfoTooltip.offsetHeight || 170;
            const gap = 12;

            let left = pointerX + gap;
            if (left + tooltipWidth > viewportWidth - 8) {
                left = Math.max(8, pointerX - tooltipWidth - gap);
            }

            let top = (anchorRect.top + anchorRect.bottom - tooltipHeight) / 2;
            if (!Number.isFinite(top)) {
                top = pointerY - (tooltipHeight / 2);
            }
            if (top + tooltipHeight > viewportHeight - 8) {
                top = viewportHeight - tooltipHeight - 8;
            }
            if (top < 8) {
                top = 8;
            }

            blockInfoTooltip.style.left = Math.round(left) + 'px';
            blockInfoTooltip.style.top = Math.round(top) + 'px';
        }

        function showBlockTooltip(target, event) {
            if (!blockInfoTooltip || !target) return;
            if (!blockTooltipDentistName || !blockTooltipTimeRange || !blockTooltipReason || !blockTooltipNotes) return;

            const dentistName = String(target.getAttribute('data-block-dentist-name') || '').trim();
            const timeRange = String(target.getAttribute('data-block-time') || '').trim();
            const reason = String(target.getAttribute('data-block-reason') || '').trim();
            const notes = String(target.getAttribute('data-block-notes') || '').trim();
            if (!dentistName || !timeRange) return;

            hideShiftTooltip();
            hideAppointmentTooltip();
            blockTooltipDentistName.textContent = '👤 Dentist: ' + (dentistName || '-');
            blockTooltipTimeRange.textContent = '🕒 Time: ' + (timeRange || '-');
            blockTooltipReason.textContent = '📌 Reason: ' + (reason || 'Break');
            if (notes) {
                blockTooltipNotes.textContent = '📝 Notes: ' + notes;
                blockTooltipNotes.classList.remove('hidden');
            } else {
                blockTooltipNotes.textContent = '📝 Notes: -';
                blockTooltipNotes.classList.add('hidden');
            }
            blockInfoTooltip.classList.remove('hidden');
            blockInfoTooltip.setAttribute('aria-hidden', 'false');
            positionBlockTooltip(target.getBoundingClientRect(), event.clientX, event.clientY);
            blockInfoTooltip.classList.add('is-visible');
        }

        function syncModalBodyScrollLock() {
            const hasOpenModal = (chooseDentistModal && !chooseDentistModal.classList.contains('hidden'))
                || (setShiftModal && !setShiftModal.classList.contains('hidden'))
                || (blockTimeModal && !blockTimeModal.classList.contains('hidden'))
                || (pageAlertModal && !pageAlertModal.classList.contains('hidden'));
            document.body.classList.toggle('overflow-hidden', Boolean(hasOpenModal));
        }

        function toMinutes(timeValue) {
            const parts = String(timeValue || '').split(':');
            const hour = Number(parts[0] || 0);
            const minute = Number(parts[1] || 0);
            return (hour * 60) + minute;
        }

        function applySetShiftRowStatus(statusEl, text, kind) {
            if (!statusEl) {
                return;
            }
            statusEl.textContent = text || '';
            statusEl.classList.remove(
                'text-emerald-700',
                'text-amber-700',
                'text-slate-600',
                'text-rose-600',
                'text-slate-400'
            );
            if (!text) {
                statusEl.classList.add('text-slate-400');
                return;
            }
            if (kind === 'valid') {
                statusEl.classList.add('text-emerald-700');
            } else if (kind === 'outside') {
                statusEl.classList.add('text-amber-700');
            } else if (kind === 'closed') {
                statusEl.classList.add('text-slate-600');
            } else if (kind === 'invalid') {
                statusEl.classList.add('text-rose-600');
            } else {
                statusEl.classList.add('text-slate-400');
            }
        }

        function setSetShiftRowIndicator(row, state) {
            const wrap = document.getElementById(row.loaderId);
            if (!wrap) {
                return;
            }
            const loadLayer = wrap.querySelector('.set-shift-day-loading');
            const okLayer = wrap.querySelector('.set-shift-day-success');
            const errLayer = wrap.querySelector('.set-shift-day-error');
            [loadLayer, okLayer, errLayer].forEach(function (layer) {
                if (layer) {
                    layer.classList.add('hidden');
                }
            });
            if (state === 'loading' && loadLayer) {
                loadLayer.classList.remove('hidden');
            } else if (state === 'valid' && okLayer) {
                okLayer.classList.remove('hidden');
            } else if (state === 'invalid' && errLayer) {
                errLayer.classList.remove('hidden');
            }
        }

        function runSetShiftRowValidation(row) {
            const startEl = document.getElementById(row.startId);
            const endEl = document.getElementById(row.endId);
            const statusEl = document.getElementById(row.statusId);
            if (!startEl || !endEl || !statusEl) {
                return;
            }
            if (isSetShiftRowClinicUnavailable(row) || startEl.disabled) {
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(statusEl, 'Clinic closed', 'closed');
                return;
            }
            const start = String(startEl && startEl.value ? startEl.value : '').trim();
            const end = String(endEl && endEl.value ? endEl.value : '').trim();

            if (start === '' && end === '') {
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(statusEl, '', 'neutral');
                return;
            }
            if (start === '' || end === '') {
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(statusEl, '', 'neutral');
                return;
            }
            if (!/^\d{2}:\d{2}$/.test(start) || !/^\d{2}:\d{2}$/.test(end)) {
                setSetShiftRowIndicator(row, 'invalid');
                applySetShiftRowStatus(statusEl, 'Invalid times', 'invalid');
                return;
            }
            const shiftStartMinutes = toMinutes(start);
            const shiftEndMinutes = toMinutes(end);
            if (shiftEndMinutes <= shiftStartMinutes) {
                setSetShiftRowIndicator(row, 'invalid');
                applySetShiftRowStatus(statusEl, 'Invalid times', 'invalid');
                return;
            }

            const snap = clinicHoursSnapshotByDayName[row.dayName];
            if (!snap || snap.is_closed || !snap.open_time_raw || !snap.close_time_raw) {
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(statusEl, 'Clinic closed', 'closed');
                return;
            }
            const clinicOpenMinutes = toMinutes(snap.open_time_raw);
            const clinicCloseMinutes = toMinutes(snap.close_time_raw);
            if (shiftStartMinutes < clinicOpenMinutes || shiftEndMinutes > clinicCloseMinutes) {
                setSetShiftRowIndicator(row, 'invalid');
                applySetShiftRowStatus(statusEl, 'Outside Clinic Hours', 'outside');
                return;
            }
            setSetShiftRowIndicator(row, 'valid');
            applySetShiftRowStatus(statusEl, 'Valid', 'valid');
        }

        function scheduleSetShiftRowValidation(row) {
            const startEl = document.getElementById(row.startId);
            const endEl = document.getElementById(row.endId);
            const statusEl = document.getElementById(row.statusId);
            const dayKey = row.dayName;
            if (setShiftRowValidateTimers[dayKey]) {
                clearTimeout(setShiftRowValidateTimers[dayKey]);
                setShiftRowValidateTimers[dayKey] = null;
            }

            if (!startEl || !endEl || !statusEl) {
                return;
            }
            if (isSetShiftRowClinicUnavailable(row) || startEl.disabled) {
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(statusEl, 'Clinic closed', 'closed');
                return;
            }

            const start = String(startEl && startEl.value ? startEl.value : '').trim();
            const end = String(endEl && endEl.value ? endEl.value : '').trim();

            if (start === '' && end === '') {
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(statusEl, '', 'neutral');
                return;
            }
            if (start === '' || end === '') {
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(statusEl, '', 'neutral');
                return;
            }

            setSetShiftRowIndicator(row, 'loading');
            applySetShiftRowStatus(statusEl, '', 'neutral');
            setShiftRowValidateTimers[dayKey] = setTimeout(function () {
                setShiftRowValidateTimers[dayKey] = null;
                runSetShiftRowValidation(row);
            }, SET_SHIFT_CLINIC_VALIDATE_MS);
        }

        function resetAllSetShiftRowStatuses() {
            setShiftWeekFieldDefs.forEach(function (row) {
                if (setShiftRowValidateTimers[row.dayName]) {
                    clearTimeout(setShiftRowValidateTimers[row.dayName]);
                    setShiftRowValidateTimers[row.dayName] = null;
                }
                const startEl = document.getElementById(row.startId);
                if (startEl && startEl.disabled) {
                    return;
                }
                setSetShiftRowIndicator(row, 'idle');
                applySetShiftRowStatus(document.getElementById(row.statusId), '', 'neutral');
            });
        }

        function refreshAllSetShiftRowValidations() {
            setShiftWeekFieldDefs.forEach(function (row) {
                scheduleSetShiftRowValidation(row);
            });
        }

        function formatDateForUiLabel(dateValue) {
            if (!dateValue || !/^\d{4}-\d{2}-\d{2}$/.test(dateValue)) {
                return '';
            }
            const parsedDate = new Date(dateValue + 'T00:00:00');
            if (Number.isNaN(parsedDate.getTime())) {
                return dateValue;
            }
            return parsedDate.toLocaleDateString('en-US', {
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            });
        }

        function clearSetShiftWeekFields() {
            setShiftWeekFieldDefs.forEach(function (row) {
                const startEl = document.getElementById(row.startId);
                const endEl = document.getElementById(row.endId);
                if (startEl && !startEl.disabled) {
                    startEl.value = '';
                }
                if (endEl && !endEl.disabled) {
                    endEl.value = '';
                }
            });
            syncSetShiftRowClinicLockForAllRows();
            resetAllSetShiftRowStatuses();
        }

        async function loadWeeklyShiftEditorFromServer() {
            if (!setShiftDentistId) {
                return;
            }
            const selectedDentistUserId = String(setShiftDentistId.value || '').trim();
            if (selectedDentistUserId === '') {
                return;
            }
            clearSetShiftWeekFields();
            try {
                const params = new URLSearchParams({
                    weekly_shift_editor_lookup: '1',
                    dentist_user_id: selectedDentistUserId
                });
                const response = await fetch(window.location.pathname + '?' + params.toString(), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                if (!response.ok) {
                    throw new Error('Unable to load weekly shift.');
                }
                const payload = await response.json();
                if (!payload || payload.success !== true) {
                    throw new Error((payload && payload.message) ? payload.message : 'Unable to load weekly shift.');
                }
                const shifts = payload.shifts && typeof payload.shifts === 'object' ? payload.shifts : {};
                setShiftWeekFieldDefs.forEach(function (row) {
                    const slot = shifts[row.dayName];
                    const startEl = document.getElementById(row.startId);
                    const endEl = document.getElementById(row.endId);
                    if (!startEl || !endEl) {
                        return;
                    }
                    if (isSetShiftRowClinicUnavailable(row)) {
                        startEl.value = '';
                        endEl.value = '';
                        return;
                    }
                    if (slot && slot.start_time_raw && slot.end_time_raw) {
                        startEl.value = String(slot.start_time_raw || '').slice(0, 5);
                        endEl.value = String(slot.end_time_raw || '').slice(0, 5);
                    } else {
                        startEl.value = '';
                        endEl.value = '';
                    }
                });
                if (setShiftNotes && typeof payload.notes === 'string') {
                    setShiftNotes.value = payload.notes;
                }
            } catch (ignored) {
                if (setShiftNotes) {
                    setShiftNotes.value = '';
                }
            }
            syncSetShiftRowClinicLockForAllRows();
            refreshAllSetShiftRowValidations();
        }

        async function loadWorkShiftForBlockTime(dateValue) {
            const normalizedDate = String(dateValue || '').trim();
            if (!blockTimeShiftHoursLabel || !blockTimeShiftStartReadonly || !blockTimeShiftEndReadonly || !blockTimeDentistId) return;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(normalizedDate)) return;

            const selectedDentistUserId = String(blockTimeDentistId.value || '').trim();
            blockTimeShiftHoursLabel.textContent = 'Selected Dentist Work Shift for ' + formatDateForUiLabel(normalizedDate);
            blockTimeShiftStartReadonly.value = 'Loading...';
            blockTimeShiftEndReadonly.value = 'Loading...';
            selectedWorkShiftForBlockTime = {
                hasRecord: false,
                startTimeRaw: '',
                endTimeRaw: ''
            };

            if (selectedDentistUserId === '') {
                blockTimeShiftStartReadonly.value = '-';
                blockTimeShiftEndReadonly.value = '-';
                return;
            }

            try {
                const params = new URLSearchParams({
                    dentist_shift_lookup: '1',
                    date: normalizedDate,
                    dentist_user_id: selectedDentistUserId
                });
                const response = await fetch(window.location.pathname + '?' + params.toString(), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                if (!response.ok) {
                    throw new Error('Unable to load dentist work shift.');
                }
                const payload = await response.json();
                if (!payload || payload.success !== true) {
                    throw new Error((payload && payload.message) ? payload.message : 'Unable to load dentist work shift.');
                }

                blockTimeShiftStartReadonly.value = String(payload.start_time || '-');
                blockTimeShiftEndReadonly.value = String(payload.end_time || '-');
                selectedWorkShiftForBlockTime = {
                    hasRecord: Boolean(payload.has_record),
                    startTimeRaw: String(payload.start_time_raw || ''),
                    endTimeRaw: String(payload.end_time_raw || '')
                };
            } catch (error) {
                blockTimeShiftStartReadonly.value = '-';
                blockTimeShiftEndReadonly.value = '-';
                showPageAlert((error && error.message) ? error.message : 'Unable to fetch dentist shift for the selected date.');
            }
        }

        async function openSetShiftModal() {
            if (!setShiftModal) return;
            if (setShiftNotes) {
                setShiftNotes.value = '';
            }
            await loadWeeklyShiftEditorFromServer();
            setShiftModal.classList.remove('hidden');
            syncModalBodyScrollLock();
        }

        function closeSetShiftModal() {
            if (!setShiftModal) return;
            setShiftModal.classList.add('hidden');
            syncModalBodyScrollLock();
        }

        function openBlockTimeModal() {
            if (!blockTimeModal) return;
            if (blockTimeDate && weekReferenceDateInput && weekReferenceDateInput.value) {
                blockTimeDate.value = weekReferenceDateInput.value;
            } else if (blockTimeDate && (!blockTimeDate.value || blockTimeDate.value < todayDateOnly)) {
                blockTimeDate.value = todayDateOnly;
            }
            if (blockTimeDentistId && selectedDentistUserIdInput && selectedDentistUserIdInput.value) {
                blockTimeDentistId.value = selectedDentistUserIdInput.value;
            }
            if (blockTimeDate) {
                loadWorkShiftForBlockTime(blockTimeDate.value);
            }
            blockTimeModal.classList.remove('hidden');
            syncModalBodyScrollLock();
        }

        function closeBlockTimeModal() {
            if (!blockTimeModal) return;
            blockTimeModal.classList.add('hidden');
            syncModalBodyScrollLock();
        }

        function openChooseDentistModal() {
            if (!chooseDentistModal) return;
            chooseDentistModal.classList.remove('hidden');
            syncModalBodyScrollLock();
            renderDentistsList();
        }

        function closeChooseDentistModal() {
            if (!chooseDentistModal) return;
            chooseDentistModal.classList.add('hidden');
            syncModalBodyScrollLock();
        }

        function setSelectedDentist(userId, dentistId, dentistName) {
            if (selectedDentistUserIdInput) selectedDentistUserIdInput.value = userId || '';
            if (selectedDentistIdInput) selectedDentistIdInput.value = dentistId || '';
            if (selectedDentistLabel) selectedDentistLabel.textContent = dentistName || 'All dentists';
        }

        function renderDentistsList() {
            if (!dentistListContainer || !dentistListEmptyState) return;
            if (!dentistsSeedData.length) {
                dentistListEmptyState.textContent = 'No dentists available.';
                dentistListEmptyState.classList.remove('hidden');
                dentistListContainer.innerHTML = '';
                return;
            }

            dentistListEmptyState.classList.add('hidden');
            dentistListContainer.innerHTML = dentistsSeedData.map(function (dentist) {
                const dentistId = escapeHtml(dentist.user_id || '');
                const fullNameText = String(dentist.full_name || 'Dentist').trim() || 'Dentist';
                const fullName = escapeHtml(fullNameText);
                const imageSrc = escapeHtml(resolveDentistProfileImageUrl(dentist.profile_image));
                const displayId = String(dentist.dentist_display_id || '').trim();
                const rawId = String(dentist.dentist_id || '').trim();
                const idLine = escapeHtml(displayId !== '' ? displayId : (rawId !== '' ? ('ID #' + rawId) : 'ID not set'));
                return '' +
                    '<div class="w-full sm:w-[19rem] rounded-2xl border border-slate-200 bg-slate-50/50 p-4 flex flex-col items-center text-center">' +
                        '<img src="' + imageSrc + '" alt="" class="w-24 h-24 rounded-full object-cover border border-slate-200 bg-white"/>' +
                        '<p class="mt-3 text-sm font-extrabold text-slate-900">' + fullName + '</p>' +
                        '<p class="mt-1 text-[11px] font-bold uppercase tracking-wider text-slate-500">' + idLine + '</p>' +
                        '<button type="button" data-action="select-dentist" data-user-id="' + dentistId + '" data-dentist-id="' + escapeHtml(dentist.dentist_id || '') + '" data-dentist-name="' + fullName + '" class="mt-3 rounded-lg px-3 py-2 text-xs font-extrabold uppercase tracking-wide transition-colors bg-primary text-white hover:bg-primary/90">Select</button>' +
                    '</div>';
            }).join('');
        }

        if (openSetShiftModalBtn) {
            openSetShiftModalBtn.addEventListener('click', openSetShiftModal);
        }
        if (openBlockTimeModalBtn) {
            openBlockTimeModalBtn.addEventListener('click', openBlockTimeModal);
        }
        if (closeSetShiftModalBtn) {
            closeSetShiftModalBtn.addEventListener('click', closeSetShiftModal);
        }
        if (closeBlockTimeModalBtn) {
            closeBlockTimeModalBtn.addEventListener('click', closeBlockTimeModal);
        }
        if (cancelSetShiftBtn) {
            cancelSetShiftBtn.addEventListener('click', closeSetShiftModal);
        }
        if (cancelBlockTimeBtn) {
            cancelBlockTimeBtn.addEventListener('click', closeBlockTimeModal);
        }
        if (setShiftModal) {
            setShiftModal.addEventListener('click', function (event) {
                if (event.target === setShiftModal || event.target === setShiftModal.firstElementChild) {
                    closeSetShiftModal();
                }
            });
        }
        if (blockTimeModal) {
            blockTimeModal.addEventListener('click', function (event) {
                if (event.target === blockTimeModal || event.target === blockTimeModal.firstElementChild) {
                    closeBlockTimeModal();
                }
            });
        }
        if (saveBlockTimeBtn) {
            saveBlockTimeBtn.addEventListener('click', async function () {
                if (!blockTimeDentistId || !blockTimeDate || !blockTimeStartTime || !blockTimeEndTime || !blockTimeReason) return;

                const selectedDate = String(blockTimeDate.value || '');
                const selectedStart = String(blockTimeStartTime.value || '');
                const selectedEnd = String(blockTimeEndTime.value || '');
                const selectedReason = String(blockTimeReason.value || 'Break');
                const notesValue = blockTimeNotes ? String(blockTimeNotes.value || '').trim() : '';
                const selectedDentistUserId = String(blockTimeDentistId.value || '');
                const selectedDentistOption = blockTimeDentistId.options[blockTimeDentistId.selectedIndex] || null;
                const selectedDentistId = selectedDentistOption ? String(selectedDentistOption.getAttribute('data-dentist-id') || '') : '';

                if (!selectedDate || selectedDate < todayDateOnly) {
                    showPageAlert('Please select today or a future date.');
                    return;
                }
                if (!selectedDentistUserId && !selectedDentistId) {
                    showPageAlert('Please select a dentist.');
                    return;
                }
                if (!selectedStart || !selectedEnd) {
                    showPageAlert('Please select both block start and end times.');
                    return;
                }
                if (toMinutes(selectedEnd) <= toMinutes(selectedStart)) {
                    showPageAlert('Block end time must be later than block start time.');
                    return;
                }
                if (!selectedWorkShiftForBlockTime.hasRecord || !selectedWorkShiftForBlockTime.startTimeRaw || !selectedWorkShiftForBlockTime.endTimeRaw) {
                    showPageAlert('No work shift found for the selected dentist and date.');
                    return;
                }

                const shiftStartMinutes = toMinutes(selectedWorkShiftForBlockTime.startTimeRaw);
                const shiftEndMinutes = toMinutes(selectedWorkShiftForBlockTime.endTimeRaw);
                const blockStartMinutes = toMinutes(selectedStart);
                const blockEndMinutes = toMinutes(selectedEnd);
                if (blockStartMinutes < shiftStartMinutes || blockEndMinutes > shiftEndMinutes) {
                    showPageAlert('Block time must be within the selected dentist work shift.');
                    return;
                }

                const originalButtonLabel = saveBlockTimeBtn.textContent;
                saveBlockTimeBtn.disabled = true;
                saveBlockTimeBtn.textContent = 'Saving...';
                try {
                    const payload = new URLSearchParams();
                    payload.append('save_block_time', '1');
                    payload.append('dentist_id', selectedDentistId);
                    payload.append('dentist_user_id', selectedDentistUserId);
                    payload.append('block_date', selectedDate);
                    payload.append('start_time', selectedStart);
                    payload.append('end_time', selectedEnd);
                    payload.append('reason', selectedReason);
                    payload.append('notes', notesValue);

                    const response = await fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: payload.toString()
                    });
                    const result = await response.json();
                    if (!response.ok || !result || result.success !== true) {
                        throw new Error((result && result.message) ? result.message : 'Failed to save blocked time.');
                    }

                    closeBlockTimeModal();
                    const refreshedUrl = new URL(window.location.href);
                    refreshedUrl.searchParams.set('selected_date', selectedDate);
                    if (selectedDentistUserId) {
                        refreshedUrl.searchParams.set('user_id', selectedDentistUserId);
                    }
                    if (selectedDentistId) {
                        refreshedUrl.searchParams.set('dentist_id', selectedDentistId);
                    }
                    window.location.href = refreshedUrl.toString();
                } catch (error) {
                    showPageAlert((error && error.message) ? error.message : 'Failed to save blocked time.');
                } finally {
                    saveBlockTimeBtn.disabled = false;
                    saveBlockTimeBtn.textContent = originalButtonLabel;
                }
            });
        }
        if (blockTimeDate) {
            blockTimeDate.addEventListener('change', function () {
                if (blockTimeDate.value < todayDateOnly) {
                    blockTimeDate.value = todayDateOnly;
                }
                loadWorkShiftForBlockTime(blockTimeDate.value);
            });
        }
        if (blockTimeDentistId) {
            blockTimeDentistId.addEventListener('change', function () {
                if (!blockTimeDate) return;
                loadWorkShiftForBlockTime(blockTimeDate.value);
            });
        }
        if (pageAlertOkBtn) {
            pageAlertOkBtn.addEventListener('click', closePageAlert);
        }
        if (pageAlertModal) {
            pageAlertModal.addEventListener('click', function (event) {
                if (event.target === pageAlertModal || event.target === pageAlertModal.firstElementChild) {
                    closePageAlert();
                }
            });
        }
        if (saveSetShiftBtn) {
            saveSetShiftBtn.addEventListener('click', async function () {
                if (!setShiftDentistId || !setShiftForm) return;

                const selectedDentistUserId = String(setShiftDentistId.value || '');
                const selectedDentistOption = setShiftDentistId.options[setShiftDentistId.selectedIndex] || null;
                const selectedDentistId = selectedDentistOption ? String(selectedDentistOption.getAttribute('data-dentist-id') || '') : '';
                const selectedDentistHasUserId = selectedDentistOption ? selectedDentistOption.getAttribute('data-has-user-id') === '1' : false;
                const anchorDate = String((weekReferenceDateInput && weekReferenceDateInput.value) ? weekReferenceDateInput.value : scheduleWeekAnchorDefault || '');
                const notesValue = setShiftNotes ? String(setShiftNotes.value || '').trim() : '';

                if (!anchorDate || !/^\d{4}-\d{2}-\d{2}$/.test(anchorDate)) {
                    showPageAlert('Unable to determine the schedule week. Refresh the page and try again.');
                    return;
                }
                if (!selectedDentistUserId && !selectedDentistId) {
                    showPageAlert('Please select a dentist.');
                    return;
                }
                if (!selectedDentistHasUserId) {
                    showPageAlert('The selected dentist has no linked account. Please link the dentist to a user account first.');
                    return;
                }

                for (let w = 0; w < setShiftWeekFieldDefs.length; w++) {
                    const row = setShiftWeekFieldDefs[w];
                    const startEl = document.getElementById(row.startId);
                    const endEl = document.getElementById(row.endId);
                    if (!startEl || !endEl) {
                        continue;
                    }
                    if (startEl.disabled || endEl.disabled) {
                        continue;
                    }
                    const selectedStart = String(startEl.value || '').trim();
                    const selectedEnd = String(endEl.value || '').trim();
                    if (selectedStart === '' && selectedEnd === '') {
                        continue;
                    }
                    if (selectedStart === '' || selectedEnd === '') {
                        showPageAlert(row.dayName + ': Enter both start and end times, or leave both empty for a day off.');
                        return;
                    }
                    if (toMinutes(selectedEnd) <= toMinutes(selectedStart)) {
                        showPageAlert(row.dayName + ': End time must be later than start time.');
                        return;
                    }
                    const snap = clinicHoursSnapshotByDayName[row.dayName];
                    if (!snap || snap.is_closed || !snap.open_time_raw || !snap.close_time_raw) {
                        continue;
                    }
                    const clinicOpenMinutes = toMinutes(snap.open_time_raw);
                    const clinicCloseMinutes = toMinutes(snap.close_time_raw);
                    const shiftStartMinutes = toMinutes(selectedStart);
                    const shiftEndMinutes = toMinutes(selectedEnd);
                    if (shiftStartMinutes < clinicOpenMinutes || shiftEndMinutes > clinicCloseMinutes) {
                        showPageAlert(row.dayName + ': Shift must fall within clinic operating hours for that day.');
                        return;
                    }
                }

                const originalButtonLabel = saveSetShiftBtn.textContent;
                saveSetShiftBtn.disabled = true;
                saveSetShiftBtn.textContent = 'Saving...';
                try {
                    const payload = new URLSearchParams(new FormData(setShiftForm));
                    payload.set('save_set_shift', '1');
                    payload.set('dentist_id', selectedDentistId);
                    payload.set('dentist_user_id', selectedDentistUserId);
                    payload.set('shift_week_anchor', anchorDate);
                    payload.set('notes', notesValue);

                    const response = await fetch(window.location.pathname + window.location.search, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin',
                        body: payload.toString()
                    });
                    const result = await response.json();
                    if (!response.ok || !result || result.success !== true) {
                        throw new Error((result && result.message) ? result.message : 'Failed to save shift.');
                    }

                    closeSetShiftModal();
                    const refreshedUrl = new URL(window.location.href);
                    refreshedUrl.searchParams.set('selected_date', anchorDate);
                    refreshedUrl.searchParams.delete('user_id');
                    refreshedUrl.searchParams.delete('dentist_id');
                    window.location.href = refreshedUrl.toString();
                } catch (error) {
                    showPageAlert((error && error.message) ? error.message : 'Failed to save shift.');
                } finally {
                    saveSetShiftBtn.disabled = false;
                    saveSetShiftBtn.textContent = originalButtonLabel;
                }
            });
        }
        if (setShiftDentistId) {
            setShiftDentistId.addEventListener('change', function () {
                if (!setShiftModal || setShiftModal.classList.contains('hidden')) {
                    return;
                }
                loadWeeklyShiftEditorFromServer();
            });
        }
        setShiftWeekFieldDefs.forEach(function (row) {
            const startEl = document.getElementById(row.startId);
            const endEl = document.getElementById(row.endId);
            if (!startEl || !endEl) {
                return;
            }
            const onShiftTimeAdjust = function () {
                scheduleSetShiftRowValidation(row);
            };
            startEl.addEventListener('input', onShiftTimeAdjust);
            startEl.addEventListener('change', onShiftTimeAdjust);
            endEl.addEventListener('input', onShiftTimeAdjust);
            endEl.addEventListener('change', onShiftTimeAdjust);
        });
        if (chooseDentistBtn) {
            chooseDentistBtn.addEventListener('click', openChooseDentistModal);
        }
        if (clearDentistBtn) {
            clearDentistBtn.addEventListener('click', function () {
                if (!selectedDentistUserIdInput || !scheduleFilterForm) return;
                selectedDentistUserIdInput.value = '';
                if (selectedDentistIdInput) {
                    selectedDentistIdInput.value = '';
                }
                if (selectedDentistLabel) {
                    selectedDentistLabel.textContent = 'All dentists';
                }
                scheduleFilterForm.submit();
            });
        }
        if (closeChooseDentistModalBtn) {
            closeChooseDentistModalBtn.addEventListener('click', closeChooseDentistModal);
        }
        if (chooseDentistModal) {
            chooseDentistModal.addEventListener('click', function (event) {
                if (event.target === chooseDentistModal || event.target === chooseDentistModal.firstElementChild) {
                    closeChooseDentistModal();
                }
            });
        }
        if (dentistListContainer) {
            dentistListContainer.addEventListener('click', function (event) {
                const button = event.target.closest('button[data-action="select-dentist"]');
                if (!button) return;
                const userId = button.getAttribute('data-user-id') || '';
                const dentistId = button.getAttribute('data-dentist-id') || '';
                const dentistName = button.getAttribute('data-dentist-name') || '';
                setSelectedDentist(userId, dentistId, dentistName);
                closeChooseDentistModal();
                if (scheduleFilterForm) {
                    scheduleFilterForm.submit();
                }
            });
        }
        if (weekReferenceDateInput) {
            weekReferenceDateInput.addEventListener('change', function () {
                if (scheduleFilterForm) {
                    scheduleFilterForm.submit();
                }
            });
        }
        const miniCalendarDateButtons = document.querySelectorAll('[data-mini-calendar-date]');
        const miniCalendarMonthNavButtons = document.querySelectorAll('[data-mini-calendar-month-nav]');
        const shiftDateByMonth = function (dateString, monthDelta) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(String(dateString || '').trim())) return '';
            const [yearRaw, monthRaw, dayRaw] = String(dateString).split('-');
            const year = Number(yearRaw);
            const month = Number(monthRaw);
            const day = Number(dayRaw);
            if (!Number.isInteger(year) || !Number.isInteger(month) || !Number.isInteger(day)) return '';
            const firstOfCurrentMonth = new Date(year, month - 1, 1);
            const targetFirstOfMonth = new Date(firstOfCurrentMonth.getFullYear(), firstOfCurrentMonth.getMonth() + monthDelta, 1);
            const targetYear = targetFirstOfMonth.getFullYear();
            const targetMonthIndex = targetFirstOfMonth.getMonth();
            const maxDay = new Date(targetYear, targetMonthIndex + 1, 0).getDate();
            const targetDay = Math.min(day, maxDay);
            const targetDate = new Date(targetYear, targetMonthIndex, targetDay);
            const yyyy = targetDate.getFullYear();
            const mm = String(targetDate.getMonth() + 1).padStart(2, '0');
            const dd = String(targetDate.getDate()).padStart(2, '0');
            return yyyy + '-' + mm + '-' + dd;
        };
        miniCalendarMonthNavButtons.forEach(function (navButton) {
            navButton.addEventListener('click', function () {
                if (!weekReferenceDateInput || !scheduleFilterForm) return;
                const navDirection = String(navButton.getAttribute('data-mini-calendar-month-nav') || '').trim();
                if (navDirection !== 'prev' && navDirection !== 'next') return;
                const monthDelta = navDirection === 'prev' ? -1 : 1;
                const baseDateValue = String(weekReferenceDateInput.value || '').trim();
                const shiftedDateValue = shiftDateByMonth(baseDateValue, monthDelta);
                if (!/^\d{4}-\d{2}-\d{2}$/.test(shiftedDateValue)) return;
                weekReferenceDateInput.value = shiftedDateValue;
                scheduleFilterForm.submit();
            });
        });
        miniCalendarDateButtons.forEach(function (dateButton) {
            dateButton.addEventListener('click', function () {
                if (!weekReferenceDateInput || !scheduleFilterForm) return;
                const selectedDateValue = String(dateButton.getAttribute('data-mini-calendar-date') || '').trim();
                if (!/^\d{4}-\d{2}-\d{2}$/.test(selectedDateValue)) return;
                weekReferenceDateInput.value = selectedDateValue;
                scheduleFilterForm.submit();
            });
        });
        if (showCompletedToggle) {
            showCompletedToggle.addEventListener('change', function () {
                if (scheduleFilterForm) {
                    scheduleFilterForm.submit();
                }
            });
        }

        const shiftTooltipTargets = document.querySelectorAll('[data-shift-tooltip="1"]');
        shiftTooltipTargets.forEach(function (shiftEl) {
            shiftEl.addEventListener('mouseenter', function (event) {
                showShiftTooltip(shiftEl, event);
            });
            shiftEl.addEventListener('mousemove', function (event) {
                if (!shiftInfoTooltip || shiftInfoTooltip.classList.contains('hidden')) return;
                positionShiftTooltip(shiftEl.getBoundingClientRect(), event.clientX, event.clientY);
            });
            shiftEl.addEventListener('mouseleave', hideShiftTooltip);
        });

        const appointmentTooltipTargets = document.querySelectorAll('[data-appointment-tooltip="1"]');
        appointmentTooltipTargets.forEach(function (apptEl) {
            apptEl.addEventListener('mouseenter', function (event) {
                showAppointmentTooltip(apptEl, event);
            });
            apptEl.addEventListener('mousemove', function (event) {
                if (!appointmentInfoTooltip || appointmentInfoTooltip.classList.contains('hidden')) return;
                positionAppointmentTooltip(apptEl.getBoundingClientRect(), event.clientX, event.clientY);
            });
            apptEl.addEventListener('mouseleave', hideAppointmentTooltip);
        });

        const blockTooltipTargets = document.querySelectorAll('[data-block-tooltip="1"]');
        blockTooltipTargets.forEach(function (blockEl) {
            blockEl.addEventListener('mouseenter', function (event) {
                showBlockTooltip(blockEl, event);
            });
            blockEl.addEventListener('mousemove', function (event) {
                if (!blockInfoTooltip || blockInfoTooltip.classList.contains('hidden')) return;
                positionBlockTooltip(blockEl.getBoundingClientRect(), event.clientX, event.clientY);
            });
            blockEl.addEventListener('mouseleave', hideBlockTooltip);
        });

        window.addEventListener('scroll', function () {
            hideShiftTooltip();
            hideAppointmentTooltip();
            hideBlockTooltip();
        }, { passive: true });
        window.addEventListener('resize', function () {
            hideShiftTooltip();
            hideAppointmentTooltip();
            hideBlockTooltip();
        });
    })();
</script>
</body>
</html>
