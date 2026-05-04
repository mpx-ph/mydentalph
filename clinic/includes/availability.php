<?php
/**
 * Shared availability helpers for staff/dentist assignment checks.
 */

require_once __DIR__ . '/appointment_db_tables.php';

if (!function_exists('clinic_time_overlaps')) {
    /**
     * Overlap check: (startA < endB) AND (endA > startB)
     *
     * @param string $startA
     * @param string $endA
     * @param string $startB
     * @param string $endB
     * @return bool
     */
    function clinic_time_overlaps($startA, $endA, $startB, $endB)
    {
        return ((string) $startA < (string) $endB) && ((string) $endA > (string) $startB);
    }
}

if (!function_exists('clinic_is_valid_time_range')) {
    /**
     * @param string $startTime
     * @param string $endTime
     * @return bool
     */
    function clinic_is_valid_time_range($startTime, $endTime)
    {
        return (string) $startTime < (string) $endTime;
    }
}

if (!function_exists('clinic_normalize_schedule_blocks')) {
    /**
     * Removes invalid/duplicate active blocks so availability checks stay stable.
     *
     * @param array<int,array<string,mixed>> $blocks
     * @return array<int,array<string,mixed>>
     */
    function clinic_normalize_schedule_blocks(array $blocks)
    {
        $normalized = [];
        $seen = [];
        foreach ($blocks as $block) {
            $start = (string) ($block['start_time'] ?? '');
            $end = (string) ($block['end_time'] ?? '');
            $type = strtolower((string) ($block['block_type'] ?? ''));
            if ($start === '' || $end === '' || !clinic_is_valid_time_range($start, $end)) {
                continue;
            }
            $key = $type . '|' . $start . '|' . $end;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $normalized[] = $block;
        }
        return $normalized;
    }
}

if (!function_exists('clinic_get_effective_schedule_blocks')) {
    /**
     * Resolve schedule blocks with date-specific priority then recurring fallback.
     *
     * @param PDO $pdo
     * @param string $tenantId
     * @param string $userId
     * @param string $slotDate Format: Y-m-d
     * @return array<int,array<string,mixed>>
     */
    function clinic_get_effective_schedule_blocks($pdo, $tenantId, $userId, $slotDate)
    {
        $scheduleBlocksTable = function_exists('clinic_get_physical_table_name')
            ? (clinic_get_physical_table_name($pdo, 'tbl_schedule_blocks')
                ?? clinic_get_physical_table_name($pdo, 'schedule_blocks'))
            : 'tbl_schedule_blocks';
        if ($scheduleBlocksTable === null) {
            return [];
        }
        $quotedScheduleBlocksTable = '`' . str_replace('`', '``', (string) $scheduleBlocksTable) . '`';

        $dateSpecificBlocksStmt = $pdo->prepare("
            SELECT start_time, end_time, block_type, notes
            FROM {$quotedScheduleBlocksTable}
            WHERE tenant_id = ?
              AND user_id = ?
              AND is_active = 1
              AND block_date = ?
            ORDER BY start_time ASC
        ");
        $dateSpecificBlocksStmt->execute([(string) $tenantId, (string) $userId, (string) $slotDate]);
        $dateBlocks = $dateSpecificBlocksStmt->fetchAll(PDO::FETCH_ASSOC);

        $slotDayOfWeek = date('l', strtotime((string) $slotDate));
        $recurringBlocksStmt = $pdo->prepare("
            SELECT start_time, end_time, block_type, notes
            FROM {$quotedScheduleBlocksTable}
            WHERE tenant_id = ?
              AND user_id = ?
              AND is_active = 1
              AND day_of_week = ?
              AND (block_date IS NULL OR block_date = '0000-00-00')
            ORDER BY start_time ASC
        ");
        $recurringBlocksStmt->execute([(string) $tenantId, (string) $userId, (string) $slotDayOfWeek]);
        $recurringBlocks = $recurringBlocksStmt->fetchAll(PDO::FETCH_ASSOC);

        /**
         * StaffScheduling shows date-specific rows AND recurring rows together; booking must match.
         * Previously, any date-specific row (e.g. one-off break) replaced the entire day and hid recurring shifts.
         */
        $partition = static function (array $blocks): array {
            $shifts = [];
            $obstacles = [];
            foreach ($blocks as $block) {
                $t = strtolower((string) ($block['block_type'] ?? ''));
                if (in_array($t, ['shift', 'work'], true)) {
                    $shifts[] = $block;
                } elseif (in_array($t, ['break', 'blocked'], true)) {
                    $obstacles[] = $block;
                }
            }

            return [$shifts, $obstacles];
        };

        [$dateShifts, $dateObstacles] = $partition($dateBlocks);
        [$recShifts, $recObstacles] = $partition($recurringBlocks);

        $shiftLayer = !empty($dateShifts) ? $dateShifts : $recShifts;
        $merged = array_merge($shiftLayer, $dateObstacles, $recObstacles);

        return clinic_normalize_schedule_blocks($merged);
    }
}

if (!function_exists('clinic_get_appointments_for_user_date')) {
    /**
     * Returns non-cancelled appointments for a user and date.
     *
     * @param PDO $pdo
     * @param string $tenantId
     * @param string $userId
     * @param string $slotDate Format: Y-m-d
     * @return array<int,array<string,mixed>>
     */
    function clinic_get_appointments_for_user_date($pdo, $tenantId, $userId, $slotDate)
    {
        $appointmentsTable = function_exists('clinic_get_physical_table_name')
            ? (clinic_get_physical_table_name($pdo, 'tbl_appointments')
                ?? clinic_get_physical_table_name($pdo, 'appointments'))
            : 'tbl_appointments';
        $dentistsTable = function_exists('clinic_get_physical_table_name')
            ? (clinic_get_physical_table_name($pdo, 'tbl_dentists')
                ?? clinic_get_physical_table_name($pdo, 'dentists'))
            : 'tbl_dentists';
        if ($appointmentsTable === null || $dentistsTable === null) {
            return [];
        }
        $quotedAppointmentsTable = '`' . str_replace('`', '``', (string) $appointmentsTable) . '`';
        $quotedDentistsTable = '`' . str_replace('`', '``', (string) $dentistsTable) . '`';

        $appointmentsStmt = $pdo->prepare("
            SELECT
                a.appointment_time AS start_time,
                ADDTIME(a.appointment_time, '01:00:00') AS end_time
            FROM {$quotedAppointmentsTable} a
            INNER JOIN {$quotedDentistsTable} d
                ON d.tenant_id = a.tenant_id
               AND d.dentist_id = a.dentist_id
            WHERE a.tenant_id = ?
              AND d.user_id = ?
              AND a.appointment_date = ?
              AND LOWER(COALESCE(a.status, 'pending')) <> 'cancelled'
        ");
        $appointmentsStmt->execute([(string) $tenantId, (string) $userId, (string) $slotDate]);
        return $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('clinic_get_availability_details')) {
    /**
     * Detailed availability verdict.
     *
     * @param string $userId
     * @param string $datetimeStart
     * @param string $datetimeEnd
     * @param string|null $tenantId
     * @return array{available:bool, reason:string}
     */
    function clinic_get_availability_details($userId, $datetimeStart, $datetimeEnd, $tenantId = null)
    {
        $resolvedTenantId = ($tenantId !== null && trim((string) $tenantId) !== '')
            ? trim((string) $tenantId)
            : (isset($_SESSION['tenant_id']) ? trim((string) $_SESSION['tenant_id']) : '');

        if ($resolvedTenantId === '' || trim((string) $userId) === '') {
            return ['available' => false, 'reason' => 'Outside Shift'];
        }

        try {
            $start = new DateTime((string) $datetimeStart);
            $end = new DateTime((string) $datetimeEnd);
        } catch (Exception $e) {
            return ['available' => false, 'reason' => 'Outside Shift'];
        }

        if ($end <= $start) {
            return ['available' => false, 'reason' => 'Outside Shift'];
        }

        $slotDate = $start->format('Y-m-d');
        $slotStartTime = $start->format('H:i:s');
        $slotEndTime = $end->format('H:i:s');

        try {
            $pdo = getDBConnection();
            $effectiveBlocks = clinic_get_effective_schedule_blocks($pdo, $resolvedTenantId, (string) $userId, $slotDate);
        } catch (Throwable $e) {
            return ['available' => false, 'reason' => 'Outside Shift'];
        }

        // Priority order:
        // 1) BREAK (always blocks)
        // 2) APPOINTMENT
        // 3) WORK SHIFT (availability window)
        foreach ($effectiveBlocks as $block) {
            $blockType = strtolower((string) ($block['block_type'] ?? ''));
            if ($blockType !== 'break') {
                continue;
            }
            $breakStart = (string) ($block['start_time'] ?? '');
            $breakEnd = (string) ($block['end_time'] ?? '');
            if (clinic_time_overlaps($slotStartTime, $slotEndTime, $breakStart, $breakEnd)) {
                return ['available' => false, 'reason' => 'Break'];
            }
        }

        try {
            $appointments = clinic_get_appointments_for_user_date($pdo, $resolvedTenantId, (string) $userId, $slotDate);
        } catch (Throwable $e) {
            return ['available' => false, 'reason' => 'Already Booked'];
        }
        foreach ($appointments as $appointment) {
            $appointmentStart = (string) ($appointment['start_time'] ?? '');
            $appointmentEnd = (string) ($appointment['end_time'] ?? '');
            if (clinic_time_overlaps($slotStartTime, $slotEndTime, $appointmentStart, $appointmentEnd)) {
                return ['available' => false, 'reason' => 'Already Booked'];
            }
        }

        $insideWorkShift = false;
        foreach ($effectiveBlocks as $block) {
            $blockType = strtolower((string) ($block['block_type'] ?? ''));
            if (!in_array($blockType, ['work', 'shift'], true)) {
                continue;
            }
            $blockStart = (string) ($block['start_time'] ?? '');
            $blockEnd = (string) ($block['end_time'] ?? '');
            if ($slotStartTime >= $blockStart && $slotEndTime <= $blockEnd) {
                $insideWorkShift = true;
                break;
            }
        }
        if (!$insideWorkShift) {
            return ['available' => false, 'reason' => 'Outside Shift'];
        }

        return ['available' => true, 'reason' => 'Available'];
    }
}

if (!function_exists('clinic_assert_user_available_atomic')) {
    /**
     * Re-check availability under a per-user/day DB lock to reduce race conditions.
     *
     * @param PDO $pdo
     * @param string $tenantId
     * @param string $userId
     * @param string $slotStartDatetime
     * @param string $slotEndDatetime
     * @return array{available:bool, reason:string}
     */
    function clinic_assert_user_available_atomic($pdo, $tenantId, $userId, $slotStartDatetime, $slotEndDatetime)
    {
        try {
            $start = new DateTime((string) $slotStartDatetime);
            $lockDate = $start->format('Y-m-d');
        } catch (Exception $e) {
            return ['available' => false, 'reason' => 'Invalid time range'];
        }

        $lockName = 'sched:' . trim((string) $tenantId) . ':' . trim((string) $userId) . ':' . $lockDate;
        $lockStmt = $pdo->prepare("SELECT GET_LOCK(?, 5) AS lock_ok");
        $lockStmt->execute([$lockName]);
        $lockOk = (int) $lockStmt->fetchColumn();
        if ($lockOk !== 1) {
            return ['available' => false, 'reason' => 'Scheduling lock busy'];
        }

        try {
            return clinic_get_availability_details((string) $userId, (string) $slotStartDatetime, (string) $slotEndDatetime, (string) $tenantId);
        } finally {
            try {
                $unlockStmt = $pdo->prepare("SELECT RELEASE_LOCK(?)");
                $unlockStmt->execute([$lockName]);
            } catch (Throwable $unlockError) {
                // no-op
            }
        }
    }
}

if (!function_exists('isUserAvailable')) {
    /**
     * Required boolean wrapper.
     *
     * @param string $userId
     * @param string $datetimeStart
     * @param string $datetimeEnd
     * @return bool
     */
    function isUserAvailable($userId, $datetimeStart, $datetimeEnd)
    {
        $details = clinic_get_availability_details($userId, $datetimeStart, $datetimeEnd);
        return !empty($details['available']);
    }
}

