<?php
/**
 * Public booking grid: tbl_clinic_hours + tbl_schedule_blocks + tbl_appointments.
 */

require_once __DIR__ . '/appointment_db_tables.php';
require_once __DIR__ . '/availability.php';

if (!function_exists('patient_booking_normalize_time')) {
    /** @param mixed $t */
    function patient_booking_normalize_time($t): ?string
    {
        $s = trim((string) $t);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $s)) {
            return $s;
        }
        if (preg_match('/^\d{1,2}:\d{2}$/', $s)) {
            $parts = explode(':', $s, 2);
            $h = (int) $parts[0];
            $m = (int) ($parts[1] ?? 0);

            return sprintf('%02d:%02d:00', max(0, min(23, $h)), max(0, min(59, $m)));
        }

        return null;
    }
}

if (!function_exists('patient_booking_dentist_user_id')) {
    /**
     * Resolve the staff user_id used by tbl_schedule_blocks.
     * Matches StaffWalkIn.php / StaffSetAppointments.php: prefer tbl_dentists.user_id, else tbl_users.user_id
     * where email matches and role is dentist (handles NULL user_id on dentist rows).
     */
    function patient_booking_dentist_user_id(PDO $pdo, string $tenantId, int $dentistId): ?string
    {
        $dentistsTable = clinic_get_physical_table_name($pdo, 'tbl_dentists')
            ?? clinic_get_physical_table_name($pdo, 'dentists');
        if ($dentistsTable === null) {
            return null;
        }
        $qD = clinic_quote_identifier($dentistsTable);
        $usersTable = clinic_get_physical_table_name($pdo, 'tbl_users')
            ?? clinic_get_physical_table_name($pdo, 'users');

        if ($usersTable !== null) {
            $qU = clinic_quote_identifier($usersTable);
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(NULLIF(TRIM(d.user_id), ''), NULLIF(TRIM(u.user_id), ''), '') AS resolved_user_id
                FROM {$qD} d
                LEFT JOIN {$qU} u
                    ON u.tenant_id = d.tenant_id
                    AND LOWER(TRIM(COALESCE(u.email, ''))) = LOWER(TRIM(COALESCE(d.email, '')))
                    AND u.role = 'dentist'
                WHERE d.tenant_id = ? AND d.dentist_id = ?
                LIMIT 1
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT NULLIF(TRIM(user_id), '') AS resolved_user_id
                FROM {$qD}
                WHERE tenant_id = ? AND dentist_id = ?
                LIMIT 1
            ");
        }
        $stmt->execute([$tenantId, $dentistId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $uid = trim((string) ($row['resolved_user_id'] ?? ''));
        if ($uid === '') {
            return null;
        }

        return $uid;
    }
}

if (!function_exists('patient_booking_clinic_hours_for_date')) {
    /**
     * @return array{is_closed:bool, open:?string, close:?string}
     */
    function patient_booking_clinic_hours_for_date(PDO $pdo, string $tenantId, string $dateYmd): array
    {
        $defaultsByDow = [
            0 => ['open' => '09:00:00', 'close' => '17:00:00', 'closed' => false],
            1 => ['open' => '08:00:00', 'close' => '17:00:00', 'closed' => false],
            2 => ['open' => '08:00:00', 'close' => '17:00:00', 'closed' => false],
            3 => ['open' => '08:00:00', 'close' => '17:00:00', 'closed' => false],
            4 => ['open' => '08:00:00', 'close' => '17:00:00', 'closed' => false],
            5 => ['open' => '08:00:00', 'close' => '17:00:00', 'closed' => false],
            6 => ['open' => '09:00:00', 'close' => '15:00:00', 'closed' => false],
        ];

        $tz = new DateTimeZone('Asia/Manila');
        $dto = DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd, $tz);
        if (!($dto instanceof DateTimeImmutable)) {
            return ['is_closed' => true, 'open' => null, 'close' => null];
        }
        $dow = (int) $dto->format('w');
        $def = $defaultsByDow[$dow] ?? $defaultsByDow[1];

        $hoursTable = clinic_get_physical_table_name($pdo, 'tbl_clinic_hours');
        if ($hoursTable === null) {
            if (!empty($def['closed'])) {
                return ['is_closed' => true, 'open' => null, 'close' => null];
            }

            return ['is_closed' => false, 'open' => $def['open'], 'close' => $def['close']];
        }
        $qh = clinic_quote_identifier($hoursTable);

        $stmt2 = $pdo->prepare("SELECT open_time, close_time, is_closed FROM {$qh} WHERE tenant_id = ? AND clinic_date IS NULL AND day_of_week = ? LIMIT 1");
        $stmt2->execute([$tenantId, $dow]);
        $row2 = $stmt2->fetch(PDO::FETCH_ASSOC);
        if (is_array($row2) && !empty($row2)) {
            $closed2 = isset($row2['is_closed']) && (int) $row2['is_closed'] === 1;
            if ($closed2) {
                return ['is_closed' => true, 'open' => null, 'close' => null];
            }
            $o = patient_booking_normalize_time($row2['open_time'] ?? null);
            $c = patient_booking_normalize_time($row2['close_time'] ?? null);
            if ($o === null || $c === null || $o >= $c) {
                return ['is_closed' => true, 'open' => null, 'close' => null];
            }

            return ['is_closed' => false, 'open' => $o, 'close' => $c];
        }

        if (!empty($def['closed'])) {
            return ['is_closed' => true, 'open' => null, 'close' => null];
        }

        return ['is_closed' => false, 'open' => $def['open'], 'close' => $def['close']];
    }
}

if (!function_exists('patient_booking_dentist_has_shift')) {
    function patient_booking_dentist_has_shift(PDO $pdo, string $tenantId, string $userId, string $dateYmd): bool
    {
        foreach (clinic_get_effective_schedule_blocks($pdo, $tenantId, $userId, $dateYmd) as $b) {
            $t = strtolower((string) ($b['block_type'] ?? ''));
            if (in_array($t, ['shift', 'work'], true)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('patient_booking_fetch_appointment_ranges')) {
    /**
     * @return array<int, array{start_time:string,end_time:string}>
     */
    function patient_booking_fetch_appointment_ranges(PDO $pdo, string $tenantId, int $dentistId, string $dateYmd): array
    {
        $tables = clinic_resolve_appointment_db_tables($pdo);
        $apt = $tables['appointments'] ?? null;
        if ($apt === null) {
            return [];
        }
        $q = clinic_quote_identifier($apt);

        try {
            $stmt = $pdo->prepare("
                SELECT appointment_time AS start_time, ADDTIME(appointment_time, '01:00:00') AS end_time
                FROM {$q}
                WHERE tenant_id = ?
                  AND dentist_id = ?
                  AND DATE(appointment_date) = ?
                  AND LOWER(COALESCE(status, '')) <> 'cancelled'
            ");
            $stmt->execute([$tenantId, $dentistId, $dateYmd]);
        } catch (Throwable $e) {
            return [];
        }

        return array_map(static function ($row) {
            return [
                'start_time' => (string) ($row['start_time'] ?? ''),
                'end_time' => (string) ($row['end_time'] ?? ''),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
}

if (!function_exists('patient_booking_hour_is_bookable')) {
    /**
     * One-hour slot [H:00, H+1:00) must fit clinic hours, a shift/work block, and avoid breaks/blocks + appointments.
     */
    function patient_booking_hour_is_bookable(
        PDO $pdo,
        string $tenantId,
        int $dentistId,
        string $dentistUserId,
        string $dateYmd,
        int $hour
    ): bool {
        if ($hour < 0 || $hour > 23) {
            return false;
        }
        $ch = patient_booking_clinic_hours_for_date($pdo, $tenantId, $dateYmd);
        if ($ch['is_closed'] || empty($ch['open']) || empty($ch['close'])) {
            return false;
        }
        $slotStart = sprintf('%02d:00:00', $hour);
        $slotEnd = sprintf('%02d:00:00', $hour + 1);
        if ($slotStart < $ch['open'] || $slotEnd > $ch['close']) {
            return false;
        }

        $blocks = clinic_get_effective_schedule_blocks($pdo, $tenantId, $dentistUserId, $dateYmd);
        $shifts = [];
        $obstacles = [];
        foreach ($blocks as $b) {
            $type = strtolower((string) ($b['block_type'] ?? ''));
            $st = patient_booking_normalize_time($b['start_time'] ?? null);
            $en = patient_booking_normalize_time($b['end_time'] ?? null);
            if ($st === null || $en === null || $st >= $en) {
                continue;
            }
            if (in_array($type, ['shift', 'work'], true)) {
                $shifts[] = ['s' => $st, 'e' => $en];
            } elseif (in_array($type, ['break', 'blocked'], true)) {
                $obstacles[] = ['s' => $st, 'e' => $en];
            }
        }
        $inShift = false;
        foreach ($shifts as $sh) {
            if ($slotStart >= $sh['s'] && $slotEnd <= $sh['e']) {
                $inShift = true;
                break;
            }
        }
        if (!$inShift) {
            return false;
        }
        foreach ($obstacles as $ob) {
            if (clinic_time_overlaps($slotStart, $slotEnd, $ob['s'], $ob['e'])) {
                return false;
            }
        }
        foreach (patient_booking_fetch_appointment_ranges($pdo, $tenantId, $dentistId, $dateYmd) as $ap) {
            $as = patient_booking_normalize_time($ap['start_time'] ?? null);
            $ae = patient_booking_normalize_time($ap['end_time'] ?? null);
            if ($as !== null && $ae !== null && clinic_time_overlaps($slotStart, $slotEnd, $as, $ae)) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('patient_booking_day_selectable_meta')) {
    /**
     * @return array{ok:bool, code:string}
     */
    function patient_booking_day_selectable_meta(PDO $pdo, string $tenantId, int $dentistId, string $dateYmd): array
    {
        $hours = patient_booking_clinic_hours_for_date($pdo, $tenantId, $dateYmd);
        if ($hours['is_closed']) {
            return ['ok' => false, 'code' => 'clinic_closed'];
        }
        $uid = patient_booking_dentist_user_id($pdo, $tenantId, $dentistId);
        if ($uid === null) {
            return ['ok' => false, 'code' => 'dentist_unresolved'];
        }
        if (!patient_booking_dentist_has_shift($pdo, $tenantId, $uid, $dateYmd)) {
            return ['ok' => false, 'code' => 'no_schedule_block'];
        }

        return ['ok' => true, 'code' => 'ok'];
    }
}

if (!function_exists('patient_booking_day_selectable_clinic_only')) {
    /**
     * Calendar hint before a dentist is chosen: only tbl_clinic_hours (no dentist shift required).
     *
     * @return array{ok:bool, code:string}
     */
    function patient_booking_day_selectable_clinic_only(PDO $pdo, string $tenantId, string $dateYmd): array
    {
        $hours = patient_booking_clinic_hours_for_date($pdo, $tenantId, $dateYmd);
        if ($hours['is_closed']) {
            return ['ok' => false, 'code' => 'clinic_closed'];
        }

        return ['ok' => true, 'code' => 'ok'];
    }
}

if (!function_exists('patient_booking_slots_clinic_hours_only')) {
    /**
     * One-hour ticks within tbl_clinic_hours only (all selectable — dentist chosen afterward).
     *
     * @return list<array{hour:int, available:bool}>
     */
    function patient_booking_slots_clinic_hours_only(PDO $pdo, string $tenantId, string $dateYmd): array
    {
        $ch = patient_booking_clinic_hours_for_date($pdo, $tenantId, $dateYmd);
        if ($ch['is_closed'] || empty($ch['open']) || empty($ch['close'])) {
            return [];
        }
        $out = [];
        for ($h = 0; $h < 24; ++$h) {
            $slotStart = sprintf('%02d:00:00', $h);
            $slotEnd = sprintf('%02d:00:00', $h + 1);
            if ($slotStart < $ch['open'] || $slotEnd > $ch['close']) {
                continue;
            }
            $out[] = ['hour' => $h, 'available' => true];
        }

        return $out;
    }
}

if (!function_exists('patient_booking_list_dentists_for_hour')) {
    /**
     * Dentists whose shift (+ breaks/bookings for that dentist) allows this hourly slot on this date.
     *
     * @return list<array{dentist_id:int, first_name:string, last_name:string}>
     */
    function patient_booking_list_dentists_for_hour(PDO $pdo, string $tenantId, string $dateYmd, int $hour): array
    {
        if ($hour < 0 || $hour > 23) {
            return [];
        }
        $ch = patient_booking_clinic_hours_for_date($pdo, $tenantId, $dateYmd);
        if ($ch['is_closed']) {
            return [];
        }

        $dentistsTable = clinic_get_physical_table_name($pdo, 'tbl_dentists')
            ?? clinic_get_physical_table_name($pdo, 'dentists');
        if ($dentistsTable === null) {
            return [];
        }
        $qD = clinic_quote_identifier((string) $dentistsTable);
        $cols = clinic_table_columns($pdo, (string) $dentistsTable);
        $hasStatus = in_array('status', $cols, true);
        $hasIsActive = in_array('is_active', $cols, true);

        $sql = "SELECT dentist_id, first_name, last_name FROM {$qD} WHERE tenant_id = ?";
        if ($hasStatus) {
            // NULL/blank status must count as active (schema default + legacy rows); strict '' = 'active' hid every such dentist.
            $sql .= " AND LOWER(COALESCE(NULLIF(TRIM(COALESCE(status, '')), ''), 'active')) = 'active'";
        } elseif ($hasIsActive) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY last_name ASC, first_name ASC';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!isset($row['dentist_id'])) {
                continue;
            }
            $did = (int) $row['dentist_id'];
            if ($did <= 0) {
                continue;
            }
            $uid = patient_booking_dentist_user_id($pdo, $tenantId, $did);
            if ($uid === null) {
                continue;
            }
            if (!patient_booking_hour_is_bookable($pdo, $tenantId, $did, $uid, $dateYmd, $hour)) {
                continue;
            }
            $out[] = [
                'dentist_id' => $did,
                'first_name' => (string) ($row['first_name'] ?? ''),
                'last_name' => (string) ($row['last_name'] ?? ''),
            ];
        }

        return $out;
    }
}

if (!function_exists('patient_booking_slots_for_day')) {
    /**
     * Hourly chips within clinic hours (+ shift rules); each carries availability.
     *
     * @return list<array{hour:int, available:bool}>
     */
    function patient_booking_slots_for_day(PDO $pdo, string $tenantId, int $dentistId, string $dateYmd): array
    {
        $uid = patient_booking_dentist_user_id($pdo, $tenantId, $dentistId);
        if ($uid === null) {
            return [];
        }
        $ch = patient_booking_clinic_hours_for_date($pdo, $tenantId, $dateYmd);
        if ($ch['is_closed'] || empty($ch['open']) || empty($ch['close'])) {
            return [];
        }

        $out = [];
        for ($h = 0; $h < 24; ++$h) {
            $slotStart = sprintf('%02d:00:00', $h);
            $slotEnd = sprintf('%02d:00:00', $h + 1);
            if ($slotStart < $ch['open'] || $slotEnd > $ch['close']) {
                continue;
            }
            $out[] = [
                'hour' => $h,
                'available' => patient_booking_hour_is_bookable($pdo, $tenantId, $dentistId, $uid, $dateYmd, $h),
            ];
        }

        return $out;
    }
}

if (!function_exists('patient_booking_slot_available_at_time')) {
    /**
     * @param string $appointmentTime "HH:MM:SS" or "HH:MM"
     */
    function patient_booking_slot_available_at_time(
        PDO $pdo,
        string $tenantId,
        int $dentistId,
        string $appointmentDateYmd,
        string $appointmentTime
    ): bool {
        $norm = patient_booking_normalize_time($appointmentTime);
        if ($norm === null) {
            return false;
        }
        $hour = (int) substr($norm, 0, 2);
        $uid = patient_booking_dentist_user_id($pdo, $tenantId, $dentistId);
        if ($uid === null) {
            return false;
        }

        return patient_booking_hour_is_bookable($pdo, $tenantId, $dentistId, $uid, $appointmentDateYmd, $hour);
    }
}
