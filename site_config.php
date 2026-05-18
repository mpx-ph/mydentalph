<?php
/**
 * Canonical public hostname (no scheme). Used when HTTP_HOST is unavailable.
 */
if (!defined('MYDENTAL_SITE_HOST')) {
    define('MYDENTAL_SITE_HOST', 'mydentalph.gt.tc');
}

if (!function_exists('mydental_site_host')) {
    function mydental_site_host(): string
    {
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        return $host !== '' ? $host : MYDENTAL_SITE_HOST;
    }
}

if (!function_exists('mydental_site_base_url')) {
    function mydental_site_base_url(): string
    {
        $_s = isset($_SERVER) ? $_SERVER : [];
        $host = trim((string) ($_s['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $https = (!empty($_s['HTTPS']) && $_s['HTTPS'] !== 'off')
                || (isset($_s['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_s['HTTP_X_FORWARDED_PROTO']) === 'https');
            return ($https ? 'https' : 'http') . '://' . $host;
        }
        return 'https://' . MYDENTAL_SITE_HOST;
    }
}
