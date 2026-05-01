<?php

declare(strict_types=1);

/**
 * Patient mobile booking ledger (init_mobile_payment / add_booking): aligns with staff flow in
 * clinic/api/appointments.php — generate treatment_id, insert tbl_treatments, link tbl_payments,
 * optionally tbl_appointments installment_number / notes when columns exist.
 */

require_once __DIR__ . '/appointment_db_tables.php';
require_once __DIR__ . '/staff_installment_helpers.php';

/**
 * @param array<string, mixed> $apptExtras
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function booking_merge_extras_from_mobile_input(array $apptExtras, array $input): array
{
    $tt = strtolower(trim((string) ($input['treatment_type'] ?? '')));
    if ($tt === 'long_term' || $tt === 'longterm') {
        $apptExtras['treatment_type'] = 'long_term';
    } elseif ($tt === 'short_term' || $tt === 'shortterm') {
        $apptExtras['treatment_type'] = 'short_term';
    }

    $dm = $input['duration_months'] ?? null;
    if ($dm !== null && $dm !== '') {
        $dmInt = (int) $dm;
        if ($dmInt > 0) {
            $apptExtras['duration_months'] = $dmInt;
        }
    }

    $tc = trim((string) ($input['target_completion_date'] ?? ''));
    if ($tc !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tc)) {
        $apptExtras['target_completion_date'] = $tc;
    }

    return $apptExtras;
}

/**
 * @param array<string, mixed> $apptExtras
 * @return array<string, mixed>
 */
function booking_ensure_long_term_schedule(array $apptExtras, string $appointmentDateYmd): array
{
    $tz = new DateTimeZone('Asia/Manila');
    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDateYmd)
        ? $appointmentDateYmd
        : (new DateTimeImmutable('now', $tz))->format('Y-m-d');

    if (trim((string) ($apptExtras['start_date'] ?? '')) === '') {
        $apptExtras['start_date'] = $start;
    }

    $isLong = strtolower(trim((string) ($apptExtras['treatment_type'] ?? ''))) === 'long_term';

    $duration = isset($apptExtras['duration_months']) ? (int) $apptExtras['duration_months'] : 0;

    if ($isLong || $duration > 0) {
        if ($duration <= 0) {
            $duration = 18;
            $apptExtras['duration_months'] = $duration;
        }
        try {
            $dt = new DateTimeImmutable((string) $apptExtras['start_date'], $tz);
            if (trim((string) ($apptExtras['target_completion_date'] ?? '')) === '') {
                $apptExtras['target_completion_date'] = $dt->modify('+' . $duration . ' months')->format('Y-m-d');
            }
        } catch (Throwable $e) {
            // keep extras as-is
        }
        $apptExtras['treatment_type'] = 'long_term';
    }

    return $apptExtras;
}

/**
 * @param list<mixed> $services
 * @return list<array<string, mixed>>
 */
function booking_enrich_mobile_service_rows(PDO $pdo, string $tenantId, array $services): array
{
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $servicesTable = $tables['services'];
    $out = [];
    if ($servicesTable === null) {
        return $out;
    }
    $quoted = clinic_quote_identifier($servicesTable);

    foreach ($services as $srv) {
        if (!is_array($srv)) {
            continue;
        }
        $sid = trim((string) ($srv['id'] ?? $srv['service_id'] ?? ''));
        $nameIn = trim((string) ($srv['name'] ?? $srv['service_name'] ?? ''));
        $priceIn = isset($srv['price']) ? (float) $srv['price'] : 0.0;
        $catIn = trim((string) ($srv['category'] ?? ''));
        $typeIn = strtolower(trim((string) ($srv['service_type'] ?? $srv['type'] ?? '')));

        $catalogName = $nameIn;
        $catalogPrice = $priceIn;
        $category = $catIn;
        $enableInstall = 0;
        $installMonths = 0;
        $categoryServiceType = '';

        if ($sid !== '') {
            $stmt = $pdo->prepare("
                SELECT service_name, price, COALESCE(enable_installment, 0) AS enable_installment,
                       COALESCE(installment_duration_months, 0) AS installment_duration_months,
                       category,
                       COALESCE(TRIM(service_type), '') AS service_type
                FROM {$quoted}
                WHERE tenant_id = ? AND service_id = ?
                LIMIT 1
            ");
            $stmt->execute([$tenantId, $sid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            if (is_array($row)) {
                $catalogName = trim((string) ($row['service_name'] ?? $nameIn)) ?: $nameIn;
                $catalogPrice = isset($row['price']) ? (float) $row['price'] : $priceIn;
                $category = trim((string) ($row['category'] ?? '')) ?: $category;
                $enableInstall = (int) ($row['enable_installment'] ?? 0);
                $installMonths = (int) ($row['installment_duration_months'] ?? 0);
                $categoryServiceType = strtolower(trim((string) ($row['service_type'] ?? '')));
            }
        }

        $isOrtho = strcasecmp(trim($category), 'Orthodontics') === 0;
        $isInstallmentExplicit = ($typeIn === 'installment');
        $catalogSaysInstallment = ($enableInstall === 1) || ($categoryServiceType === 'installment');
        $isInstallmentRow = !$isOrtho && ($isInstallmentExplicit || $catalogSaysInstallment);

        $out[] = [
            'service_id' => $sid,
            'service_name' => $catalogName !== '' ? $catalogName : $nameIn,
            'price' => $catalogPrice,
            'category' => $category,
            'enable_installment' => $enableInstall === 1,
            'installment_duration_months' => $installMonths,
            'is_orthodontics' => $isOrtho,
            'is_installment_line' => $isInstallmentRow,
        ];
    }

    return $out;
}

/**
 * @throws Exception when unable to mint id
 */
function booking_mobile_generate_treatment_id(PDO $pdo, string $tenantId, string $treatPhys): string
{
    $quoted = clinic_quote_identifier($treatPhys);
    $prefix = 'TRT-' . date('Y') . '-';
    $stmt = $pdo->prepare("
        SELECT treatment_id
        FROM {$quoted}
        WHERE tenant_id = ?
          AND treatment_id LIKE ?
        ORDER BY treatment_id DESC
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $prefix . '%']);
    $last = trim((string) ($stmt->fetchColumn() ?: ''));
    $seq = 1;
    if ($last !== '') {
        $parts = explode('-', $last);
        if (count($parts) === 3) {
            $seq = ((int) $parts[2]) + 1;
        }
    }
    return $prefix . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
}

/**
 * @param list<mixed> $services
 * @param array<string, mixed> $apptExtras
 * @return array{
 *   treatment_id: string,
 *   installment_number: int,
 *   payment_notes: string,
 *   enriched_rows: list<array<string, mixed>>,
 *   duration_months: int,
 *   needs_plan_ledger: bool
 * }
 */
function booking_create_treatment_row(
    PDO $pdo,
    string $tenantId,
    string $patientId,
    string $userId,
    string $bookingId,
    array $services,
    float $cartTotalAmount,
    array $apptExtras
): array {
    $tables = clinic_resolve_appointment_db_tables($pdo);
    $treatPhys = $tables['treatments'];

    $enriched = booking_enrich_mobile_service_rows($pdo, $tenantId, $services);
    $anyInstallRow = false;
    foreach ($enriched as $r) {
        if (!empty($r['is_installment_line'])) {
            $anyInstallRow = true;
            break;
        }
    }
    $anyOrtho = false;
    foreach ($enriched as $r) {
        if (!empty($r['is_orthodontics'])) {
            $anyOrtho = true;
            break;
        }
    }

    $tt = strtolower(trim((string) ($apptExtras['treatment_type'] ?? 'short_term')));
    $durationFromExtras = isset($apptExtras['duration_months']) ? (int) $apptExtras['duration_months'] : 0;

    $needsLedger = $anyInstallRow
        || $anyOrtho
        || $tt === 'long_term'
        || $durationFromExtras > 0;

    if (!$needsLedger || $treatPhys === null || trim($treatPhys) === '') {
        return [
            'treatment_id' => '',
            'installment_number' => 0,
            'payment_notes' => sprintf('Booking %s (patient mobile).', $bookingId),
            'enriched_rows' => $enriched,
            'duration_months' => $durationFromExtras,
            'needs_plan_ledger' => false,
        ];
    }

    $tid = booking_mobile_generate_treatment_id($pdo, $tenantId, $treatPhys);

    $primaryIdx = null;
    foreach ($enriched as $i => $r) {
        if (!empty($r['is_installment_line'])) {
            $primaryIdx = $i;
            break;
        }
    }
    if ($primaryIdx === null && $enriched !== []) {
        $primaryIdx = 0;
    }
    $primary = $primaryIdx !== null ? $enriched[$primaryIdx] : null;

    $durationMonths = max(
        $durationFromExtras > 0 ? $durationFromExtras : 0,
        $primary && (int) ($primary['installment_duration_months'] ?? 0) > 0
            ? (int) $primary['installment_duration_months']
            : 0,
        ($anyOrtho || $tt === 'long_term') ? 18 : 0
    );
    if ($durationMonths <= 0) {
        $durationMonths = 18;
    }

    $cost = round(max(0.0, $cartTotalAmount), 2);

    $data = [
        'tenant_id' => $tenantId,
        'treatment_id' => $tid,
        'patient_id' => $patientId,
        'primary_service_id' => $primary !== null ? trim((string) ($primary['service_id'] ?? '')) : '',
        'primary_service_name' => $primary !== null ? trim((string) ($primary['service_name'] ?? '')) : '',
        'total_cost' => $cost,
        'amount_paid' => 0.0,
        'remaining_balance' => $cost,
        'duration_months' => $durationMonths,
        'months_paid' => 0,
        'months_left' => $durationMonths,
        'status' => 'active',
        'started_at' => trim((string) ($apptExtras['start_date'] ?? '')) !== ''
            ? (string) $apptExtras['start_date']
            : date('Y-m-d'),
        'created_by' => $userId,
    ];

    $quotedT = clinic_quote_identifier($treatPhys);
    $treatCols = clinic_table_columns($pdo, $treatPhys);
    $cols = [];
    $ph = [];
    $params = [];
    $order = [
        'tenant_id', 'treatment_id', 'patient_id', 'primary_service_id', 'primary_service_name',
        'total_cost', 'amount_paid', 'remaining_balance', 'duration_months', 'months_paid',
        'months_left', 'status', 'started_at', 'created_by',
    ];
    foreach ($order as $col) {
        if (!in_array(strtolower($col), $treatCols, true) || !array_key_exists($col, $data)) {
            continue;
        }
        $cols[] = clinic_quote_identifier($col);
        $ph[] = '?';
        $params[] = $data[$col];
    }
    if (in_array('created_at', $treatCols, true)) {
        $cols[] = clinic_quote_identifier('created_at');
        $ph[] = 'NOW()';
    }
    if ($cols !== []) {
        $sql = 'INSERT INTO ' . $quotedT . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    return [
        'treatment_id' => $tid,
        'installment_number' => 1,
        'payment_notes' => sprintf(
            'Mobile booking %s. Treatment ledger %s. Patient %s.',
            $bookingId,
            $tid,
            $patientId
        ),
        'enriched_rows' => $enriched,
        'duration_months' => $durationMonths,
        'needs_plan_ledger' => true,
    ];
}

/**
 * After PayMongo marks payment completed — bump tbl_treatments totals.
 *
 * @param array<string, mixed> $paymentRow fetched tbl_payments row
 */
function booking_apply_completed_payment_to_treatment(PDO $pdo, array $paymentRow): void
{
    $tenantId = trim((string) ($paymentRow['tenant_id'] ?? ''));
    $tid = trim((string) ($paymentRow['treatment_id'] ?? ''));
    if ($tenantId === '' || $tid === '') {
        return;
    }
    $amount = (float) ($paymentRow['amount'] ?? 0);
    if ($amount <= 0) {
        return;
    }
    $payType = strtolower(trim((string) ($paymentRow['payment_type'] ?? '')));

    try {
        $tables = clinic_resolve_appointment_db_tables($pdo);
        $phys = $tables['treatments'];
        if ($phys === null) {
            return;
        }
        $quoted = clinic_quote_identifier((string) $phys);
        $sel = $pdo->prepare("SELECT duration_months, months_paid FROM {$quoted}
            WHERE tenant_id = ? AND treatment_id = ? LIMIT 1");
        $sel->execute([$tenantId, $tid]);
        $snap = $sel->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$snap) {
            return;
        }
        $dur = max(0, (int) ($snap['duration_months'] ?? 0));
        $curM = max(0, (int) ($snap['months_paid'] ?? 0));
        $monthsInc = 0;
        if ($payType === 'fullpayment') {
            $monthsInc = $dur > 0 ? max(0, $dur - $curM) : 0;
        } else {
            $monthsInc = 1;
        }
        staff_treatments_apply_payment($pdo, $tenantId, $tid, $amount, $monthsInc);
    } catch (Throwable $e) {
        error_log('booking_apply_completed_payment_to_treatment: ' . $e->getMessage());
    }
}

/**
 * INSERT appointment using only columns present on DB (handles tbl_appointments / appointments naming).
 *
 * @param array<string, mixed> $values keyed by logical column names
 */
function booking_mobile_dynamic_insert_appointment(PDO $pdo, string $appointmentsPhys, array $values): void
{
    $colsAvail = clinic_table_columns($pdo, $appointmentsPhys);
    $qTable = clinic_quote_identifier($appointmentsPhys);
    $cols = [];
    $ph = [];
    $bind = [];

    foreach ($values as $k => $v) {
        $lk = strtolower((string) $k);
        if ($lk === 'created_at') {
            continue;
        }
        if (!in_array($lk, $colsAvail, true)) {
            continue;
        }
        $cols[] = clinic_quote_identifier($lk);
        $ph[] = '?';
        $bind[] = $v;
    }

    if (in_array('created_at', $colsAvail, true)) {
        $cols[] = clinic_quote_identifier('created_at');
        $ph[] = 'NOW()';
    }

    if ($cols === []) {
        throw new Exception('Appointment insert: no overlapping columns.');
    }

    $sql = 'INSERT INTO ' . $qTable . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')';
    $st = $pdo->prepare($sql);
    $st->execute($bind);
}
