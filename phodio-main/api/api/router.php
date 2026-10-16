<?php

/**
 * Front controller.
 *
 * vercel.json sends every request here and this file dispatches it to the
 * matching PHP script, which keeps the whole application inside a SINGLE
 * Serverless Function.
 *
 * That is a hard requirement, not an optimisation: the Vercel Hobby plan
 * allows only 12 Serverless Functions per deployment, and this application
 * has 38 PHP files. Declaring them all as functions fails the build with
 * "No more than 12 Serverless Functions can be added to a Deployment on the
 * Hobby plan."
 *
 * The browser URL is preserved by Vercel's rewrites, so relative links,
 * form actions and Location: redirects inside the application keep working
 * exactly as they did on a normal PHP host.
 */

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// PHP_SELF is used by the admin sidebar and the client header to highlight
// the active menu item. Point it at the URL the browser actually asked for
// instead of this file.
$_SERVER['PHP_SELF'] = $requestPath;
$_SERVER['SCRIPT_NAME'] = $requestPath;

$relative = ltrim($requestPath, '/');

if ($relative === '') {
    $relative = 'index.php';
} elseif ($relative === 'admin' || $relative === 'admin/') {
    $relative = 'admin/index.php';
}

// Static files that sit inside the api/ folder are bundled into the function
// rather than served by Vercel, so the admin stylesheet is a PHP script that
// emits it.
if ($relative === 'admin/assets/css/style.css' || $relative === 'assets/css/style.css') {
    $relative = 'admin/assets/css.php';
}

$target = __DIR__ . '/' . $relative;

if (substr($relative, -4) === '.php' && is_file($target)) {
    // A few scripts still use relative paths; make them resolve against the
    // application root.
    chdir(__DIR__);

    require $target;

    return;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');

echo 'Not found: ' . $relative . "\n";
