<?php

declare(strict_types=1);

/**
 * Derive tbl_appointments display/treatment fields from a cart of services and tbl_services.
 *
 * @param array $services List of items with at least 'id' or 'service_id', and optionally 'name', 'price'.
 * @return array{service_type:?string,service_description:?string,treatment_type:string,duration_months:?int,target_completion_date:?string,start_date:string}
 */
function clinic_appointment_extras_for_booking(
    PDO $pdo,
    string $tenantId,
    array $services,
    string $appointmentDateYmd
): array {
    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $appointmentDateYmd) ? $appointmentDateYmd : (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d');

    $out = [
        'service_type' => null,
        'service_description' => null,
        'treatment_type' => 'short_term',
        'duration_months' => null,
        'target_completion_date' => null,
        'start_date' => $start,
    ];
    if ($services === []) {
        return $out;
    }

    $stmt = $pdo->prepare('
        SELECT service_name, service_details, category, price,
               COALESCE(enable_installment, 0) AS enable_installment,
               COALESCE(installment_duration_months, 0) AS duration_m
        FROM tbl_services
        WHERE tenant_id = ? AND service_id = ?
        LIMIT 1
    ');

    $serviceNames = [];
    $descriptionLines = [];
    $anyInstallment = false;
    $anyOrthodontics = false;
    $maxDuration = 0;
    $sumPrice = 0.0;
    $anyRow = false;

    foreach ($services as $srv) {
        if (!is_array($srv)) {
            continue;
        }
        $sid = trim((string) ($srv['id'] ?? $srv['service_id'] ?? ''));
        if ($sid === '') {
            continue;
        }
        $stmt->execute([$tenantId, $sid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $name = $row
            ? trim((string) ($row['service_name'] ?? ''))
            : trim((string) ($srv['name'] ?? ''));
        $priceVal = $row
            ? (float) ($row['price'] ?? 0)
            : (float) ($srv['price'] ?? 0);
        $sumPrice += $priceVal;
        $anyRow = true;

        if ($row) {
            if (!empty($row['enable_installment'])) {
                $anyInstallment = true;
            }
            $cat = trim((string) ($row['category'] ?? ''));
            if (strcasecmp($cat, 'Orthodontics') === 0) {
                $anyOrthodontics = true;
            }
            $d = (int) ($row['duration_m'] ?? 0);
            if ($d > 0) {
                $maxDuration = max($maxDuration, $d);
            }
            $details = trim((string) ($row['service_details'] ?? ''));
        } else {
            $details = '';
        }

        if ($name !== '') {
            $serviceNames[] = $name;
        }
        if ($name !== '' || $details !== '') {
            $line = $name !== '' ? $name : $sid;
            if ($details !== '') {
                $line .= ' — ' . $details;
            }
            $line .= ' (₱' . number_format($priceVal, 2) . ')';
            $descriptionLines[] = $line;
        }
    }

    if (!$anyRow) {
        return $out;
    }

    $serviceNames = array_values(array_unique(array_filter($serviceNames, static function ($n) {
        return $n !== '';
    })));

    $label = '';
    if ($serviceNames !== []) {
        $label = implode(', ', array_slice($serviceNames, 0, 3));
        if (count($serviceNames) > 3) {
            $label .= ' (+' . (count($serviceNames) - 3) . ' more)';
        }
    }
    if ($label !== '') {
        if (function_exists('mb_substr')) {
            $out['service_type'] = mb_substr($label, 0, 100, 'UTF-8');
        } else {
            $out['service_type'] = substr($label, 0, 100);
        }
    }

    if ($descriptionLines !== []) {
        $out['service_description'] = implode('; ', $descriptionLines);
        if ($sumPrice > 0) {
            $out['service_description'] .= ' | Total: ₱' . number_format($sumPrice, 2);
        }
    }

    if ($anyInstallment || $anyOrthodontics) {
        $out['treatment_type'] = 'long_term';
    }

    if ($maxDuration > 0) {
        $out['duration_months'] = $maxDuration;
        $tz = new DateTimeZone('Asia/Manila');
        $dt = new DateTimeImmutable($start, $tz);
        $out['target_completion_date'] = $dt->modify('+' . $maxDuration . ' months')->format('Y-m-d');
    }

    return $out;
}
