<?php
$staff_nav_active = 'my_schedule';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/appointment_db_tables.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
        return ['label' => 'Hygiene', 'class' => 'bg-teal-500 border-teal-600'];
    }
    if (strpos($normalized, 'consult') !== false) {
        return ['label' => 'Consultation', 'class' => 'bg-orange-500 border-orange-600'];
    }
    return ['label' => 'Treatment', 'class' => 'bg-violet-500 border-violet-600'];
}

$tz = new DateTimeZone('Asia/Manila');
$selectedDateInput = isset($_GET['selected_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $_GET['selected_date'])
    ? (string) $_GET['selected_date']
    : (new DateTimeImmutable('now', $tz))->format('Y-m-d');
$selectedDate = new DateTimeImmutable($selectedDateInput, $tz);
$startOfWeek = $selectedDate->modify('-' . $selectedDate->format('w') . ' days')->setTime(0, 0, 0);
$endOfWeek = $startOfWeek->modify('+6 days')->setTime(23, 59, 59);
$monthLabel = $selectedDate->format('F Y');
$weekLabel = $startOfWeek->format('M j') . '-' . $endOfWeek->format('j, Y');

$gridStartMinutes = 6 * 60;
$gridEndMinutes = 28 * 60; // 4:00 AM next day
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
for ($hour = 6; $hour <= 28; $hour++) {
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
$hasUserFilterParam = array_key_exists('user_id', $_GET);
$hasDentistIdFilterParam = array_key_exists('dentist_id', $_GET);
$selectedDentistName = 'All dentists';
$isDentistFiltered = false;
$entriesByDate = [];
foreach ($weekDays as $day) {
    $entriesByDate[$day['date_key']] = [];
}

try {
    $pdo = getDBConnection();

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

        $hoursStmt = $pdo->prepare("
            SELECT open_time, close_time, is_closed
            FROM tbl_clinic_hours
            WHERE clinic_date = ?
            LIMIT 1
        ");
        $hoursStmt->execute([$lookupDate]);
        $hoursRow = $hoursStmt->fetch(PDO::FETCH_ASSOC);

        $hasRecord = is_array($hoursRow) && !empty($hoursRow);
        $isClosed = $hasRecord && isset($hoursRow['is_closed']) && (int) $hoursRow['is_closed'] === 1;
        $openTimeRaw = null;
        $closeTimeRaw = null;
        $openTimeLabel = '-';
        $closeTimeLabel = '-';

        if ($hasRecord && !$isClosed) {
            $openTimeRaw = substr((string) ($hoursRow['open_time'] ?? ''), 0, 5);
            $closeTimeRaw = substr((string) ($hoursRow['close_time'] ?? ''), 0, 5);
            if ($openTimeRaw !== '' && $closeTimeRaw !== '') {
                $openTimeLabel = formatTimeForUi($openTimeRaw);
                $closeTimeLabel = formatTimeForUi($closeTimeRaw);
            }
        } elseif ($isClosed) {
            $openTimeLabel = '--';
            $closeTimeLabel = '--';
        }

        echo json_encode([
            'success' => true,
            'date' => $lookupDate,
            'has_record' => $hasRecord,
            'is_closed' => $isClosed,
            'open_time' => $openTimeLabel,
            'close_time' => $closeTimeLabel,
            'open_time_raw' => $openTimeRaw,
            'close_time_raw' => $closeTimeRaw,
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
        $shiftDate = isset($_POST['shift_date']) ? trim((string) $_POST['shift_date']) : '';
        $shiftStart = isset($_POST['start_time']) ? trim((string) $_POST['start_time']) : '';
        $shiftEnd = isset($_POST['end_time']) ? trim((string) $_POST['end_time']) : '';
        $shiftRepeat = isset($_POST['repeat']) ? trim((string) $_POST['repeat']) : 'one_day';
        $shiftNotesInput = isset($_POST['notes']) ? trim((string) $_POST['notes']) : '';
        $shiftNotes = $shiftNotesInput !== '' ? $shiftNotesInput : null;

        if ($shiftDentistId === '' && $shiftDentistUserId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a dentist.']);
            exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select a valid shift date.']);
            exit;
        }
        if ($shiftDate < $todayDateOnly) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Shift date must be today or a future date.']);
            exit;
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $shiftStart) || !preg_match('/^\d{2}:\d{2}$/', $shiftEnd)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please select valid start and end times.']);
            exit;
        }
        if (toMinutes($shiftEnd) <= toMinutes($shiftStart)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Shift end time must be later than start time.']);
            exit;
        }
        if (!in_array($shiftRepeat, ['one_day', 'weekly'], true)) {
            $shiftRepeat = 'one_day';
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

        // Resolve missing pieces from dentist table when possible.
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

        // Legacy-data fallback: resolve by dentist profile email within tenant.
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

        $clinicHoursStmt = $pdo->prepare("
            SELECT open_time, close_time, is_closed
            FROM tbl_clinic_hours
            WHERE clinic_date = ?
            LIMIT 1
        ");
        $clinicHoursStmt->execute([$shiftDate]);
        $clinicHoursRow = $clinicHoursStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($clinicHoursRow) || empty($clinicHoursRow)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Clinic hours are not set for the selected date.',
            ]);
            exit;
        }
        if (isset($clinicHoursRow['is_closed']) && (int) $clinicHoursRow['is_closed'] === 1) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'The clinic is marked closed for this date.',
            ]);
            exit;
        }

        $clinicOpen = substr((string) ($clinicHoursRow['open_time'] ?? ''), 0, 5);
        $clinicClose = substr((string) ($clinicHoursRow['close_time'] ?? ''), 0, 5);
        if ($clinicOpen === '' || $clinicClose === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Clinic opening and closing times are incomplete for this date.',
            ]);
            exit;
        }
        $clinicOpenMinutes = toMinutes($clinicOpen);
        $clinicCloseMinutes = toMinutes($clinicClose);
        $shiftStartMinutes = toMinutes($shiftStart);
        $shiftEndMinutes = toMinutes($shiftEnd);
        if ($shiftStartMinutes < $clinicOpenMinutes || $shiftEndMinutes > $clinicCloseMinutes) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Shift time must be within clinic operating hours for the selected day.',
            ]);
            exit;
        }

        $shiftDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $shiftDate, $tz);
        $shiftDayName = $shiftDateObj instanceof DateTimeImmutable ? $shiftDateObj->format('l') : null;
        $createdByUserId = isset($_SESSION['user_id']) ? trim((string) $_SESSION['user_id']) : null;
        if ($createdByUserId === '') {
            $createdByUserId = null;
        }

        if ($shiftRepeat === 'one_day') {
            $disableExistingStmt = $pdo->prepare("
                UPDATE tbl_schedule_blocks
                SET is_active = 0
                WHERE tenant_id = ?
                  AND user_id = ?
                  AND block_type = 'shift'
                  AND block_date = ?
            ");
            $disableExistingStmt->execute([$tenantId, $shiftUserId, $shiftDate]);

            $insertShiftStmt = $pdo->prepare("
                INSERT INTO tbl_schedule_blocks
                    (tenant_id, user_id, block_date, day_of_week, start_time, end_time, block_type, is_active, notes, created_by)
                VALUES
                    (?, ?, ?, NULL, ?, ?, 'shift', 1, ?, ?)
            ");
            $insertShiftStmt->execute([
                $tenantId,
                $shiftUserId,
                $shiftDate,
                $shiftStart . ':00',
                $shiftEnd . ':00',
                $shiftNotes,
                $createdByUserId,
            ]);
        } else {
            if (!in_array($shiftDayName, $dayNameMap, true)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Unable to determine weekly repeat day.',
                ]);
                exit;
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
            $disableExistingWeeklyStmt->execute([$tenantId, $shiftUserId, $shiftDayName]);

            $insertWeeklyShiftStmt = $pdo->prepare("
                INSERT INTO tbl_schedule_blocks
                    (tenant_id, user_id, block_date, day_of_week, start_time, end_time, block_type, is_active, notes, created_by)
                VALUES
                    (?, ?, NULL, ?, ?, ?, 'shift', 1, ?, ?)
            ");
            $insertWeeklyShiftStmt->execute([
                $tenantId,
                $shiftUserId,
                $shiftDayName,
                $shiftStart . ':00',
                $shiftEnd . ':00',
                $shiftNotes,
                $createdByUserId,
            ]);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Shift saved successfully.',
            'selected_date' => $shiftDate,
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

                if ($blockType === 'break') {
                    $entriesByDate[$targetDateKey][] = [
                        'start_min' => $startMin,
                        'end_min' => $endMin,
                        'label' => 'Blocked',
                        'class' => 'bg-slate-500 border-slate-600',
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
                        'class' => 'bg-emerald-100/60 border-emerald-300/85 text-emerald-900',
                        'kind' => 'work',
                    ];
                }
            }

            $appointmentsSql = "
                SELECT appointment_date, appointment_time, service_type, COALESCE(status, 'pending') AS appointment_status
                FROM tbl_appointments
                WHERE tenant_id = ?
                  AND appointment_date BETWEEN ? AND ?
                  AND LOWER(COALESCE(status, 'pending')) <> 'cancelled'
            ";
            $appointmentsParams = [
                $tenantId,
                $startOfWeek->format('Y-m-d'),
                $endOfWeek->format('Y-m-d'),
            ];
            if ($selectedDentistId !== '') {
                $appointmentsSql .= " AND dentist_id = ? ";
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
                $endMin = $startMin + 60;
                $mapped = mapAppointmentClass((string) ($row['service_type'] ?? ''));
                $appointmentStatus = strtolower(trim((string) ($row['appointment_status'] ?? 'pending')));
                $appointmentDate = trim((string) ($row['appointment_date'] ?? ''));
                $appointmentDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i', $appointmentDate . ' ' . $startTime, $tz);
                $isPastAppointment = ($appointmentDateTime instanceof DateTimeImmutable) && $appointmentDateTime < new DateTimeImmutable('now', $tz);
                $entriesByDate[$dateKey][] = [
                    'start_min' => $startMin,
                    'end_min' => $endMin,
                    'label' => $isPastAppointment ? ($mapped['label'] . ' (Completed)') : $mapped['label'],
                    'class' => $isPastAppointment ? 'bg-slate-400 border-slate-500' : $mapped['class'],
                    'kind' => ($appointmentStatus === 'completed' || $isPastAppointment) ? 'appointment_past' : 'appointment',
                ];
            }
    }
} catch (Throwable $e) {
    // Keep empty-state UI when data is unavailable.
}

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

    $activeLanes = [];
    $clusterIndexes = [];
    $clusterLaneCount = 1;
    $finalizeWorkCluster = static function (&$entriesRef, $clusterRefs, $laneCount) {
        if (empty($clusterRefs)) {
            return;
        }
        $resolvedLaneCount = max(1, (int) $laneCount);
        foreach ($clusterRefs as $entryRefIndex) {
            $entriesRef[$entryRefIndex]['lane_total'] = $resolvedLaneCount;
        }
    };

    foreach ($workEntryIndexes as $workEntryIndex) {
        $workStart = (int) ($entries[$workEntryIndex]['start_min'] ?? 0);
        $workEnd = (int) ($entries[$workEntryIndex]['end_min'] ?? 0);
        foreach ($activeLanes as $laneIndex => $activeEnd) {
            if ((int) $activeEnd <= $workStart) {
                unset($activeLanes[$laneIndex]);
            }
        }

        if (empty($activeLanes) && !empty($clusterIndexes)) {
            $finalizeWorkCluster($entries, $clusterIndexes, $clusterLaneCount);
            $clusterIndexes = [];
            $clusterLaneCount = 1;
        }

        $assignedLane = 0;
        while (isset($activeLanes[$assignedLane])) {
            $assignedLane++;
        }
        $activeLanes[$assignedLane] = $workEnd;
        $entries[$workEntryIndex]['lane_index'] = $assignedLane;
        $clusterIndexes[] = $workEntryIndex;
        if (($assignedLane + 1) > $clusterLaneCount) {
            $clusterLaneCount = $assignedLane + 1;
        }
    }
    $finalizeWorkCluster($entries, $clusterIndexes, $clusterLaneCount);

    $entriesByDate[$dayKey] = $entries;
}

$dayMaxLaneCount = [];
foreach ($weekDays as $d) {
    $dayMaxLaneCount[$d['date_key']] = 1;
}
foreach ($entriesByDate as $dayKey => $ents) {
    foreach ($ents as $e) {
        if ((string) ($e['kind'] ?? '') === 'work') {
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
    <title>My Schedule - Staff Portals</title>
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
        @keyframes alert-popup-in {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
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
                    <button id="openSetShiftModalBtn" type="button" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-primary/30 bg-primary/10 text-primary hover:bg-primary/15 font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                        <span class="material-symbols-outlined text-base">schedule</span>
                        Set Shift
                    </button>
                </div>
            </div>
        </section>

        <section class="elevated-card p-6 rounded-3xl">
            <form method="get" id="scheduleFilterForm" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                <div class="lg:col-span-5">
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
                    <p class="mt-2 text-xs font-semibold text-slate-500">
                        <?php echo $isDentistFiltered ? 'Showing filtered view for selected dentist.' : 'Showing overall view for all dentists.'; ?>
                    </p>
                </div>
                <div class="lg:col-span-4">
                    <label class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Week Reference Date</label>
                    <input type="date" name="selected_date" value="<?php echo htmlspecialchars($selectedDate->format('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" class="w-full py-3 px-4 rounded-xl border border-slate-200 bg-white text-sm font-semibold text-slate-700 focus:ring-2 focus:ring-primary/20 focus:border-primary"/>
                </div>
                <?php if ($currentTenantSlug !== ''): ?>
                    <input type="hidden" name="clinic_slug" value="<?php echo htmlspecialchars($currentTenantSlug, ENT_QUOTES, 'UTF-8'); ?>"/>
                <?php endif; ?>
                <div class="lg:col-span-3 flex items-end">
                    <button type="submit" class="w-full px-5 py-3 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs uppercase tracking-widest transition-colors">
                        Apply Week
                    </button>
                </div>
            </form>
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
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Schedule Status</p>
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                            <span class="w-3 h-3 rounded-full bg-primary"></span><span class="text-sm font-semibold text-slate-700">Appointment (all services)</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                            <span class="w-3 h-3 rounded-full bg-slate-700"></span><span class="text-sm font-semibold text-slate-700">Blocked / Personal Time</span>
                        </div>
                        <div class="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-500"></span><span class="text-sm font-semibold text-slate-700">Available Slot</span>
                        </div>
                    </div>
                    <div class="mt-5 pt-5 border-t border-slate-100 space-y-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.18em] text-slate-500">Entry Type</p>
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
                            <div class="px-3 py-3 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 sticky left-0 z-50 bg-slate-50 border-r border-slate-200 shadow-[2px_0_12px_-4px_rgba(15,23,42,0.1)]">Time</div>
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
                            <div class="border-r border-slate-100 bg-slate-50/80 backdrop-blur-sm sticky left-0 z-40 shadow-[2px_0_14px_-4px_rgba(15,23,42,0.12)]">
                                <?php foreach ($timeSlots as $slotTime): ?>
                                    <div class="h-16 px-3 py-3 text-xs font-bold text-slate-500 border-b border-slate-100 last:border-b-0 bg-slate-50/70">
                                        <?php echo htmlspecialchars($slotTime, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php foreach ($weekDays as $weekDay): ?>
                                <?php $dayEntries = $entriesByDate[$weekDay['date_key']] ?? []; ?>
                                <div class="relative border-l border-slate-100" style="height: <?php echo (int) $gridHeightPx; ?>px;">
                                    <?php for ($line = 1; $line <= $gridHourSegments; $line++): ?>
                                        <div class="absolute left-0 right-0 border-t border-slate-100" style="top: <?php echo (int) ($line * $pixelsPerHour); ?>px;"></div>
                                    <?php endfor; ?>
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
                                        $entryClass = (string) ($entry['class'] ?? 'bg-slate-500 border-slate-600');
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
                                        $fullDentistName = (string) ($entry['dentist_name'] ?? 'Dr. Dentist');
                                        $shortDentistName = formatShiftDentistShortName($fullDentistName);
                                        $entryKind = (string) ($entry['kind'] ?? '');
                                        $zClass = ($entryKind === 'appointment' || $entryKind === 'appointment_past') ? 'z-30' : (($entryKind === 'work') ? 'z-20' : 'z-10');
                                        $entryStyle = 'top: ' . $topPx . 'px; height: ' . $heightPx . 'px;';
                                        if ($isWork) {
                                            $laneIndex = max(0, (int) ($entry['lane_index'] ?? 0));
                                            $laneTotal = max(1, (int) ($entry['lane_total'] ?? 1));
                                            $laneGapPercent = 1.5;
                                            $usableWidthPercent = 100 - (($laneTotal - 1) * $laneGapPercent);
                                            $laneWidthPercent = $usableWidthPercent > 0 ? ($usableWidthPercent / $laneTotal) : (100 / $laneTotal);
                                            $laneLeftPercent = $laneIndex * ($laneWidthPercent + $laneGapPercent);
                                            $entryStyle .= ' left: calc(6px + ' . number_format($laneLeftPercent, 4, '.', '') . '%);';
                                            $entryStyle .= ' width: calc(' . number_format($laneWidthPercent, 4, '.', '') . '% - 8px);';
                                        } else {
                                            $entryStyle .= ' left: 6px; right: 6px;';
                                        }
                                        ?>
                                        <div class="schedule-block absolute rounded-xl border px-2 py-1.5 <?php echo htmlspecialchars($entryClass, ENT_QUOTES, 'UTF-8'); ?> <?php echo $isWork ? 'text-emerald-900' : 'text-white'; ?> <?php echo $zClass; ?>" style="<?php echo htmlspecialchars($entryStyle, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $isWork ? ('data-shift-tooltip="1" data-shift-full-name="' . htmlspecialchars($fullDentistName, ENT_QUOTES, 'UTF-8') . '" data-shift-time="' . htmlspecialchars($timeRangeLabel, ENT_QUOTES, 'UTF-8') . '"') : ''; ?>>
                                            <?php if ($isWork): ?>
                                                <p class="text-[9px] font-black uppercase tracking-[0.12em] text-emerald-900/80 leading-tight">WORK SHIFT</p>
                                                <p class="mt-0.5 text-[10px] font-black text-emerald-900 truncate"><?php echo htmlspecialchars($shortDentistName, ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p class="mt-1 text-[10px] font-semibold text-emerald-900/90 truncate"><?php echo htmlspecialchars($timeRangeLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php else: ?>
                                                <p class="text-[10px] font-black uppercase tracking-[0.12em]"><?php echo htmlspecialchars((string) $entry['label'], ENT_QUOTES, 'UTF-8'); ?></p>
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
    <div class="absolute inset-0 bg-slate-900/45"></div>
    <div class="relative h-full w-full flex items-center justify-center p-4">
        <div class="w-full max-w-3xl rounded-3xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="px-6 py-5 border-b border-slate-200 bg-gradient-to-r from-primary/10 via-white to-white flex items-center justify-between gap-4">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500">Dentist Scheduling</p>
                    <h3 class="text-2xl font-extrabold text-slate-900 tracking-tight">Set Dentist Shift</h3>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Define a one-day or weekly working schedule.</p>
                </div>
                <button id="closeSetShiftModalBtn" type="button" class="w-9 h-9 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 inline-flex items-center justify-center">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>
            <div class="px-6 py-6 bg-slate-50/40">
                <form id="setShiftForm" class="space-y-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 space-y-4 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="setShiftDentistId" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Dentist</label>
                                <select id="setShiftDentistId" class="schedule-input w-full py-3 px-4">
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
                            <div>
                                <label for="setShiftDate" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Date</label>
                                <div class="schedule-input w-full py-3 px-4 inline-flex items-center gap-2.5">
                                    <span class="material-symbols-outlined text-[18px] text-primary">event</span>
                                    <input id="setShiftDate" type="date" min="<?php echo htmlspecialchars($todayDateOnly, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($setShiftDefaultDate, ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-transparent p-0 border-0 text-sm font-extrabold text-slate-900 focus:ring-0"/>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                            <p id="setShiftClinicHoursLabel" class="text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em]">Clinic Hours for the day <?php echo htmlspecialchars((new DateTimeImmutable($setShiftDefaultDate, $tz))->format('F j, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="setShiftClinicOpenTime" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Open Time (Read Only)</label>
                                    <input id="setShiftClinicOpenTime" type="text" value="-" readonly class="w-full py-3 px-4 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-500 cursor-not-allowed"/>
                                </div>
                                <div>
                                    <label for="setShiftClinicCloseTime" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Close Time (Read Only)</label>
                                    <input id="setShiftClinicCloseTime" type="text" value="-" readonly class="w-full py-3 px-4 rounded-xl border border-slate-200 bg-slate-100 text-sm font-bold text-slate-500 cursor-not-allowed"/>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="setShiftStartTime" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Start Time</label>
                                <input id="setShiftStartTime" type="time" step="60" value="09:00" class="schedule-input w-full py-3 px-4"/>
                            </div>
                            <div>
                                <label for="setShiftEndTime" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">End Time</label>
                                <input id="setShiftEndTime" type="time" step="60" value="17:00" class="schedule-input w-full py-3 px-4"/>
                            </div>
                        </div>
                        <div>
                            <p class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Repeat</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <label class="inline-flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-700 hover:border-primary/30 cursor-pointer">
                                    <input type="radio" name="setShiftRepeat" value="one_day" checked class="h-4 w-4 border-slate-300 text-primary focus:ring-primary/30"/>
                                    One Day
                                </label>
                                <label class="inline-flex items-center gap-2.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-bold text-slate-700 hover:border-primary/30 cursor-pointer">
                                    <input type="radio" name="setShiftRepeat" value="weekly" class="h-4 w-4 border-slate-300 text-primary focus:ring-primary/30"/>
                                    Weekly
                                </label>
                            </div>
                        </div>
                        <div>
                            <label for="setShiftNotes" class="block text-[10px] font-black text-on-surface-variant/60 uppercase tracking-[0.2em] mb-2">Notes</label>
                            <textarea id="setShiftNotes" rows="3" class="schedule-input w-full py-3 px-4 resize-y" placeholder="Optional text"></textarea>
                        </div>
                    </div>
                    <div class="pt-4 border-t border-slate-200 flex items-center justify-end gap-2.5">
                        <button id="cancelSetShiftBtn" type="button" class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 font-bold text-xs uppercase tracking-widest transition-colors">
                            Cancel
                        </button>
                        <button id="saveSetShiftBtn" type="button" class="inline-flex items-center justify-center px-5 py-2.5 rounded-xl bg-primary hover:bg-primary/90 text-white font-bold text-xs uppercase tracking-widest transition-colors shadow-sm">
                            Save
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
<script>
    (function () {
        const openSetShiftModalBtn = document.getElementById('openSetShiftModalBtn');
        const setShiftModal = document.getElementById('setShiftModal');
        const closeSetShiftModalBtn = document.getElementById('closeSetShiftModalBtn');
        const cancelSetShiftBtn = document.getElementById('cancelSetShiftBtn');
        const saveSetShiftBtn = document.getElementById('saveSetShiftBtn');
        const setShiftDentistId = document.getElementById('setShiftDentistId');
        const setShiftForm = document.getElementById('setShiftForm');
        const setShiftNotes = document.getElementById('setShiftNotes');
        const setShiftStartTime = document.getElementById('setShiftStartTime');
        const setShiftEndTime = document.getElementById('setShiftEndTime');
        const setShiftDate = document.getElementById('setShiftDate');
        const setShiftClinicHoursLabel = document.getElementById('setShiftClinicHoursLabel');
        const setShiftClinicOpenTime = document.getElementById('setShiftClinicOpenTime');
        const setShiftClinicCloseTime = document.getElementById('setShiftClinicCloseTime');
        const chooseDentistBtn = document.getElementById('chooseDentistBtn');
        const clearDentistBtn = document.getElementById('clearDentistBtn');
        const selectedDentistLabel = document.getElementById('selectedDentistLabel');
        const selectedDentistUserIdInput = document.getElementById('selectedDentistUserId');
        const selectedDentistIdInput = document.getElementById('selectedDentistId');
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
        const scheduleFilterForm = document.getElementById('scheduleFilterForm');
        const dentistsSeedData = <?php echo json_encode($dentistsSeedData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const stockDentistImage = 'https://images.unsplash.com/photo-1622253692010-333f2da6031d?auto=format&fit=crop&w=300&q=60';
        const clinicAssetBaseUrl = <?php echo json_encode(rtrim(BASE_URL, '/') . '/', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const todayDateOnly = <?php echo json_encode($todayDateOnly, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        let selectedClinicHoursForShift = {
            hasRecord: false,
            isClosed: false,
            openTimeRaw: '',
            closeTimeRaw: ''
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

            shiftTooltipDentistName.textContent = fullName;
            shiftTooltipTimeRange.textContent = timeRange;
            shiftInfoTooltip.classList.remove('hidden');
            shiftInfoTooltip.setAttribute('aria-hidden', 'false');
            positionShiftTooltip(target.getBoundingClientRect(), event.clientX, event.clientY);
            shiftInfoTooltip.classList.add('is-visible');
        }

        function syncModalBodyScrollLock() {
            const hasOpenModal = (chooseDentistModal && !chooseDentistModal.classList.contains('hidden'))
                || (setShiftModal && !setShiftModal.classList.contains('hidden'))
                || (pageAlertModal && !pageAlertModal.classList.contains('hidden'));
            document.body.classList.toggle('overflow-hidden', Boolean(hasOpenModal));
        }

        function toMinutes(timeValue) {
            const parts = String(timeValue || '').split(':');
            const hour = Number(parts[0] || 0);
            const minute = Number(parts[1] || 0);
            return (hour * 60) + minute;
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

        async function loadClinicHoursForDate(dateValue) {
            const normalizedDate = String(dateValue || '').trim();
            if (!setShiftClinicOpenTime || !setShiftClinicCloseTime || !setShiftClinicHoursLabel) return;
            if (!/^\d{4}-\d{2}-\d{2}$/.test(normalizedDate)) return;

            setShiftClinicHoursLabel.textContent = 'Clinic Hours for the day ' + formatDateForUiLabel(normalizedDate);
            setShiftClinicOpenTime.value = 'Loading...';
            setShiftClinicCloseTime.value = 'Loading...';
            selectedClinicHoursForShift = {
                hasRecord: false,
                isClosed: false,
                openTimeRaw: '',
                closeTimeRaw: ''
            };

            try {
                const params = new URLSearchParams({
                    clinic_hours_lookup: '1',
                    date: normalizedDate
                });
                const response = await fetch(window.location.pathname + '?' + params.toString(), {
                    method: 'GET',
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                if (!response.ok) {
                    throw new Error('Unable to load clinic hours.');
                }
                const payload = await response.json();
                if (!payload || payload.success !== true) {
                    throw new Error((payload && payload.message) ? payload.message : 'Unable to load clinic hours.');
                }

                setShiftClinicOpenTime.value = String(payload.open_time || '-');
                setShiftClinicCloseTime.value = String(payload.close_time || '-');
                selectedClinicHoursForShift = {
                    hasRecord: Boolean(payload.has_record),
                    isClosed: Boolean(payload.is_closed),
                    openTimeRaw: String(payload.open_time_raw || ''),
                    closeTimeRaw: String(payload.close_time_raw || '')
                };
            } catch (error) {
                setShiftClinicOpenTime.value = '-';
                setShiftClinicCloseTime.value = '-';
                showPageAlert((error && error.message) ? error.message : 'Unable to fetch clinic hours for the selected date.');
            }
        }

        function openSetShiftModal() {
            if (!setShiftModal) return;
            if (setShiftStartTime && !setShiftStartTime.value) {
                setShiftStartTime.value = '09:00';
            }
            if (setShiftEndTime && !setShiftEndTime.value) {
                setShiftEndTime.value = '17:00';
            }
            if (setShiftDate) {
                if (!setShiftDate.value || setShiftDate.value < todayDateOnly) {
                    setShiftDate.value = todayDateOnly;
                }
                loadClinicHoursForDate(setShiftDate.value);
            }
            setShiftModal.classList.remove('hidden');
            syncModalBodyScrollLock();
        }

        function closeSetShiftModal() {
            if (!setShiftModal) return;
            setShiftModal.classList.add('hidden');
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
        if (closeSetShiftModalBtn) {
            closeSetShiftModalBtn.addEventListener('click', closeSetShiftModal);
        }
        if (cancelSetShiftBtn) {
            cancelSetShiftBtn.addEventListener('click', closeSetShiftModal);
        }
        if (setShiftModal) {
            setShiftModal.addEventListener('click', function (event) {
                if (event.target === setShiftModal || event.target === setShiftModal.firstElementChild) {
                    closeSetShiftModal();
                }
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
                if (!setShiftStartTime || !setShiftEndTime || !setShiftDate || !setShiftDentistId || !setShiftForm) return;

                const selectedDate = String(setShiftDate.value || '');
                const selectedStart = String(setShiftStartTime.value || '');
                const selectedEnd = String(setShiftEndTime.value || '');
                const selectedDentistUserId = String(setShiftDentistId.value || '');
                const selectedDentistOption = setShiftDentistId.options[setShiftDentistId.selectedIndex] || null;
                const selectedDentistId = selectedDentistOption ? String(selectedDentistOption.getAttribute('data-dentist-id') || '') : '';
                const selectedDentistHasUserId = selectedDentistOption ? selectedDentistOption.getAttribute('data-has-user-id') === '1' : false;
                const selectedRepeatInput = setShiftForm.querySelector('input[name="setShiftRepeat"]:checked');
                const selectedRepeat = selectedRepeatInput ? String(selectedRepeatInput.value || 'one_day') : 'one_day';
                const notesValue = setShiftNotes ? String(setShiftNotes.value || '').trim() : '';

                if (!selectedDate || selectedDate < todayDateOnly) {
                    showPageAlert('Please select today or a future date.');
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
                if (!selectedStart || !selectedEnd) {
                    showPageAlert('Please select both shift start and end times.');
                    return;
                }
                if (toMinutes(selectedEnd) <= toMinutes(selectedStart)) {
                    showPageAlert('Shift end time must be later than shift start time.');
                    return;
                }
                if (!selectedClinicHoursForShift.hasRecord || !selectedClinicHoursForShift.openTimeRaw || !selectedClinicHoursForShift.closeTimeRaw) {
                    showPageAlert('Clinic hours are not set for this day. Please set clinic hours first before assigning shifts.');
                    return;
                }
                if (selectedClinicHoursForShift.isClosed) {
                    showPageAlert('The clinic is marked closed for this date. Shift scheduling is not allowed.');
                    return;
                }

                const clinicOpenMinutes = toMinutes(selectedClinicHoursForShift.openTimeRaw);
                const clinicCloseMinutes = toMinutes(selectedClinicHoursForShift.closeTimeRaw);
                const shiftStartMinutes = toMinutes(selectedStart);
                const shiftEndMinutes = toMinutes(selectedEnd);
                if (shiftStartMinutes < clinicOpenMinutes || shiftEndMinutes > clinicCloseMinutes) {
                    showPageAlert('Shift time must fall within clinic operating hours for the selected day.');
                    return;
                }
                const originalButtonLabel = saveSetShiftBtn.textContent;
                saveSetShiftBtn.disabled = true;
                saveSetShiftBtn.textContent = 'Saving...';
                try {
                    const payload = new URLSearchParams();
                    payload.append('save_set_shift', '1');
                    payload.append('dentist_id', selectedDentistId);
                    payload.append('dentist_user_id', selectedDentistUserId);
                    payload.append('shift_date', selectedDate);
                    payload.append('start_time', selectedStart);
                    payload.append('end_time', selectedEnd);
                    payload.append('repeat', selectedRepeat);
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
                        throw new Error((result && result.message) ? result.message : 'Failed to save shift.');
                    }

                    closeSetShiftModal();
                    const refreshedUrl = new URL(window.location.href);
                    refreshedUrl.searchParams.set('selected_date', selectedDate);
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
        if (setShiftDate) {
            setShiftDate.addEventListener('change', function () {
                if (setShiftDate.value < todayDateOnly) {
                    setShiftDate.value = todayDateOnly;
                }
                loadClinicHoursForDate(setShiftDate.value);
            });
        }
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

        window.addEventListener('scroll', hideShiftTooltip, { passive: true });
        window.addEventListener('resize', hideShiftTooltip);
    })();
</script>
</body>
</html>
