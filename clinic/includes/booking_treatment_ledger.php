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
 * @return array<string, string> lowercase column name → MySQL type (e.g. int(11), date, varchar(…))
 */
function clinic_mysql_column_types_map(PDO $pdo, string $tablePreferred): array
{
    static $cache = [];
    $phys = clinic_get_physical_table_name($pdo, $tablePreferred) ?? $tablePreferred;
    $k = strtolower($phys);
    if (isset($cache[$k])) {
        return $cache[$k];
    }
    $map = [];
    try {
        $q = clinic_quote_identifier($phys);
        $show = $pdo->query('SHOW COLUMNS FROM ' . $q);
        if ($show) {
            while ($row = $show->fetch(PDO::FETCH_ASSOC)) {
                $field = strtolower(trim((string) ($row['Field'] ?? '')));
                if ($field !== '') {
                    $map[$field] = strtolower(trim((string) ($row['Type'] ?? '')));
                }
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
    $cache[$k] = $map;
    return $cache[$k];
}

function clinic_mysql_column_type_string(PDO $pdo, string $tablePreferred, string $columnLc): string
{
    $types = clinic_mysql_column_types_map($pdo, $tablePreferred);
    return strtolower(trim((string) ($types[strtolower($columnLc)] ?? '')));
}

/**
 * Classify MySQL column Type for sensible date payloads.
 *
 * MySQL binds "2026-05-02" into INT columns as numeric 2026 → ~33 min epoch → UI shows year 1970.
 */
function booking_mysql_type_date_family(?string $mysqlType): string
{
    $type = strtolower(trim((string) $mysqlType));
    if ($type === '') {
        return 'unknown';
    }
    if (
        strpos($type, 'datetime') !== false
        || strpos($type, 'timestamp') !== false
    ) {
        return 'datetime';
    }
    if (preg_match('/\b(bigint|int|smallint|mediumint)\b/', $type)) {
        return 'integer';
    }
    if (
        strpos($type, 'tinyint') !== false
        || strpos($type, 'decimal') !== false
        || strpos($type, 'double') !== false
        || strpos($type, 'float') !== false
    ) {
        return 'numeric';
    }
    if (strpos($type, 'date') !== false || strpos($type, 'year') !== false) {
        return 'date';
    }
    return 'text';
}

/**
 * @return mixed unix int | string Y-m-d | string Y-m-d H:i:s
 */
function booking_bind_dateish_value_for_mysql_type(
    string $mysqlType,
    DateTimeImmutable $dtNoonManila,
    string $ymd,
): mixed {
    $fam = booking_mysql_type_date_family($mysqlType);
    switch ($fam) {
        case 'integer':
            return $dtNoonManila->getTimestamp();
        case 'numeric':
            return $dtNoonManila->getTimestamp();
        case 'date':
            return $ymd;
        case 'datetime':
            return $dtNoonManila->format('Y-m-d H:i:s');
        default:
            return $ymd;
    }
}

/**
 * Normalize incoming mobile `appointment_date` (string Y-m-d, unix seconds, ms).
 *
 * @return non-empty-string|null
 */
/**
 * @return non-empty-string|null
 */
function booking_normalize_mobile_date_input(mixed $raw, string $fallbackYmdWhenInvalid = ''): ?string
{
    if ($raw === null || $raw === '') {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fallbackYmdWhenInvalid)
            ? $fallbackYmdWhenInvalid
            : null;
    }
    $tz = new DateTimeZone('Asia/Manila');
    if (is_numeric($raw)) {
        $n = (float) $raw;
        if ($n > 1e12) {
            $n = (int) round($n / 1000.0);
        } else {
            $n = (int) round($n);
        }
        if ($n >= 946684800 && $n < 2147483648) {
            return (new DateTimeImmutable('@' . $n))->setTimezone($tz)->format('Y-m-d');
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $fallbackYmdWhenInvalid)
            ? $fallbackYmdWhenInvalid
            : null;
    }
    $s = trim((string) $raw);
    if (
        preg_match('/^(\d{4})-(\d{2})-(\d{2})\b/', $s, $m)
        && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
        && (int) $m[1] > 1970
    ) {
        return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    $fbBase = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($fallbackYmdWhenInvalid))
        ? trim($fallbackYmdWhenInvalid)
        : (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    $try = strtotime($s);
    if ($try !== false) {
        $dt = (new DateTimeImmutable('@' . $try))->setTimezone($tz);
        if ((int) $dt->format('Y') > 1970) {
            return $dt->format('Y-m-d');
        }
    }
    return $fbBase !== '' ? $fbBase : null;
}

/**
 * Align appointment INSERT values when DB columns are unix integers vs DATE/DATETIME strings.
 *
 * @param array<string, mixed> $values
 * @return array<string, mixed>
 */
function booking_coerce_appointment_date_columns(PDO $pdo, string $appointmentsPhys, array $values): array
{
    $appointmentYmd = isset($values['appointment_date'])
        ? (string) $values['appointment_date']
        : '';
    foreach (['appointment_date', 'start_date', 'target_completion_date'] as $col) {
        if (!array_key_exists($col, $values)) {
            continue;
        }
        $rawVal = $values[$col];
        if ($rawVal === null || $rawVal === '') {
            continue;
        }
        $typeStr = clinic_mysql_column_type_string($pdo, $appointmentsPhys, $col);
        if ($typeStr === '') {
            continue;
        }
        $ymd = booking_normalize_plan_start_date(
            (string) $rawVal,
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentYmd)
                ? $appointmentYmd
                : booking_fallback_appointment_ymd($appointmentYmd)
        );
        $tz = new DateTimeZone('Asia/Manila');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, $tz)
            ?: new DateTimeImmutable('now', $tz);
        $dtNoon = $dt->setTime(12, 0, 0);
        $values[$col] = booking_bind_dateish_value_for_mysql_type($typeStr, $dtNoon, $ymd);
    }
    return $values;
}

function booking_fallback_appointment_ymd(string $appointmentDateYmd): string
{
    $t = trim($appointmentDateYmd);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) {
        return $t;
    }
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');
}

/**
 * Plan / ledger start date safe for INSERT. Rejects Unix-epoch sentinel and invalid strings.
 */
function booking_normalize_plan_start_date(?string $candidate, string $appointmentDateYmd): string
{
    $fb = booking_fallback_appointment_ymd($appointmentDateYmd);
    $c = trim((string) ($candidate ?? ''));
    if ($c === '' || strncmp($c, '0000', 4) === 0) {
        return $fb;
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $c, $m)) {
        return $fb;
    }
    $yy = (int) $m[1];
    $mo = (int) $m[2];
    $dd = (int) $m[3];
    if (!checkdate($mo, $dd, $yy)) {
        return $fb;
    }
    if ($yy <= 1970) {
        return $fb;
    }
    return sprintf('%04d-%02d-%02d', $yy, $mo, $dd);
}

/**
 * If `started_at` is INTEGER (unix), binding "2026-05-02" can become numeric 2026 → ~33 min past epoch → UI shows year 1970.
 *
 * @return mixed int unix | string Y-m-d | string Y-m-d H:i:s
 */
function booking_started_at_bind_value_for_column(PDO $pdo, string $treatmentsTablePhys, string $ymd): mixed
{
    try {
        $tz = new DateTimeZone('Asia/Manila');
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $ymd, $tz);
        if (!$dt instanceof DateTimeImmutable) {
            $dt = new DateTimeImmutable('now', $tz);
        }
        $dtNoon = $dt->setTime(12, 0, 0);

        $type = clinic_mysql_column_type_string($pdo, $treatmentsTablePhys, 'started_at');
        if ($type !== '') {
            return booking_bind_dateish_value_for_mysql_type($type, $dtNoon, $ymd);
        }

        return $dtNoon->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return $ymd . ' 12:00:00';
    }
}

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
    $apptExtras['start_date'] = booking_normalize_plan_start_date(
        isset($apptExtras['start_date']) ? (string) $apptExtras['start_date'] : null,
        $appointmentDateYmd
    );

    $isLong = strtolower(trim((string) ($apptExtras['treatment_type'] ?? ''))) === 'long_term';

    $duration = isset($apptExtras['duration_months']) ? (int) $apptExtras['duration_months'] : 0;

    if ($isLong || $duration > 0) {
        // Target completion only when catalog/mobile supplied a positive duration (tbl_services.installment_duration_months path).
        if ($duration > 0) {
            try {
                $dt = new DateTimeImmutable((string) $apptExtras['start_date'], $tz);
                if (trim((string) ($apptExtras['target_completion_date'] ?? '')) === '') {
                    $apptExtras['target_completion_date'] = $dt->modify('+' . $duration . ' months')->format('Y-m-d');
                }
            } catch (Throwable $e) {
                // keep extras as-is
            }
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
    array $apptExtras,
    string $bookingAppointmentYmd,
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

    // Plan length = primary line's tbl_services.installment_duration_months (same row as primary_service_id).
    // Do not take max() across the whole cart — that could inflate duration (e.g. 12 + 12 → 24) vs one real plan.
    // When catalog has a value it wins over mobile `duration_months` so tbl_treatments matches tbl_appointments + tbl_services.
    $catalogPrimary = $primary !== null ? (int) ($primary['installment_duration_months'] ?? 0) : 0;
    if ($catalogPrimary > 0) {
        $durationMonths = $catalogPrimary;
    } else {
        $durationMonths = $durationFromExtras > 0 ? $durationFromExtras : 0;
    }

    $cost = round(max(0.0, $cartTotalAmount), 2);

    $planStartYmd = booking_normalize_plan_start_date(
        isset($apptExtras['start_date']) ? (string) $apptExtras['start_date'] : null,
        $bookingAppointmentYmd
    );
    $startedAtBind = booking_started_at_bind_value_for_column($pdo, (string) $treatPhys, $planStartYmd);

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
        'started_at' => $startedAtBind,
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
