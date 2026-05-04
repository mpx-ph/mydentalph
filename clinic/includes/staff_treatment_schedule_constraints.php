<?php

declare(strict_types=1);

require_once __DIR__ . '/appointment_db_tables.php';

/**
 * Plan-scoped reference dates used by Treatment Progress / 28-day follow-up rules.
 *
 * @return array{
 *     candidate_dates:list<string>,
 *     canonical_booking:string,
 *     has_treatment_id:bool,
 *     treatment_id:string,
 *     qa:string,
 *     has_appt_date:bool,
 *     can_scope_appt:bool
 * }
 */
function staff_treatment_schedule_reference_context(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    string $treatmentId,
    string $bookingIdHint
): array {
    $tenantId = trim($tenantId);
    $patientId = trim($patientId);
    $treatmentId = trim($treatmentId);
    $bookingIdHint = trim($bookingIdHint);

    $empty = [
        'candidate_dates' => [],
        'canonical_booking' => '',
        'has_treatment_id' => false,
        'treatment_id' => $treatmentId,
        'qa' => '',
        'has_appt_date' => false,
        'can_scope_appt' => false,
    ];
    if ($tenantId === '' || $patientId === '') {
        return $empty;
    }

    $tables = clinic_resolve_appointment_db_tables($pdo);
    $aTable = $tables['appointments'] ?? null;
    if ($aTable === null || trim((string) $aTable) === '') {
        return $empty;
    }

    $qa = clinic_quote_identifier((string) $aTable);
    $apptCols = clinic_table_columns($pdo, (string) $aTable);
    $hasTreatmentId = in_array('treatment_id', $apptCols, true);
    $hasApptDate = in_array('appointment_date', $apptCols, true);
    if (!$hasApptDate) {
        return array_merge($empty, ['qa' => $qa, 'has_treatment_id' => $hasTreatmentId]);
    }

    $canonicalBooking = $bookingIdHint;
    if ($canonicalBooking === '' && $hasTreatmentId && $treatmentId !== '') {
        try {
            $orderCol = in_array('created_at', $apptCols, true) ? 'a.created_at' : 'a.appointment_date';
            $bStmt = $pdo->prepare("
                SELECT TRIM(COALESCE(a.booking_id, '')) AS bid
                FROM {$qa} a
                WHERE a.tenant_id = ?
                  AND TRIM(COALESCE(a.patient_id, '')) = ?
                  AND TRIM(COALESCE(a.treatment_id, '')) = ?
                  AND TRIM(COALESCE(a.booking_id, '')) <> ''
                ORDER BY {$orderCol} DESC
                LIMIT 1
            ");
            $bStmt->execute([$tenantId, $patientId, $treatmentId]);
            $br = $bStmt->fetch(PDO::FETCH_ASSOC);
            if ($br && trim((string) ($br['bid'] ?? '')) !== '') {
                $canonicalBooking = trim((string) $br['bid']);
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $candidateYmd = [];

    $pushYmd = static function (string $raw) use (&$candidateYmd): void {
        $raw = trim($raw);
        if ($raw === '' || strpos($raw, '0000-00-00') === 0) {
            return;
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            $candidateYmd[$m[1]] = true;
        }
    };

    if ($canonicalBooking !== '') {
        $instName = clinic_get_physical_table_name($pdo, 'tbl_installments')
            ?? clinic_get_physical_table_name($pdo, 'installments');
        if ($instName !== null && trim((string) $instName) !== '') {
            $qi = clinic_quote_identifier((string) $instName);
            try {
                $instCols = clinic_table_columns($pdo, (string) $instName);
                if (in_array('scheduled_date', $instCols, true)) {
                    $tClause = '(i.tenant_id = ? OR i.tenant_id IS NULL OR TRIM(COALESCE(i.tenant_id, \'\')) = \'\')';
                    $iStmt = $pdo->prepare("
                        SELECT i.scheduled_date
                        FROM {$qi} i
                        WHERE i.booking_id = ?
                          AND {$tClause}
                          AND TRIM(COALESCE(i.scheduled_date, '')) <> ''
                          AND TRIM(COALESCE(i.scheduled_date, '')) NOT LIKE '0000-00-00%'
                    ");
                    $iStmt->execute([$canonicalBooking, $tenantId]);
                    foreach ($iStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                        $pushYmd((string) ($row['scheduled_date'] ?? ''));
                    }
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    $canScopeAppt = ($canonicalBooking !== '' || ($hasTreatmentId && $treatmentId !== ''));
    if ($canScopeAppt) {
        try {
            $w = ['a.tenant_id = ?', "TRIM(COALESCE(a.patient_id, '')) = ?"];
            $params = [$tenantId, $patientId];
            $or = [];
            if ($canonicalBooking !== '') {
                $or[] = 'TRIM(COALESCE(a.booking_id, \'\')) = ?';
                $params[] = $canonicalBooking;
            }
            if ($hasTreatmentId && $treatmentId !== '') {
                $or[] = 'TRIM(COALESCE(a.treatment_id, \'\')) = ?';
                $params[] = $treatmentId;
            }
            if ($or !== []) {
                $w[] = '(' . implode(' OR ', $or) . ')';
            }
            $sql = "
                SELECT a.appointment_date
                FROM {$qa} a
                WHERE " . implode(' AND ', $w) . "
                  AND TRIM(COALESCE(a.appointment_date, '')) <> ''
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $pushYmd((string) ($row['appointment_date'] ?? ''));
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    $apsTable = $tables['appointment_services'] ?? null;
    if ($apsTable !== null && trim((string) $apsTable) !== '' && $canScopeAppt) {
        $apsCols = clinic_table_columns($pdo, (string) $apsTable);
        if (in_array('service_type', $apsCols, true) && in_array('booking_id', $apsCols, true)) {
            $qaps = clinic_quote_identifier((string) $apsTable);
            try {
                $w = [
                    'a.tenant_id = ?',
                    "TRIM(COALESCE(a.patient_id, '')) = ?",
                    "LOWER(TRIM(COALESCE(aps.service_type, ''))) = 'included_plan'",
                    'aps.tenant_id = a.tenant_id',
                    'TRIM(COALESCE(aps.booking_id, \'\')) = TRIM(COALESCE(a.booking_id, \'\'))',
                ];
                $params = [$tenantId, $patientId];
                $or = [];
                if ($canonicalBooking !== '') {
                    $or[] = 'TRIM(COALESCE(a.booking_id, \'\')) = ?';
                    $params[] = $canonicalBooking;
                }
                if ($hasTreatmentId && $treatmentId !== '') {
                    $or[] = 'TRIM(COALESCE(a.treatment_id, \'\')) = ?';
                    $params[] = $treatmentId;
                }
                if ($or !== []) {
                    $w[] = '(' . implode(' OR ', $or) . ')';
                }
                $sql = "
                    SELECT a.appointment_date
                    FROM {$qa} a
                    INNER JOIN {$qaps} aps ON aps.tenant_id = a.tenant_id
                        AND TRIM(COALESCE(aps.booking_id, '')) = TRIM(COALESCE(a.booking_id, ''))
                    WHERE " . implode(' AND ', $w) . "
                      AND TRIM(COALESCE(a.appointment_date, '')) <> ''
                ";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $pushYmd((string) ($row['appointment_date'] ?? ''));
                }
            } catch (Throwable $e) {
                // ignore
            }
        }
    }

    $dates = array_keys($candidateYmd);
    sort($dates);

    return [
        'candidate_dates' => $dates,
        'canonical_booking' => $canonicalBooking,
        'has_treatment_id' => $hasTreatmentId,
        'treatment_id' => $treatmentId,
        'qa' => $qa,
        'has_appt_date' => true,
        'can_scope_appt' => $canScopeAppt,
    ];
}

/**
 * Minimum appointment date for the next installment follow-up: latest reference date + 28 days (Asia/Manila).
 * Reference dates: installment scheduled slots for the plan booking + appointment rows scoped to patient + plan +
 * appointments that include included_plan lines for the same plan scope.
 *
 * @return ''|numeric-string|null null => no tighter rule than caller default (usually "today only")
 */
function staff_treatment_schedule_min_date_after_gap(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    string $treatmentId,
    string $bookingIdHint,
    int $gapDays = 28
): ?string
{
    $tz = new DateTimeZone('Asia/Manila');
    $gapDays = max(1, $gapDays);
    $ctx = staff_treatment_schedule_reference_context($pdo, $tenantId, $patientId, $treatmentId, $bookingIdHint);
    $dates = $ctx['candidate_dates'] ?? [];
    if ($dates === []) {
        return null;
    }
    $latest = (string) end($dates);
    if ($latest === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $latest)) {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($latest . ' 00:00:00', $tz);

        return $dt->modify('+' . $gapDays . ' days')->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Included-plan monthly services already booked for the current 28-day cycle (Treatment Progress semantics).
 *
 * Uses the same reference timeline as {@see staff_treatment_schedule_min_date_after_gap}: cycle starts at
 * (latest reference date + gapDays), inclusive window of gapDays calendar days.
 *
 * @return array{
 *     latest_reference_ymd:?string,
 *     cycle_next_start_ymd:?string,
 *     cycle_end_ymd:?string,
 *     locked_service_ids:list<string>
 * }
 */
function staff_treatment_monthly_included_plan_cycle_lock_state(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    string $treatmentId,
    string $bookingIdHint,
    int $gapDays = 28
): array {
    $tz = new DateTimeZone('Asia/Manila');
    $gapDays = max(1, $gapDays);
    $none = [
        'latest_reference_ymd' => null,
        'cycle_next_start_ymd' => null,
        'cycle_end_ymd' => null,
        'locked_service_ids' => [],
    ];

    $ctx = staff_treatment_schedule_reference_context($pdo, $tenantId, $patientId, $treatmentId, $bookingIdHint);
    $dates = $ctx['candidate_dates'] ?? [];
    if ($dates === []) {
        return $none;
    }
    $latest = (string) end($dates);
    if ($latest === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $latest)) {
        return $none;
    }

    $cycleNext = '';
    $cycleEnd = '';
    try {
        $base = new DateTimeImmutable($latest . ' 00:00:00', $tz);
        $nextStartDt = $base->modify('+' . $gapDays . ' days');
        $cycleNext = $nextStartDt->format('Y-m-d');
        $cycleEnd = $nextStartDt->modify('+' . ($gapDays - 1) . ' days')->format('Y-m-d');
    } catch (Throwable $e) {
        return $none;
    }

    $canonicalBooking = (string) ($ctx['canonical_booking'] ?? '');
    $hasTreatmentId = (bool) ($ctx['has_treatment_id'] ?? false);
    $treatmentId = (string) ($ctx['treatment_id'] ?? '');
    $qa = (string) ($ctx['qa'] ?? '');
    $canScopeAppt = (bool) ($ctx['can_scope_appt'] ?? false);

    if ($qa === '' || !$canScopeAppt) {
        return array_merge($none, [
            'latest_reference_ymd' => $latest,
            'cycle_next_start_ymd' => $cycleNext,
            'cycle_end_ymd' => $cycleEnd,
        ]);
    }

    $tables = clinic_resolve_appointment_db_tables($pdo);
    $apsTable = $tables['appointment_services'] ?? null;
    if ($apsTable === null || trim((string) $apsTable) === '') {
        return array_merge($none, [
            'latest_reference_ymd' => $latest,
            'cycle_next_start_ymd' => $cycleNext,
            'cycle_end_ymd' => $cycleEnd,
        ]);
    }
    $apsCols = clinic_table_columns($pdo, (string) $apsTable);
    if (!in_array('booking_id', $apsCols, true) || !in_array('service_id', $apsCols, true)) {
        return array_merge($none, [
            'latest_reference_ymd' => $latest,
            'cycle_next_start_ymd' => $cycleNext,
            'cycle_end_ymd' => $cycleEnd,
        ]);
    }

    $qaps = clinic_quote_identifier((string) $apsTable);
    $servicesPhys = clinic_get_physical_table_name($pdo, 'tbl_services')
        ?? clinic_get_physical_table_name($pdo, 'services');

    $lineIsPlanSql = "LOWER(TRIM(COALESCE(aps.service_type, ''))) IN ('included_plan', 'included plan')";
    if (
        $servicesPhys !== null
        && trim((string) $servicesPhys) !== ''
        && in_array('service_id', $apsCols, true)
    ) {
        $qs = '`' . str_replace('`', '``', (string) $servicesPhys) . '`';
        $lineIsPlanSql = '(' . $lineIsPlanSql . " OR EXISTS (\n"
            . "            SELECT 1 FROM {$qs} srv\n"
            . "            WHERE srv.tenant_id = aps.tenant_id\n"
            . "              AND TRIM(COALESCE(srv.service_id, '')) = TRIM(COALESCE(aps.service_id, ''))\n"
            . "              AND LOWER(TRIM(COALESCE(srv.service_type, ''))) = 'included_plan'\n"
            . '        ))';
    }

    $w = [
        'a.tenant_id = ?',
        "TRIM(COALESCE(a.patient_id, '')) = ?",
        "LOWER(TRIM(COALESCE(a.status, ''))) NOT IN ('cancelled', 'no_show', 'completed')",
        "TRIM(COALESCE(a.appointment_date, '')) >= ?",
        "TRIM(COALESCE(a.appointment_date, '')) <= ?",
        $lineIsPlanSql,
        'aps.tenant_id = a.tenant_id',
        'TRIM(COALESCE(aps.booking_id, \'\')) = TRIM(COALESCE(a.booking_id, \'\'))',
        "TRIM(COALESCE(aps.service_id, '')) <> ''",
    ];
    $params = [$tenantId, $patientId, $cycleNext, $cycleEnd];
    $or = [];
    if ($canonicalBooking !== '') {
        $or[] = 'TRIM(COALESCE(a.booking_id, \'\')) = ?';
        $params[] = $canonicalBooking;
    }
    if ($hasTreatmentId && $treatmentId !== '') {
        $or[] = 'TRIM(COALESCE(a.treatment_id, \'\')) = ?';
        $params[] = $treatmentId;
    }
    if ($or !== []) {
        $w[] = '(' . implode(' OR ', $or) . ')';
    }

    $locked = [];
    try {
        $sql = "
            SELECT DISTINCT TRIM(COALESCE(aps.service_id, '')) AS sid
            FROM {$qa} a
            INNER JOIN {$qaps} aps ON aps.tenant_id = a.tenant_id
                AND TRIM(COALESCE(aps.booking_id, '')) = TRIM(COALESCE(a.booking_id, ''))
            WHERE " . implode(' AND ', $w) . '
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $sid = trim((string) ($row['sid'] ?? ''));
            if ($sid !== '') {
                $locked[$sid] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('staff_treatment_monthly_included_plan_cycle_lock_state: ' . $e->getMessage());

        return array_merge($none, [
            'latest_reference_ymd' => $latest,
            'cycle_next_start_ymd' => $cycleNext,
            'cycle_end_ymd' => $cycleEnd,
        ]);
    }

    $ids = array_keys($locked);
    sort($ids);

    return [
        'latest_reference_ymd' => $latest,
        'cycle_next_start_ymd' => $cycleNext,
        'cycle_end_ymd' => $cycleEnd,
        'locked_service_ids' => $ids,
    ];
}
