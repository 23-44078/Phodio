<?php

/*
 * checkLogin() first: this page shows the studio's income, expenses and debts.
 */
require_once __DIR__ . '/functions.php';

checkLogin();

$range = isset($_GET['range']) && $_GET['range'] === 'today' ? 'today' : 'month';

$data = getDashboardData($conn, $range);
$stats = $data['stats'];
$rental_breakdown = $data['rentals'];
$liability_breakdown = $data['liabilities_list'];

// The latest Daily Tracker entry drives the goal card.
$today_track = $conn->query(
    "SELECT * FROM daily_tracker ORDER BY track_date DESC LIMIT 1"
)->fetch_assoc();

$goalIncome = (float) ($today_track['income_today'] ?? 0);
$goalTarget = (float) ($today_track['target'] ?? 0);
$goalClients = (int) ($today_track['client_today'] ?? 0);
$gap = $today_track ? ($goalTarget - $goalIncome) : 0;
$goalPercent = $goalTarget > 0
    ? (int) min(100, round(($goalIncome / $goalTarget) * 100))
    : 0;

$rentals = $rental_breakdown ? $rental_breakdown->fetch_all() : [];
$creditors = $liability_breakdown ? $liability_breakdown->fetch_all() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | SOULPRINT</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .filter-group .btn {
            padding: 7px 16px;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            border: 1px solid #333;
            color: #a0a0a0;
        }

        .filter-group .btn-active {
            background: #ce0000 !important;
            border-color: #ce0000 !important;
            color: #fff !important;
        }

        .goal-card {
            background: linear-gradient(145deg, #1a1a1a, #232323);
            border: 1px solid rgba(255, 255, 255, .06);
            border-radius: 12px;
            padding: 22px;
        }

        .goal-bar {
            height: 8px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .08);
            overflow: hidden;
        }

        .goal-bar span {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(90deg, #ce0000, #ff4d4d);
        }

        .quick-link {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: 13px 16px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, .1);
            background: rgba(255, 255, 255, .04);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            transition: background .2s ease, transform .2s ease;
        }

        .quick-link:hover {
            background: rgba(255, 255, 255, .1);
            color: #fff;
            transform: translateY(-1px);
        }

        .quick-link i { font-size: 1.15rem; }
    </style>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="container-fluid py-4">

        <div class="page-head">
            <div>
                <h1 class="page-title"><i class="ri-dashboard-line me-2"></i>Business overview</h1>
                <p class="page-sub">
                    Showing
                    <span class="text-info fw-bold"><?= $range === 'today' ? 'today' : 'the last month' ?></span>
                    · signed in as <?= admin_h(currentAdminName()) ?>
                </p>
            </div>

            <div class="btn-group filter-group">
                <a href="dashboard.php?range=today" class="btn btn-dark <?= $range === 'today' ? 'btn-active' : '' ?>">Today</a>
                <a href="dashboard.php?range=month" class="btn btn-dark <?= $range === 'month' ? 'btn-active' : '' ?>">Monthly</a>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="label">Income</div>
                <div class="value text-success"><?= admin_money($stats['income']) ?></div>
                <div class="meta">Bookings, cancellations excluded</div>
            </div>
            <div class="stat-card">
                <div class="label">Expenses</div>
                <div class="value text-danger"><?= admin_money($stats['expense']) ?></div>
                <div class="meta">Everything recorded as spent</div>
            </div>
            <div class="stat-card">
                <div class="label">Liabilities</div>
                <div class="value text-warning"><?= admin_money($stats['liabilities']) ?></div>
                <div class="meta">Still owed to creditors</div>
            </div>
            <div class="stat-card">
                <div class="label">Net profit</div>
                <div class="value <?= $stats['profit'] < 0 ? 'text-danger' : 'text-success' ?>">
                    <?= admin_money($stats['profit']) ?>
                </div>
                <div class="meta"><?= $stats['profit'] < 0 ? 'Spending exceeds income' : 'Income minus expenses' ?></div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">

                <div class="goal-card mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h6 fw-bold mb-0">
                            <i class="ri-flashlight-line text-warning me-2"></i>Daily goal progress
                        </h2>
                        <span class="chip chip-ok"><?= admin_h(admin_date($today_track['track_date'] ?? '')) ?></span>
                    </div>

                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="label small text-muted text-uppercase fw-bold" style="font-size:.68rem">Income today</div>
                            <div class="h4 fw-bold mb-0"><?= admin_money($goalIncome) ?></div>
                        </div>
                        <div class="col-4">
                            <div class="label small text-muted text-uppercase fw-bold" style="font-size:.68rem">Remaining gap</div>
                            <div class="h4 fw-bold mb-0 <?= $gap > 0 ? 'text-danger' : 'text-success' ?>">
                                <?= ($gap <= 0 && $today_track) ? 'CLEARED' : admin_money($gap) ?>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="label small text-muted text-uppercase fw-bold" style="font-size:.68rem">Total clients</div>
                            <div class="h4 fw-bold mb-0"><?= $goalClients ?></div>
                        </div>
                    </div>

                    <div class="goal-bar" role="progressbar" aria-label="Daily goal progress"
                         aria-valuenow="<?= $goalPercent ?>" aria-valuemin="0" aria-valuemax="100">
                        <span style="width: <?= $goalPercent ?>%"></span>
                    </div>
                    <div class="small text-muted mt-2">
                        <?= $goalPercent ?>% of the <?= admin_money($goalTarget) ?> target
                    </div>
                </div>

                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="ri-pie-chart-line me-2 text-info"></i>Revenue by package
                            </div>
                            <div class="table-responsive">
                                <table class="table table-finance mb-0">
                                    <tbody>
                                        <?php if (count($rentals) > 0): ?>
                                            <?php foreach ($rentals as $rb): ?>
                                                <tr>
                                                    <td><?= admin_h($rb['package_type']) ?></td>
                                                    <td class="text-end text-info fw-bold"><?= admin_money($rb['amt']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <div class="empty-state">
                                                        <i class="ri-pie-chart-line"></i>
                                                        <p>No income in this period yet.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="ri-user-received-2-line me-2 text-warning"></i>Creditor breakdown
                            </div>
                            <div class="table-responsive">
                                <table class="table table-finance mb-0">
                                    <tbody>
                                        <?php if (count($creditors) > 0): ?>
                                            <?php foreach ($creditors as $lb): ?>
                                                <tr>
                                                    <td><?= admin_h($lb['creditor']) ?></td>
                                                    <td class="text-end text-warning fw-bold"><?= admin_money($lb['amt']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td>
                                                    <div class="empty-state">
                                                        <i class="ri-bank-card-line"></i>
                                                        <p>No liabilities recorded.</p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <i class="ri-rocket-2-line me-2 text-primary"></i>Quick access
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="bookings.php" class="quick-link">
                                <i class="ri-calendar-event-line text-info"></i> Bookings &amp; progress
                            </a>
                            <a href="expenses.php" class="quick-link">
                                <i class="ri-wallet-3-line text-danger"></i> Expenses
                            </a>
                            <a href="liabilities.php" class="quick-link">
                                <i class="ri-bank-card-line text-warning"></i> Liabilities
                            </a>
                            <a href="tracker.php" class="quick-link">
                                <i class="ri-line-chart-line text-success"></i> Daily tracker
                            </a>
                            <a href="team.php" class="quick-link">
                                <i class="ri-team-line text-primary"></i> Studio accounts
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
