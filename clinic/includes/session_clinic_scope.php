<?php
/**
 * Per-clinic session isolation on one domain:
 * - Slug URLs use cookie MDCLS{slug} (path /).
 * - Files under /clinic/ without a slug use MDCLSLEGACY — never PHPSESSID — so provider/root pages
 *   keep their own default session and do not see clinic Staff logins.
 * - Referer-based slug detection applies only to /clinic/api/* (URLs lack /{slug}/).
 */

if (!defined('MDCLS_LEGACY_SESSION_NAME')) {
    define('MDCLS_LEGACY_SESSION_NAME', 'MDCLSLEGACY');
}

if (!isset($GLOBALS['_clinic_session_scope_configured'])) {
    $GLOBALS['_clinic_session_scope_configured'] = false;
}

/**
 * True when this request executes a PHP file inside the clinic template directory (filesystem path contains /clinic/).
 *
 * @return bool
 */
function clinic_request_is_clinic_filesystem_script() {
    $sf = isset($_SERVER['SCRIPT_FILENAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_FILENAME']) : '';
    return $sf !== '' && strpos($sf, '/clinic/') !== false;
}

/**
 * Start the default PHP session cookie (PHPSESSID). Use only for bridges that must read provider/root session
 * while living under /clinic/ (e.g. ProviderMyDentalSSO.php). Never use for normal clinic portal pages.
 */
function provider_default_session_start() {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $GLOBALS['_clinic_session_scope_configured'] = false;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('PHPSESSID');
    session_set_cookie_params(0, '/', '', $secure, true);
    session_start();
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
 * Resolve slug from GET and request path. Referer is used only for /clinic/api/* (API paths omit /{slug}/).
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
        $normPath = '/' . trim(str_replace('\\', '/', $path), '/');
        if ($normPath !== '/' && strpos($normPath, '/clinic/api/') !== false) {
            $fromRef = clinic_session_try_slug_from_referer();
            if ($fromRef !== null) {
                return $fromRef;
            }
        }
    }
    return null;
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
    } elseif (clinic_request_is_clinic_filesystem_script()) {
        session_name(MDCLS_LEGACY_SESSION_NAME);
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
