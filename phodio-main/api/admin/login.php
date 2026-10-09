<?php

/**
 * Studio sign-in now lives on the unified login page.
 *
 * This file is kept so existing links and bookmarks keep working.
 */

require_once __DIR__ . '/../includes/auth.php';

phodio_start_session();

header('Location: /login.php?as=admin', true, 307);
exit;
