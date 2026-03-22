<?php

/**
 * Session + tbl_users fields for superadmin layout (name, email, photo).
 */
function superadmin_initials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    $sub = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $up = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';
    $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
    if (count($parts) >= 2) {
        $a = $sub($parts[0], 0, 1);
        $b = $sub($parts[count($parts) - 1], 0, 1);

        return $up($a . $b);
    }

    return $up($sub($name, 0, min(2, $len)));
}

/**
 * @return array{full_name: string, email: string, username: string, photo: string}
 */
function superadmin_current_user(PDO $pdo): array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $out = [
        'full_name' => isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : '',
        'email' => isset($_SESSION['email']) ? trim((string) $_SESSION['email']) : '',
        'username' => isset($_SESSION['username']) ? trim((string) $_SESSION['username']) : '',
        'photo' => '',
    ];

    if ($out['full_name'] === '' && $out['username'] !== '') {
        $out['full_name'] = $out['username'];
    }

    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    if ($uid !== null && $uid !== '') {
        try {
            $stmt = $pdo->prepare('SELECT full_name, email, username, photo FROM tbl_users WHERE user_id = ? LIMIT 1');
            $stmt->execute([$uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $fn = trim((string) ($row['full_name'] ?? ''));
                if ($fn !== '') {
                    $out['full_name'] = $fn;
                }
                $em = trim((string) ($row['email'] ?? ''));
                if ($em !== '') {
                    $out['email'] = $em;
                }
                $un = trim((string) ($row['username'] ?? ''));
                if ($un !== '') {
                    $out['username'] = $un;
                }
                $out['photo'] = trim((string) ($row['photo'] ?? ''));
            }
        } catch (Throwable $e) {
            // keep session fallbacks
        }
    }

    if ($out['full_name'] === '') {
        $out['full_name'] = 'Super Admin';
    }

    $cached = $out;

    return $cached;
}
