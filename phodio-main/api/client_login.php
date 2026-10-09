<?php

/**
 * Client sign-in now lives on the unified login page.
 *
 * This file is kept so existing links, bookmarks and the router's
 * "not signed in" redirects keep working.
 */

require_once __DIR__ . '/includes/auth.php';

phodio_start_session();

header('Location: /login.php?as=client', true, 307);
exit;
