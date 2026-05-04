<?php
/**
 * Per-clinic session isolation on one domain: separate session cookie names (MDCLS{slug})
 * with path "/" so slug URLs and /clinic/api/* share the same cookie.
 *
 * Requests without a resolvable clinic slug keep the default PHP session name (provider root, /clinic/* legacy).
 */

if (!isset($GLOBALS['_clinic_session_scope_configured'])) {
    $GLOBALS['_clinic_session_scope_configured'] = false;
}

/**
 * @param string|null $s
 * @return string|null Normalized slug or null if invalid
 */
function clinic_normalize_slug($s) {
    if ($s === null) {
        return null;
    }
    $s = strtolower(trim((string) $s));
    return ($s !== '' && preg_match('/^[a-z0-9\-]+$/', $s)) ? $s : null;
}

/**
 * Extract clinic slug from a URL path (REQUEST_URI or Referer), or null.
 *
 * @param string $path
 * @return string|null
 */
function clinic_session_slug_from_uri_path($path) {
    $path = '/' . trim(str_replace('\\', '/', $path), '/');
    if ($path === '/') {
        return null;
    }
    $segments = array_values(array_filter(explode('/', trim($path, '/')), static function ($p) {
        return $p !== '';
    }));
    if ($segments === []) {
        return null;
    }

    if (count($segments) >= 3
        && strtolower($segments[0]) === 'sign'
        && strtolower($segments[1]) === 'details'
    ) {
        $slug = strtolower($segments[2]);
        return preg_match('/^[a-z0-9\-]+$/', $slug) ? $slug : null;
    }

    static $reserved = array(
        '404', 'maintenance', 'denied', 'privacy', 'tos', 'superadmin', 'clinic',
        'sign', 'api', 'provider', 'clinictemplate',
    );
    $first = strtolower($segments[0]);
    if (in_array($first, $reserved, true)) {
        return null;
    }
    if (!preg_match('/^[a-z0-9\-]+$/', $first)) {
        return null;
    }
    return $first;
}

/**
 * @return string|null
 */
function clinic_session_try_slug_from_referer() {
    if (empty($_SERVER['HTTP_REFERER'])) {
        return null;
    }
    $refPath = parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    if ($refPath === null || $refPath === '' || $refPath === '/') {
        return null;
    }
    return clinic_session_slug_from_uri_path((string) $refPath);
}

/**
 * Resolve slug from GET, request path, then Referer.
 *
 * @return string|null
 */
function clinic_resolve_session_slug_from_request() {
    if (!empty($_GET['clinic_slug'])) {
        $s = clinic_normalize_slug((string) $_GET['clinic_slug']);
        if ($s !== null) {
            return $s;
        }
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $path = parse_url($uri, PHP_URL_PATH);
    if ($path !== null && $path !== '') {
        $fromUri = clinic_session_slug_from_uri_path($path);
        if ($fromUri !== null) {
            return $fromUri;
        }
    }
    return clinic_session_try_slug_from_referer();
}

/**
 * @param string $slug
 * @return string
 */
function clinic_session_cookie_name_for_slug($slug) {
    $sanitized = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($slug));
    if ($sanitized === '' || ctype_digit($sanitized)) {
        $sanitized = 's' . substr(hash('sha256', $slug), 0, 20);
    }
    $name = 'MDCLS' . $sanitized;
    if (strlen($name) > 128) {
        $name = 'MDCLS' . substr(hash('sha256', $slug), 0, 40);
    }
    return $name;
}

/**
 * Set session cookie name and cookie params. Must run before session_start().
 * Safe to call multiple times; first call wins.
 *
 * @param string|null $explicitSlug Raw clinic_slug from caller (e.g. JSON); normalized inside.
 */
function clinic_session_configure($explicitSlug = null) {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    if (!empty($GLOBALS['_clinic_session_scope_configured'])) {
        return;
    }
    $GLOBALS['_clinic_session_scope_configured'] = true;

    $slug = clinic_normalize_slug($explicitSlug);
    if ($slug === null) {
        $slug = clinic_resolve_session_slug_from_request();
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    if ($slug !== null) {
        session_name(clinic_session_cookie_name_for_slug($slug));
    }

    session_set_cookie_params(0, '/', '', $secure, true);
}

/**
 * @param string|null $explicitSlug Optional slug hint (same as clinic_session_configure).
 */
function clinic_session_start($explicitSlug = null) {
    clinic_session_configure($explicitSlug);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}
