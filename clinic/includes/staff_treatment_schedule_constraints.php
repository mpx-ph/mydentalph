<?php

declare(strict_types=1);

require_once __DIR__ . '/appointment_db_tables.php';

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
    $tenantId = trim($tenantId);
    $patientId = trim($patientId);
    $treatmentId = trim($treatmentId);
    $bookingIdHint = trim($bookingIdHint);
    if ($tenantId === '' || $patientId === '') {
        return null;
    }

    $tz = new DateTimeZone('Asia/Manila');
    $gapDays = max(1, $gapDays);

    $tables = clinic_resolve_appointment_db_tables($pdo);
    $aTable = $tables['appointments'] ?? null;
    if ($aTable === null || trim((string) $aTable) === '') {
        return null;
    }

    $qa = clinic_quote_identifier((string) $aTable);
    $apptCols = clinic_table_columns($pdo, (string) $aTable);
    $hasTreatmentId = in_array('treatment_id', $apptCols, true);
    $hasApptDate = in_array('appointment_date', $apptCols, true);
    if (!$hasApptDate) {
        return null;
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

    if ($candidateYmd === []) {
        return null;
    }

    $dates = array_keys($candidateYmd);
    sort($dates);
    $latest = end($dates) ?: '';
    if ($latest === '') {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($latest . ' 00:00:00', $tz);
        $due = $dt->modify('+' . $gapDays . ' days');

        return $due->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}
