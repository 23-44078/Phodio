<?php
/**
 * Serves the admin stylesheet.
 *
 * Vercel only runs PHP files inside the api/ directory, so assets that live
 * next to the admin pages cannot be served as plain static files. This
 * endpoint reads the same stylesheet and returns it with the correct
 * content type, which keeps the existing
 * <link rel="stylesheet" href="assets/css/style.css"> markup working.
 */

declare(strict_types=1);

$stylesheet = __DIR__ . '/css/style.css';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=300');

if (!is_file($stylesheet)) {
    http_response_code(404);

    echo '/* style.css was not found in the deployment bundle. */';

    return;
}

readfile($stylesheet);
