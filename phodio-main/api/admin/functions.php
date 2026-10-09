<?php

/*
 * config/database.php has to load first: it points session.save_path at
 * /tmp/phodio-sessions (Vercel's only writable directory).
 *
 * Starting the session before that happened made studio pages read sessions
 * from PHP's default directory while /login.php wrote them to
 * /tmp/phodio-sessions, so a successful sign-in looked signed out on the very
 * next request. That only worked while api/php.ini happened to be loaded.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Guard for every studio page.
 *
 * The account is re-read from the database on each request so that a role
 * change or a deactivation made by a super admin takes effect immediately.
 */
function checkLogin(): void
{
    phodio_start_session();

    $admin = phodio_current_admin();

    if ($admin === null) {
        header('Location: login.php');
        exit;
    }

    try {
        $pdo = $GLOBALS['conn']->pdo();

        $account = phodio_find_studio_account($pdo, $admin['username']);

        if ($account === null || (int) $account['is_active'] !== 1) {
            $_SESSION = [];
            session_destroy();

            header('Location: login.php');
            exit;
        }

        $role = strtolower(trim((string) ($account['role'] ?? '')));

        if ($role !== PHODIO_ROLE_SUPER_ADMIN) {
            $role = PHODIO_ROLE_ADMIN;
        }

        $displayName = trim((string) ($account['full_name'] ?? ''));

        $_SESSION['admin_id'] = (int) $account['id'];
        $_SESSION['admin_role'] = $role;
        $_SESSION['admin_name'] = $displayName !== ''
            ? $displayName
            : (string) $account['username'];
    } catch (Throwable $error) {
        // A transient database problem must not sign a valid admin out.
        // The session values already in memory are used instead.
    }
}

/**
 * Guard for pages only a super admin may open.
 */
function checkSuperAdmin(): void
{
    checkLogin();

    if (phodio_is_super_admin()) {
        return;
    }

    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>Not available | SOULPRINT</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '<link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">'
        . '</head><body style="background:#0f0f0f;color:#fff;display:flex;align-items:center;'
        . 'justify-content:center;min-height:100vh;margin:0;font-family:system-ui,sans-serif;">'
        . '<div style="max-width:460px;text-align:center;padding:34px;background:#1a1a1a;'
        . 'border:1px solid #2b2b2b;border-radius:16px;margin:16px;">'
        . '<i class="ri-shield-keyhole-line" style="font-size:2.4rem;color:#ef4444"></i>'
        . '<h1 class="h4 fw-bold mt-3 mb-2">Super admin only</h1>'
        . '<p class="text-secondary mb-4" style="color:#a1a1aa">'
        . 'This area is limited to super admin accounts. Ask the studio owner '
        . 'if you need access.</p>'
        . '<a class="btn btn-primary" href="dashboard.php">'
        . '<i class="ri-dashboard-line me-1"></i>Back to dashboard</a>'
        . '</div></body></html>';

    exit;
}

/**
 * Display name of the signed-in studio account.
 */
function currentAdminName(): string
{
    phodio_start_session();

    $name = trim((string) ($_SESSION['admin_name'] ?? ''));

    return $name !== '' ? $name : (string) ($_SESSION['admin'] ?? 'Studio');
}

/**
 * Human-readable role label for badges.
 */
function currentAdminRoleLabel(): string
{
    return phodio_is_super_admin() ? 'Super admin' : 'Admin';
}

function getDashboardData($conn, string $range = 'month'): array
{
    $startDate = $range === 'today' ? date('Y-m-d') : date('Y-m-d', strtotime('-1 month'));

    $stmt = $conn->prepare("SELECT COALESCE(SUM(price), 0) AS total FROM bookings WHERE booking_date >= ? AND status <> 'Cancelled'");
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $income = (float) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE expense_date >= ?');
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $expense = (float) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = $conn->prepare('SELECT COALESCE(SUM(amount), 0) AS total FROM liabilities WHERE created_at >= ?');
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $liabilities = (float) ($stmt->get_result()->fetch_assoc()['total'] ?? 0);

    $stmt = $conn->prepare("SELECT package_type, SUM(price) AS amt FROM bookings WHERE booking_date >= ? AND status <> 'Cancelled' GROUP BY package_type ORDER BY amt DESC");
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $rentals = $stmt->get_result();

    $stmt = $conn->prepare('SELECT description, SUM(amount) AS amt FROM expenses WHERE expense_date >= ? GROUP BY description ORDER BY amt DESC');
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $expenses = $stmt->get_result();

    $stmt = $conn->prepare('SELECT creditor, SUM(amount) AS amt FROM liabilities WHERE created_at >= ? GROUP BY creditor ORDER BY amt DESC');
    $stmt->bind_param('s', $startDate);
    $stmt->execute();
    $liabilitiesList = $stmt->get_result();

    return [
        'stats' => [
            'income' => $income,
            'expense' => $expense,
            'liabilities' => $liabilities,
            'profit' => $income - $expense,
        ],
        'rentals' => $rentals,
        'expenses' => $expenses,
        'liabilities_list' => $liabilitiesList,
    ];
}
