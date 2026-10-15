<?php
/**
 * Deployment health check.
 *
 * Deliberately does not include config/database.php: that file exits with a
 * plain text message when the connection fails, which would hide the reason
 * from whoever is debugging a fresh deployment. This endpoint reports the
 * exact cause instead (missing variable, missing driver, bad credentials,
 * unreachable host).
 *
 * Open /health.php after deploying.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

date_default_timezone_set('Asia/Manila');

$report = [
    'ok' => false,
    'app' => 'Phodio',
    'php' => PHP_VERSION,
    'pdo_pgsql' => extension_loaded('pdo_pgsql'),
];

$url = getenv('DATABASE_URL') ?: ($_ENV['DATABASE_URL'] ?? '');

if ($url === '') {
    $report['database'] = 'not configured';
    $report['error'] = 'DATABASE_URL is empty.';
    $report['hint'] =
        'Add it in Vercel -> Project -> Settings -> Environment Variables ' .
        '(Production and Preview) and redeploy.';

    http_response_code(500);

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return;
}

$dsn = $url;

if (stripos($dsn, 'postgres://') === 0) {
    $dsn = 'pgsql://' . substr($dsn, 11);
}

$parts = parse_url($dsn);

if ($parts === false || empty($parts['host'])) {
    $report['database'] = 'misconfigured';
    $report['error'] = 'DATABASE_URL could not be parsed as a PostgreSQL URI.';
    $report['hint'] =
        'Copy the Session pooler string from Supabase -> Connect. Percent-encode ' .
        'special characters in the password (@ becomes %40, # becomes %23).';

    http_response_code(500);

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return;
}

parse_str((string) ($parts['query'] ?? ''), $query);

if (!$report['pdo_pgsql']) {
    $report['database'] = 'driver missing';
    $report['error'] = 'The pdo_pgsql extension is not enabled in this runtime.';
    $report['hint'] =
        'Check that vercel.json still declares the vercel-php@0.9.0 runtime ' .
        'for api/**/*.php.';

    http_response_code(500);

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    return;
}

$report['host'] = $parts['host'] . ':' . ($parts['port'] ?? 5432);
$report['database_name'] = ltrim((string) ($parts['path'] ?? '/postgres'), '/');

try {
    $pdo = new PDO(
        sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $parts['host'],
            $parts['port'] ?? 5432,
            ltrim((string) ($parts['path'] ?? '/postgres'), '/'),
            $query['sslmode'] ?? 'require'
        ),
        (string) ($parts['user'] ?? 'postgres'),
        (string) ($parts['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]
    );

    $tables = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = 'public'"
    )->fetchColumn();

    $hasSessions = (bool) $pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = 'public' AND table_name = 'phodio_sessions'"
    )->fetchColumn();

    $report['ok'] = true;
    $report['database'] = 'connected';
    $report['public_tables'] = (int) $tables;
    $report['phodio_sessions'] = $hasSessions;

    if ($tables < 1) {
        $report['hint'] =
            'Connected, but no tables were found. Run supabase_schema.sql in ' .
            'the Supabase SQL Editor.';
    } elseif (!$hasSessions) {
        $report['hint'] =
            'Run api/db/migrations/20261015_phodio_sessions.sql: the client ' .
            'portal needs the phodio_sessions table.';
    }
} catch (Throwable $error) {
    $report['database'] = 'failed';
    $report['error'] = $error->getMessage();
    $report['hint'] =
        'Verify the password in DATABASE_URL, that it is the Session pooler ' .
        'host (port 5432) and that the Supabase project is not paused.';

    http_response_code(500);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
