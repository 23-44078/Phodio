<?php

require_once __DIR__ . '/config/database.php';

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Restore database-backed client session
|--------------------------------------------------------------------------
*/

function phodio_restore_dashboard_session(PDO $pdo): bool
{
    /*
     * Normal PHP session is available.
     */
    if (!empty($_SESSION['client_id'])) {
        return true;
    }


    /*
     * Vercel PHP sessions may not survive between requests,
     * so restore the client from the database session cookie.
     */

    $sessionId = $_COOKIE['phodio_session'] ?? '';

    if ($sessionId === '') {
        return false;
    }


    $stmt = $pdo->prepare("
        SELECT
            client_id,
            client_username,
            client_name
        FROM phodio_sessions
        WHERE session_id = :session_id
          AND expires_at > NOW()
        LIMIT 1
    ");

    $stmt->execute([
        'session_id' => $sessionId
    ]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$session) {
        return false;
    }


    /*
     * Rebuild PHP session.
     */

    $_SESSION['client_id'] =
        (int) $session['client_id'];

    $_SESSION['client_username'] =
        $session['client_username'] ?? '';

    $_SESSION['client'] =
        $session['client_username'] ?? '';

    $_SESSION['client_name'] =
        $session['client_name'] ?? '';


    return true;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$pdo = $conn->pdo();


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!phodio_restore_dashboard_session($pdo)) {

    header(
        'Location: /client_login.php'
    );

    exit;
}


$clientId =
    (int) ($_SESSION['client_id'] ?? 0);


if ($clientId <= 0) {

    header(
        'Location: /client_login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Client information
|--------------------------------------------------------------------------
*/

$clientStmt = $pdo->prepare("
    SELECT
        id,
        firstname,
        lastname,
        username,
        phone,
        profile_image
    FROM users
    WHERE id = :client_id
    LIMIT 1
");

$clientStmt->execute([
    'client_id' => $clientId
]);

$client = $clientStmt->fetch(PDO::FETCH_ASSOC);


if (!$client) {

    /*
     * Remove invalid session.
     */

    setcookie(
        'phodio_session',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    header(
        'Location: /client_login.php'
    );

    exit;
}


$firstName =
    trim(
        (string) ($client['firstname'] ?? '')
    );

$lastName =
    trim(
        (string) ($client['lastname'] ?? '')
    );

$fullName =
    trim(
        $firstName . ' ' . $lastName
    );

if ($fullName === '') {
    $fullName = 'Client';
}


$firstNameDisplay =
    $firstName !== ''
        ? $firstName
        : 'there';


$username =
    (string) ($client['username'] ?? '');

$phone =
    (string) ($client['phone'] ?? '');


/*
|--------------------------------------------------------------------------
| Profile image
|--------------------------------------------------------------------------
*/

$profileImage = '';

$profileFile =
    basename(
        (string) ($client['profile_image'] ?? '')
    );

if (
    $profileFile !== '' &&
    $profileFile !== 'default.png'
) {

    $profilePath =
        __DIR__ .
        '/uploads/profile/' .
        $profileFile;

    if (is_file($profilePath)) {

        $profileImage =
            '/uploads/profile/' .
            rawurlencode($profileFile);
    }
}


/*
|--------------------------------------------------------------------------
| Booking statistics
|--------------------------------------------------------------------------
*/

$totalBookings = 0;
$pendingBookings = 0;
$approvedBookings = 0;
$completedBookings = 0;


$countStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_bookings,

        COUNT(
            CASE
                WHEN LOWER(status) = 'pending'
                THEN 1
            END
        ) AS pending_bookings,

        COUNT(
            CASE
                WHEN LOWER(status) IN (
                    'approved',
                    'confirmed'
                )
                THEN 1
            END
        ) AS approved_bookings,

        COUNT(
            CASE
                WHEN LOWER(status) IN (
                    'completed',
                    'complete',
                    'done'
                )
                THEN 1
            END
        ) AS completed_bookings

    FROM bookings

    WHERE client_id = :client_id
");

$countStmt->execute([
    'client_id' => $clientId
]);

$counts =
    $countStmt->fetch(PDO::FETCH_ASSOC);


if ($counts) {

    $totalBookings =
        (int) (
            $counts['total_bookings'] ?? 0
        );

    $pendingBookings =
        (int) (
            $counts['pending_bookings'] ?? 0
        );

    $approvedBookings =
        (int) (
            $counts['approved_bookings'] ?? 0
        );

    $completedBookings =
        (int) (
            $counts['completed_bookings'] ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| Recent bookings
|--------------------------------------------------------------------------
*/

$recentStmt = $pdo->prepare("
    SELECT
        id,
        service_type,
        package_type,
        motif,
        price,
        booking_date,
        start_time,
        status,
        status_note,
        created_at
    FROM bookings
    WHERE client_id = :client_id
    ORDER BY
        created_at DESC,
        id DESC
    LIMIT 8
");

$recentStmt->execute([
    'client_id' => $clientId
]);

$recentBookings =
    $recentStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

if (!function_exists('phodio_escape')) {
    function phodio_escape($value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}


function phodio_status_class($status): string
{
    $status =
        strtolower(
            trim(
                (string) $status
            )
        );

    if (
        in_array(
            $status,
            ['approved', 'confirmed'],
            true
        )
    ) {
        return 'status-approved';
    }

    if (
        in_array(
            $status,
            ['completed', 'complete', 'done'],
            true
        )
    ) {
        return 'status-completed';
    }

    if ($status === 'pending') {
        return 'status-pending';
    }

    if (
        in_array(
            $status,
            ['cancelled', 'canceled', 'rejected'],
            true
        )
    ) {
        return 'status-cancelled';
    }

    return 'status-default';
}


function phodio_format_date($date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp =
        strtotime(
            (string) $date
        );

    if (!$timestamp) {
        return (string) $date;
    }

    return date(
        'M d, Y',
        $timestamp
    );
}


function phodio_format_time($time): string
{
    if (!$time) {
        return '';
    }

    $timestamp =
        strtotime(
            (string) $time
        );

    if (!$timestamp) {
        return (string) $time;
    }

    return date(
        'g:i A',
        $timestamp
    );
}


function phodio_format_price($price): string
{
    if (
        $price === null ||
        $price === ''
    ) {
        return '—';
    }

    return '₱' .
        number_format(
            (float) $price,
            2
        );
}


$initial =
    strtoupper(
        substr(
            $fullName,
            0,
            1
        )
    );

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Dashboard | SOULPRINT
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css"
    >

    <style>
        /* Shared design system — aligned with client_booking.php */

        :root{
            --page:#0b0d12;
            --panel:#151922;
            --panel-soft:#1b2130;
            --line:#2b3242;
            --accent:#ef4444;
            --blue:#7da8ff;
            --muted:#a6afbf;
        }

        body{
            background:var(--page);
            color:#f8fafc;
            font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;
        }

        .client-main{
            max-width:1440px;
            margin:auto;
            padding:30px 22px 48px;
        }

        .hero{
            background:
                radial-gradient(
                    circle at 80% 10%,
                    rgba(239,68,68,.2),
                    transparent 40%
                ),
                linear-gradient(
                    135deg,
                    #1b2130,
                    #11151e
                );

            border:1px solid var(--line);
            border-radius:20px;
            padding:28px 30px;
            margin-bottom:24px;
        }

        .hero h1{
            font-weight:800;
            letter-spacing:-.04em;
        }

        .eyebrow{
            color:#ff9696;
            text-transform:uppercase;
            letter-spacing:.14em;
            font-size:.72rem;
            font-weight:800;
        }

        .text-secondary-custom{
            color:var(--muted)!important;
        }

        .btn-primary{
            background:var(--accent);
            border-color:var(--accent);
            font-weight:700;
        }

        .btn-primary:hover{
            background:#d93636;
            border-color:#d93636;
        }

        .status-chip{
            display:inline-flex;
            align-items:center;
            border-radius:999px;
            padding:5px 10px;
            font-size:.76rem;
            font-weight:750;
            white-space:nowrap;
        }

        .status-pending{
            background:rgba(245,158,11,.14);
            color:#fbbf24;
        }

        .status-approved{
            background:rgba(59,130,246,.15);
            color:#93c5fd;
        }

        .status-completed{
            background:rgba(34,197,94,.16);
            color:#86efac;
        }

        .status-cancelled,
        .status-default{
            background:rgba(148,163,184,.14);
            color:#cbd5e1;
        }

        /* --------------------------------------------------
           STAT CARDS
        -------------------------------------------------- */

        .stats{
            display:grid;
            grid-template-columns:repeat(4,1fr);
            gap:14px;
            margin-bottom:24px;
        }

        .stat-card{
            position:relative;
            overflow:hidden;
            padding:22px;
            border:1px solid var(--line);
            border-radius:16px;
            background:var(--panel);
        }

        .stat-card::after{
            content:"";
            position:absolute;
            width:90px;
            height:90px;
            right:-42px;
            bottom:-42px;
            border-radius:50%;
            background:rgba(239,68,68,.07);
        }

        .stat-icon{
            width:36px;
            height:36px;
            display:flex;
            align-items:center;
            justify-content:center;
            margin-bottom:17px;
            border-radius:10px;
            background:rgba(239,68,68,.1);
            color:#ff9696;
            font-size:1.1rem;
        }

        .stat-card:nth-child(2) .stat-icon{
            background:rgba(245,158,11,.14);
            color:#fbbf24;
        }

        .stat-card:nth-child(3) .stat-icon{
            background:rgba(59,130,246,.15);
            color:#93c5fd;
        }

        .stat-card:nth-child(4) .stat-icon{
            background:rgba(16,185,129,.16);
            color:#6ee7b7;
        }

        .stat-label{
            color:var(--muted);
            font-size:.76rem;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.07em;
        }

        .stat-value{
            margin-top:5px;
            font-size:2rem;
            line-height:1;
            font-weight:850;
            letter-spacing:-.04em;
        }

        /* --------------------------------------------------
           GRID + PANELS
        -------------------------------------------------- */

        .content-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:14px;
            margin-bottom:24px;
        }

        .panel{
            border:1px solid var(--line);
            border-radius:16px;
            background:var(--panel);
            overflow:hidden;
        }

        .panel-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:20px 22px;
            border-bottom:1px solid var(--line);
        }

        .panel-title{
            display:flex;
            align-items:center;
            gap:9px;
            font-size:.82rem;
            font-weight:800;
            letter-spacing:.04em;
            text-transform:uppercase;
        }

        .panel-title i{
            color:#ff9696;
            font-size:1rem;
        }

        .panel-body{
            padding:22px;
        }

        /* --------------------------------------------------
           PROFILE
        -------------------------------------------------- */

        .profile-main{
            display:flex;
            align-items:center;
            gap:15px;
            margin-bottom:22px;
        }

        .profile-avatar{
            width:58px;
            height:58px;
            flex-shrink:0;
            display:flex;
            align-items:center;
            justify-content:center;
            overflow:hidden;
            border-radius:16px;
            background:linear-gradient(135deg,#ef4444,#9f1239);
            color:#fff;
            font-size:1.25rem;
            font-weight:850;
        }

        .profile-avatar img{
            width:100%;
            height:100%;
            object-fit:cover;
        }

        .profile-main strong{
            display:block;
            margin-bottom:4px;
            font-size:1rem;
        }

        .profile-main span{
            color:var(--muted);
            font-size:.77rem;
        }

        .info-list{
            display:grid;
            gap:13px;
        }

        .info-row{
            display:flex;
            justify-content:space-between;
            gap:20px;
            padding-bottom:12px;
            border-bottom:1px solid rgba(255,255,255,.06);
        }

        .info-row:last-child{
            padding-bottom:0;
            border-bottom:0;
        }

        .info-label{
            color:var(--muted);
            font-size:.77rem;
        }

        .info-value{
            max-width:65%;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
            color:#dce2ec;
            font-size:.78rem;
            font-weight:650;
            text-align:right;
        }

        /* --------------------------------------------------
           QUICK ACTION
        -------------------------------------------------- */

        .quick-action{
            display:flex;
            flex-direction:column;
            justify-content:space-between;
            min-height:210px;
        }

        .quick-description{
            color:var(--muted);
            font-size:.84rem;
            line-height:1.7;
        }

        .quick-button{
            display:flex;
            align-items:center;
            justify-content:space-between;
            width:100%;
            margin-top:24px;
            padding:15px 16px;
            border:1px solid var(--line);
            border-radius:12px;
            background:var(--panel-soft);
            color:#fff;
            text-decoration:none;
            font-size:.83rem;
            font-weight:800;
            transition:.2s;
        }

        .quick-button:hover{
            border-color:var(--accent);
            background:rgba(239,68,68,.07);
            color:#fff;
        }

        .quick-button span{
            display:flex;
            align-items:center;
            gap:9px;
        }

        .quick-button i{
            color:#ff9696;
            font-size:1.1rem;
        }

        /* --------------------------------------------------
           RECENT BOOKINGS
        -------------------------------------------------- */

        .bookings-panel{
            width:100%;
        }

        .view-all{
            color:#ff9696;
            font-size:.74rem;
            font-weight:750;
            text-decoration:none;
        }

        .view-all:hover{
            color:#ff9696;
            text-decoration:underline;
        }

        .table-wrap{
            width:100%;
            overflow-x:auto;
        }

        .bookings-panel table{
            width:100%;
            border-collapse:collapse;
            min-width:760px;
        }

        .bookings-panel th{
            padding:14px 20px;
            border-bottom:1px solid var(--line);
            color:var(--muted);
            font-size:.67rem;
            font-weight:800;
            letter-spacing:.08em;
            text-align:left;
            text-transform:uppercase;
        }

        .bookings-panel td{
            padding:17px 20px;
            border-bottom:1px solid rgba(255,255,255,.05);
            color:#cbd3df;
            font-size:.78rem;
        }

        .bookings-panel tr:last-child td{
            border-bottom:0;
        }

        .booking-title{
            color:#f8fafc;
            font-weight:750;
        }

        .booking-service{
            margin-top:4px;
            color:var(--muted);
            font-size:.7rem;
        }

        /* --------------------------------------------------
           EMPTY STATE
        -------------------------------------------------- */

        .empty-state{
            padding:65px 25px;
            text-align:center;
        }

        .empty-icon{
            width:58px;
            height:58px;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 15px;
            border:1px solid var(--line);
            border-radius:16px;
            background:var(--panel-soft);
            color:var(--muted);
            font-size:1.5rem;
        }

        .empty-state h3{
            margin:0 0 7px;
            font-size:1rem;
        }

        .empty-state p{
            max-width:390px;
            margin:0 auto 20px;
            color:var(--muted);
            font-size:.78rem;
            line-height:1.6;
        }

        /* --------------------------------------------------
           FOOTER
        -------------------------------------------------- */

        footer{
            margin-top:30px;
            padding-top:20px;
            border-top:1px solid rgba(255,255,255,.06);
            color:#505a6c;
            font-size:.7rem;
            text-align:center;
        }

        /* --------------------------------------------------
           RESPONSIVE
        -------------------------------------------------- */

        @media(max-width:900px){
            .stats{
                grid-template-columns:repeat(2,1fr);
            }

            .content-grid{
                grid-template-columns:1fr;
            }
        }

        @media(max-width:768px){
            .client-main{
                padding:18px 12px 30px;
            }

            .hero{
                padding:22px;
            }
        }

        @media(max-width:650px){
            .stats{
                gap:10px;
            }

            .stat-card{
                padding:17px;
            }

            .stat-value{
                font-size:1.7rem;
            }

            .panel-header,
            .panel-body{
                padding:17px;
            }
        }

    </style>

</head>


<body>


<?php include __DIR__ . '/includes/client_header.php'; ?>


<!-- ======================================================
     MAIN
====================================================== -->

<main class="client-main">


    <!-- HERO -->

    <section
        class="hero d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center"
    >


        <div>

            <div class="eyebrow mb-2">
                Client Portal
            </div>


            <h1 class="h2 mb-2">
                Welcome back,
                <?= phodio_escape($firstNameDisplay) ?>!
            </h1>


            <p class="mb-0 text-secondary-custom">
                Here's an overview of your Phodio bookings.
            </p>

        </div>


        <a
            class="btn btn-primary px-4 py-2"
            href="/client_booking.php"
        >

            <i class="ri-calendar-schedule-line me-2"></i>

            Make a Booking

        </a>


    </section>


    <!-- STATISTICS -->

    <section class="stats">


        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-calendar-line"></i>

            </div>


            <div class="stat-label">
                Total Bookings
            </div>


            <div class="stat-value">
                <?= $totalBookings ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-time-line"></i>

            </div>


            <div class="stat-label">
                Pending
            </div>


            <div class="stat-value">
                <?= $pendingBookings ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-checkbox-circle-line"></i>

            </div>


            <div class="stat-label">
                Approved
            </div>


            <div class="stat-value">
                <?= $approvedBookings ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-check-double-line"></i>

            </div>


            <div class="stat-label">
                Completed
            </div>


            <div class="stat-value">
                <?= $completedBookings ?>
            </div>

        </div>


    </section>


    <!-- PROFILE + QUICK ACTION -->

    <section class="content-grid">


        <!-- PROFILE -->

        <div class="panel">


            <div class="panel-header">

                <div class="panel-title">

                    <i class="ri-user-3-line"></i>

                    My Profile

                </div>

            </div>


            <div class="panel-body">


                <div class="profile-main">


                    <div class="profile-avatar">

                        <?php if ($profileImage !== ''): ?>

                            <img
                                src="<?= phodio_escape($profileImage) ?>"
                                alt="Profile"
                            >

                        <?php else: ?>

                            <?= phodio_escape($initial) ?>

                        <?php endif; ?>

                    </div>


                    <div>

                        <strong>
                            <?= phodio_escape($fullName) ?>
                        </strong>

                        <span>
                            Phodio Client
                        </span>

                    </div>


                </div>


                <div class="info-list">


                    <div class="info-row">

                        <span class="info-label">
                            Name
                        </span>

                        <span class="info-value">
                            <?= phodio_escape($fullName) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Email
                        </span>

                        <span class="info-value">
                            <?= phodio_escape($username) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Phone
                        </span>

                        <span class="info-value">
                            <?= phodio_escape(
                                $phone !== ''
                                    ? $phone
                                    : 'Not provided'
                            ) ?>
                        </span>

                    </div>


                </div>


            </div>

        </div>


        <!-- QUICK ACTION -->

        <div class="panel">


            <div class="panel-header">

                <div class="panel-title">

                    <i class="ri-camera-lens-line"></i>

                    Your Photography Journey

                </div>

            </div>


            <div class="panel-body quick-action">


                <div>

                    <div class="eyebrow mb-2">
                        Capture the moment
                    </div>


                    <p class="quick-description">

                        Ready for your next session?
                        Choose a service, select your preferred
                        schedule, and send your booking request
                        directly to Phodio.

                    </p>

                </div>


                <a
                    href="/client_booking.php"
                    class="quick-button"
                >

                    <span>

                        <i class="ri-camera-3-line"></i>

                        Start a New Booking

                    </span>


                    <i class="ri-arrow-right-line"></i>

                </a>


            </div>

        </div>


    </section>


    <!-- RECENT BOOKINGS -->

    <section class="panel bookings-panel">


        <div class="panel-header">


            <div class="panel-title">

                <i class="ri-history-line"></i>

                Recent Bookings

            </div>


            <?php if (!empty($recentBookings)): ?>

                <a
                    href="/client_booking.php"
                    class="view-all"
                >
                    Book another
                </a>

            <?php endif; ?>


        </div>


        <?php if (empty($recentBookings)): ?>


            <div class="empty-state">


                <div class="empty-icon">

                    <i class="ri-calendar-event-line"></i>

                </div>


                <h3>
                    No bookings yet
                </h3>


                <p>

                    Your upcoming photography sessions
                    will appear here once you make your
                    first booking.

                </p>


                <a
                    href="/client_booking.php"
                    class="btn btn-primary px-4 py-2"
                >

                    <i class="ri-add-line me-2"></i>

                    Make Your First Booking

                </a>


            </div>


        <?php else: ?>


            <div class="table-wrap">


                <table>

                    <thead>

                        <tr>

                            <th>
                                Booking
                            </th>

                            <th>
                                Package
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Time
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $recentBookings
                            as $booking
                        ): ?>


                            <?php

                            $bookingTitle =
                                trim(
                                    (string) (
                                        $booking['package_type']
                                        ?? ''
                                    )
                                );

                            if (
                                $bookingTitle === ''
                            ) {
                                $bookingTitle =
                                    'Photography Session';
                            }


                            $serviceType =
                                trim(
                                    (string) (
                                        $booking['service_type']
                                        ?? ''
                                    )
                                );


                            $packageType =
                                trim(
                                    (string) (
                                        $booking['package_type']
                                        ?? ''
                                    )
                                );


                            $status =
                                trim(
                                    (string) (
                                        $booking['status']
                                        ?? 'Pending'
                                    )
                                );

                            if ($status === '') {
                                $status = 'Pending';
                            }

                            ?>


                            <tr>


                                <td>

                                    <div class="booking-title">

                                        <?= phodio_escape(
                                            $bookingTitle
                                        ) ?>

                                    </div>


                                    <?php if (
                                        $serviceType !== ''
                                    ): ?>

                                        <div class="booking-service">

                                            <?= phodio_escape(
                                                $serviceType
                                            ) ?>

                                        </div>

                                    <?php endif; ?>


                                </td>


                                <td>

                                    <?= phodio_escape(
                                        $packageType !== ''
                                            ? $packageType
                                            : '—'
                                    ) ?>

                                </td>


                                <td>

                                    <?= phodio_escape(
                                        phodio_format_date(
                                            $booking['booking_date']
                                            ?? null
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= phodio_escape(
                                        phodio_format_time(
                                            $booking['start_time']
                                            ?? null
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= phodio_escape(
                                        phodio_format_price(
                                            $booking['price']
                                            ?? null
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="status-chip <?= phodio_status_class($status) ?>"
                                    >

                                        <?= phodio_escape(
                                            ucfirst(
                                                strtolower(
                                                    $status
                                                )
                                            )
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>

                </table>


            </div>


        <?php endif; ?>


    </section>


    <footer>

        © <?= date('Y') ?>
        Phodio · SoulPrint Studio
        <span>·</span>
        Your moments, your story.

    </footer>


</main>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>
