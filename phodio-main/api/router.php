<?php
/**
 * Phodio front controller for Vercel.
 *
 * Vercel builds ONE serverless function per PHP file, and each function
 * gets its own ephemeral filesystem (/tmp). Phodio uses relative links,
 * PHP file sessions and shared include files, so the whole application is
 * served through this single entrypoint instead:
 *
 *   - vercel.json rewrites every .php URL to this file,
 *   - this script resolves the real page under api/ and includes it.
 *
 * This fixes two deployment problems:
 *   1. Browsers downloading raw .php source instead of viewing the page
 *      (PHP files that are not built as functions are served as static
 *      downloads by Vercel). With this router, every .php URL executes.
 *   2. Login state getting lost between pages (sessions saved in /tmp of
 *      different functions never see each other). One function serving
 *      all pages keeps the PHP session alive across requests.
 */

// The session directory configured in api/php.ini must exist before any
// page calls session_start().
if (!is_dir('/tmp/phodio-sessions')) {
    @mkdir('/tmp/phodio-sessions', 0700, true);
}

// Resolve relative paths (e.g. uploads/) against the api directory.
chdir(__DIR__);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (!is_string($requestPath) || $requestPath === '') {
    $requestPath = '/';
}

// Accept direct /api/... paths as well as the clean public URLs.
if (strpos($requestPath, '/api/') === 0) {
    $requestPath = substr($requestPath, 4); // keep the leading slash
}

$relative = ltrim($requestPath, '/');

// Homepage and directory URLs resolve to their index.php.
if ($relative === '' || substr($relative, -1) === '/') {
    $relative .= 'index.php';
}

// This router is not a page itself.
if ($relative === 'router.php' || $relative === 'api/router.php') {
    http_response_code(404);
    exit('Not found');
}

// Never serve anything outside the api directory.
$base = realpath(__DIR__);

if ($base === false) {
    http_response_code(500);
    exit('Server error');
}

$target = $base . '/' . $relative;
$resolved = realpath($target);

if ($resolved === false || strpos($resolved, $base . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('Not found');
}

if (!is_file($resolved)) {
    http_response_code(404);
    exit('Not found');
}

if (substr($resolved, -4) !== '.php') {
    // Static asset reached through the router: let PHP's built-in
    // server (used by the Vercel PHP runtime) stream the file.
    return false;
}

// Present the included page as if it was requested directly, so code
// using $_SERVER['PHP_SELF'] (admin sidebar / client nav highlighting)
// keeps working unchanged.
$_SERVER['PHP_SELF'] = '/' . $relative;
$_SERVER['SCRIPT_NAME'] = '/' . $relative;
$_SERVER['SCRIPT_FILENAME'] = $resolved;

require $resolved;
