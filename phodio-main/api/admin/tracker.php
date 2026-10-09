<?php
/*
 * checkLogin() is required: without it this page (and process_tracker.php)
 * was reachable by anyone who knew the URL, exposing the studio's earnings.
 */
require_once __DIR__ . '/functions.php';

checkLogin();

$monthStart = date('Y-m-01');

$tracker = $conn->query(
    "SELECT *, (target - income_today) AS gap FROM daily_tracker ORDER BY track_date DESC"
);

$totals = $conn->query(
    "SELECT
        COUNT(*) AS entries,
        COALESCE(SUM(income_today), 0) AS income,
        COALESCE(SUM(client_today), 0) AS clients,
        COALESCE(SUM(CASE WHEN income_today >= target THEN 1 ELSE 0 END), 0) AS goals_met
     FROM daily_tracker"
)->fetch_assoc();

$entries  = (int) ($totals['entries'] ?? 0);
$income   = (float) ($totals['income'] ?? 0);
$clients  = (int) ($totals['clients'] ?? 0);
$goalsMet = (int) ($totals['goals_met'] ?? 0);

$goalRate = $entries > 0 ? round(($goalsMet / $entries) * 100) : 0;
$average  = $entries > 0 ? $income / $entries : 0.0;

/*
 * Feedback from process_tracker.php / delete_tracker.php.
 */
$flash = null;
$flashStatus = (string) ($_GET['status'] ?? '');
$flashMessage = trim((string) ($_GET['message'] ?? ''));

if ($flashStatus === 'success' || $flashStatus === 'deleted') {
    $flash = [
        'class' => 'alert-success',
        'message' => $flashStatus === 'deleted'
            ? 'Tracker entry deleted.'
            : 'Entry saved.',
    ];
} elseif ($flashStatus === 'error') {
    $flash = [
        'class' => 'alert-danger',
        'message' => $flashMessage !== ''
            ? $flashMessage
            : 'Something went wrong. Please try again.',
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Tracker | SOULPRINT</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="container-fluid py-4">

        <div class="page-head">
            <div>
                <h1 class="page-title"><i class="ri-line-chart-line me-2"></i>Daily tracker</h1>
                <p class="page-sub">Income, clients and targets, most recent day first.</p>
            </div>

            <button type="button" class="btn btn-primary px-3" data-bs-toggle="modal" data-bs-target="#trackerModal">
                <i class="ri-add-circle-line me-1"></i> Log a day
            </button>
        </div>

        <?php if ($flash !== null): ?>
            <div class="alert <?= $flash['class'] ?> alert-dismissible fade show" role="alert">
                <?= admin_h($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="label">Logged income</div>
                <div class="value text-success"><?= admin_money($income) ?></div>
                <div class="meta">Across <?= $entries ?> day(s)</div>
            </div>
            <div class="stat-card">
                <div class="label">Clients served</div>
                <div class="value text-info"><?= $clients ?></div>
                <div class="meta"><?= admin_money($average) ?> average day</div>
            </div>
            <div class="stat-card">
                <div class="label">Goals met</div>
                <div class="value"><?= $goalRate ?>%</div>
                <div class="meta"><?= $goalsMet ?> of <?= $entries ?> day(s) hit target</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="ri-list-check-2 me-2"></i>Daily log</span>
            </div>

            <div class="table-responsive">
                <table class="table table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-end">Income</th>
                            <th class="text-end">Clients</th>
                            <th class="text-end">Target</th>
                            <th class="text-end">Gap</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (($tracker->num_rows ?? 0) > 0): ?>
                            <?php while ($row = $tracker->fetch_assoc()): ?>
                                <?php $gap = (float) ($row['gap'] ?? 0); ?>
                                <tr>
                                    <td><?= admin_h(admin_date($row['track_date'] ?? '')) ?></td>
                                    <td class="text-end text-success fw-bold"><?= admin_money($row['income_today'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int) ($row['client_today'] ?? 0) ?></td>
                                    <td class="text-end text-info"><?= admin_money($row['target'] ?? 0) ?></td>
                                    <td class="text-end">
                                        <?php if ($gap <= 0): ?>
                                            <span class="chip chip-ok"><i class="ri-check-line"></i>Goal met</span>
                                        <?php else: ?>
                                            <span class="text-danger fw-bold"><?= admin_money($gap) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="delete_tracker.php" class="d-inline"
                                              onsubmit="return confirm('Delete this log?');">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-link text-muted p-0 border-0 align-baseline"
                                                    title="Delete entry" aria-label="Delete entry">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="ri-line-chart-line"></i>
                                        <h3>No days logged yet</h3>
                                        <p>Log today to start comparing income against your daily target.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</main>

<div class="modal fade" id="trackerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="process_tracker.php" method="POST">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title">Log daily performance</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="trackDate">Date</label>
                    <input type="date" id="trackDate" name="track_date" class="form-control"
                           value="<?= admin_h(date('Y-m-d')) ?>" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="trackIncome">Income today</label>
                        <input type="number" step="0.01" min="0" id="trackIncome" name="income_today"
                               class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="trackClients">Clients</label>
                        <input type="number" step="1" min="0" id="trackClients" name="client_today"
                               class="form-control" placeholder="0" required>
                    </div>
                </div>

                <div class="mb-1">
                    <label class="form-label" for="trackTarget">Daily target</label>
                    <input type="number" step="0.01" min="0" id="trackTarget" name="target"
                           class="form-control" value="5000.00" required>
                </div>

                <p class="text-muted small mt-3 mb-0">
                    Logging the same date twice updates that day instead of adding a second row.
                </p>
            </div>

            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save entry</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
