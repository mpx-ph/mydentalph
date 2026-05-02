<?php
// Shared helpers for mobile app profile (tbl_users + tbl_patients)
declare(strict_types=1);

/**
 * @return array<string,mixed>|null
 */
function api_profile_fetch_user(PDO $pdo, string $userId, string $tenantId): ?array
{
    $st = $pdo->prepare(
        "SELECT user_id, tenant_id, username, email, full_name, role, phone, created_at, updated_at, status
         FROM tbl_users
         WHERE user_id = ? AND tenant_id = ?
         LIMIT 1"
    );
    $st->execute([$userId, $tenantId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Primary patient row for this app user (owned or linked).
 * @return array<string,mixed>|null
 */
function api_profile_fetch_patient(PDO $pdo, string $userId, string $tenantId): ?array
{
    $st = $pdo->prepare(
        "SELECT p.* FROM tbl_patients p
         WHERE p.tenant_id = ?
           AND (p.owner_user_id = ? OR p.linked_user_id = ?)
         ORDER BY (p.linked_user_id = ?) DESC, p.id DESC
         LIMIT 1"
    );
    $st->execute([$tenantId, $userId, $userId, $userId]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * @return list<string>
 */
function api_profile_allowed_genders(): array
{
    return ['Male', 'Female', 'Other', 'Prefer not to say'];
}

/**
 * @return string|null null = unset / invalid to reject
 */
function api_profile_normalize_gender(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $s = trim((string) $value);
    if ($s === '') {
        return 'EMPTY';
    }
    foreach (api_profile_allowed_genders() as $g) {
        if (strcasecmp($s, $g) === 0) {
            return $g;
        }
    }
    return 'INVALID';
}

/**
 * P-YYYY-99999, collision-safe
 */
function api_profile_generate_patient_id(PDO $pdo): string
{
    $year   = date('Y');
    $maxRetries = 10;
    $pattern = 'P-' . $year . '-%';
    $st = $pdo->prepare("SELECT patient_id FROM tbl_patients WHERE patient_id LIKE ? ORDER BY patient_id DESC LIMIT 1");
    $st->execute([$pattern]);
    $last = $st->fetchColumn();
    if ($last) {
        $parts   = explode('-', (string) $last);
        $sequence = intval(end($parts), 10) + 1;
    } else {
        $sequence = 1;
    }
    for ($i = 0; $i < $maxRetries; $i++) {
        $id = 'P-' . $year . '-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare('SELECT 1 FROM tbl_patients WHERE patient_id = ? LIMIT 1');
        $chk->execute([$id]);
        if (!$chk->fetchColumn()) {
            return $id;
        }
        $sequence++;
    }
    return 'P-' . $year . '-' . str_pad((string) random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * Detect when a whole address object was JSON-stringified into one field (common client bug).
 *
 * @return array<string,string>|null Decoded map with string values, or null if not that shape
 */
function api_profile_embedded_address_object(?string $s): ?array
{
    $t = trim((string) $s);
    if ($t === '' || $t[0] !== '{') {
        return null;
    }
    $dec = json_decode($t, true);
    if (!is_array($dec)) {
        return null;
    }
    // Shape produced when an app mistakenly serializes the address map into house_street (or similar).
    if (!array_key_exists('city_municipality', $dec) && !array_key_exists('house_street', $dec)) {
        return null;
    }
    return $dec;
}

/**
 * @param array<string,mixed>|null $p tbl_patients row
 * @return array{province: string, city_municipality: string, barangay: string, house_street: string}
 */
function api_profile_resolve_address_for_api(?array $p): array
{
    $empty = ['province' => '', 'city_municipality' => '', 'barangay' => '', 'house_street' => ''];
    if (!$p) {
        return $empty;
    }
    $emb = api_profile_embedded_address_object(isset($p['house_street']) ? (string) $p['house_street'] : '');
    if ($emb !== null) {
        $line = trim((string) ($emb['house_street'] ?? ''));
        if ($line === '') {
            $line = trim((string) ($emb['street_address'] ?? ''));
        }
        return [
            'province'          => trim((string) ($emb['province'] ?? '')),
            'city_municipality' => trim((string) ($emb['city_municipality'] ?? '')),
            'barangay'          => trim((string) ($emb['barangay'] ?? '')),
            'house_street'      => $line,
        ];
    }
    return [
        'province'          => trim((string) ($p['province'] ?? '')),
        'city_municipality' => trim((string) ($p['city_municipality'] ?? '')),
        'barangay'          => trim((string) ($p['barangay'] ?? '')),
        'house_street'      => trim((string) ($p['house_street'] ?? '')),
    ];
}

/**
 * Reject saving a stringified address JSON into a single-line column.
 */
function api_profile_refuse_address_json_blob(string $fieldLabel, string $value): void
{
    $t = trim($value);
    if ($t === '') {
        return;
    }
    if (api_profile_embedded_address_object($t) !== null) {
        throw new InvalidArgumentException(
            $fieldLabel . ' must be a plain text line. Do not send the whole address as one JSON string; send province, city_municipality, barangay, and house_street as separate fields.'
        );
    }
}

function api_profile_last_update(?string $userTs, ?string $patientTs): string
{
    $u = $userTs ? strtotime($userTs) : 0;
    $p = $patientTs ? strtotime($patientTs) : 0;
    $t = max($u, $p);
    if ($t <= 0) {
        return '';
    }
    return date('Y-m-d H:i:s', $t);
}

function api_json_exit(bool $ok, string $message, array $data = []): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $out = array_merge(['success' => $ok, 'message' => $message], $data);
    $flags = JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($out, $flags);
    exit;
}
