<?php

/**
 * /admin/ entry point.
 *
 * Uses the shared auth helpers so a studio account that was deactivated while
 * it was signed in is no longer treated as signed in here, and absolute URLs
 * so the redirect works no matter which path the router was reached through.
 */

require_once __DIR__ . '/../includes/auth.php';

phodio_start_session();

if (phodio_current_admin() !== null) {
    header('Location: /admin/dashboard.php');
    exit;
}

header('Location: /login.php?as=admin');
exit;
