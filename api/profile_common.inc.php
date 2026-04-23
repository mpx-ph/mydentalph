<?php
// Shared helpers for mobile app profile (tbl_users + tbl_patients)
declare(strict_types=1);

/**
 * @return array<string,mixed>|null
 */
function api_profile_fetch_user(PDO $pdo, string $userId, string $tenantId): ?array
{
    $st = $pdo->prepare(
        "SELECT user_id, tenant_id, username, email, full_name, role, phone, photo, created_at, updated_at, status
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
    $out = array_merge(['success' => $ok, 'message' => $message], $data);
    echo json_encode($out, JSON_UNESCAPED_SLASHES);
    exit;
}
