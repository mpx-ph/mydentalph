<?php

declare(strict_types=1);

/**
 * Keep tbl_treatments.duration_months / months_* aligned with tbl_services.installment_duration_months
 * (primary_service_id) and, when needed, tbl_appointments.duration_months — not stale ledger defaults.
 */

require_once __DIR__ . '/appointment_db_tables.php';

/**
 * Canonical plan length in months for a treatment row.
 * Prefer catalog (tbl_services) for primary_service_id; else max(duration_months) on linked appointments.
 */
function clinic_canonical_duration_months_for_treatment(PDO $pdo, string $tenantId, array $treatmentRow): int
{
    $tenantId = trim($tenantId);
    $sid = trim((string) ($treatmentRow['primary_service_id'] ?? ''));
    $tid = trim((string) ($treatmentRow['treatment_id'] ?? ''));

    $tables = clinic_resolve_appointment_db_tables($pdo);
    $svcTable = $tables['services'] ?? 'tbl_services';
    $physSvc = clinic_get_physical_table_name($pdo, $svcTable) ?? $svcTable;
    $qSvc = clinic_quote_identifier($physSvc);

    if ($tenantId !== '' && $sid !== '') {
        try {
            $st = $pdo->prepare(
                "SELECT COALESCE(installment_duration_months, 0) AS m
                 FROM {$qSvc}
                 WHERE tenant_id = ? AND service_id = ?
                 LIMIT 1"
            );
            $st->execute([$tenantId, $sid]);
            $m = (int) ($st->fetchColumn() ?: 0);
            if ($m > 0) {
                return $m;
            }
        } catch (Throwable $e) {
            error_log('clinic_canonical_duration_months_for_treatment service: ' . $e->getMessage());
        }
    }

    $apptTable = $tables['appointments'] ?? 'tbl_appointments';
    $physAppt = clinic_get_physical_table_name($pdo, $apptTable) ?? $apptTable;
    $cols = clinic_table_columns($pdo, $physAppt);
    if (
        $tenantId === '' || $tid === ''
        || !in_array('treatment_id', $cols, true)
        || !in_array('duration_months', $cols, true)
    ) {
        return 0;
    }

    try {
        $qAppt = clinic_quote_identifier($physAppt);
        $st2 = $pdo->prepare(
            "SELECT MAX(COALESCE(duration_months, 0)) AS m
             FROM {$qAppt}
             WHERE tenant_id = ? AND treatment_id = ?"
        );
        $st2->execute([$tenantId, $tid]);
        return max(0, (int) ($st2->fetchColumn() ?: 0));
    } catch (Throwable $e) {
        error_log('clinic_canonical_duration_months_for_treatment appointments: ' . $e->getMessage());
        return 0;
    }
}

/**
 * When catalog / appointments disagree with tbl_treatments (e.g. old 24-month defaults), patch duration
 * and recompute months_left from duration_months − months_paid.
 *
 * @return bool true if a row was updated
 */
function clinic_reconcile_tbl_treatments_duration(PDO $pdo, string $tenantId, string $treatmentId): bool
{
    $tenantId = trim($tenantId);
    $treatmentId = trim($treatmentId);
    if ($tenantId === '' || $treatmentId === '') {
        return false;
    }

    $tables = clinic_resolve_appointment_db_tables($pdo);
    $treatPhys = $tables['treatments'] ?? 'tbl_treatments';
    $phys = clinic_get_physical_table_name($pdo, $treatPhys) ?? $treatPhys;
    $qt = clinic_quote_identifier($phys);

    try {
        $sel = $pdo->prepare(
            "SELECT treatment_id, primary_service_id, duration_months, months_paid, months_left
             FROM {$qt}
             WHERE tenant_id = ? AND treatment_id = ?
             LIMIT 1"
        );
        $sel->execute([$tenantId, $treatmentId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return false;
        }

        $canonical = clinic_canonical_duration_months_for_treatment($pdo, $tenantId, $row);
        if ($canonical <= 0) {
            return false;
        }

        $curDur = max(0, (int) ($row['duration_months'] ?? 0));
        $monthsPaidRaw = max(0, (int) ($row['months_paid'] ?? 0));
        $monthsPaid = min($monthsPaidRaw, $canonical);
        $expectedLeft = max(0, $canonical - $monthsPaid);
        $curLeft = max(0, (int) ($row['months_left'] ?? 0));

        if (
            $curDur === $canonical
            && $monthsPaidRaw === $monthsPaid
            && $curLeft === $expectedLeft
        ) {
            return false;
        }

        $upd = $pdo->prepare(
            "UPDATE {$qt}
             SET duration_months = ?,
                 months_paid = ?,
                 months_left = ?
             WHERE tenant_id = ?
               AND treatment_id = ?
             LIMIT 1"
        );
        $upd->execute([$canonical, $monthsPaid, $expectedLeft, $tenantId, $treatmentId]);
        return true;
    } catch (Throwable $e) {
        error_log('clinic_reconcile_tbl_treatments_duration: ' . $e->getMessage());
        return false;
    }
}

/**
 * Refresh duration fields on an array already fetched for JSON (get_treatments.php).
 *
 * @param array<string, mixed> $tr
 */
function clinic_patch_treatment_row_after_reconcile(PDO $pdo, string $tenantId, string $treatmentId, array &$tr): void
{
    $tenantId = trim($tenantId);
    $treatmentId = trim($treatmentId);
    if ($tenantId === '' || $treatmentId === '') {
        return;
    }
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $treatPhys = $tables['treatments'] ?? 'tbl_treatments';
    $phys = clinic_get_physical_table_name($pdo, $treatPhys) ?? $treatPhys;
    $qt = clinic_quote_identifier($phys);
    try {
        $sel = $pdo->prepare(
            "SELECT duration_months, months_paid, months_left
             FROM {$qt}
             WHERE tenant_id = ? AND treatment_id = ?
             LIMIT 1"
        );
        $sel->execute([$tenantId, $treatmentId]);
        $fresh = $sel->fetch(PDO::FETCH_ASSOC);
        if (is_array($fresh)) {
            $tr['duration_months'] = $fresh['duration_months'];
            $tr['months_paid'] = $fresh['months_paid'];
            $tr['months_left'] = $fresh['months_left'];
        }
    } catch (Throwable $e) {
        error_log('clinic_patch_treatment_row_after_reconcile: ' . $e->getMessage());
    }
}
