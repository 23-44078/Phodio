<?php
/*
 * checkLogin() is required: without it this page (and process_liability.php)
 * was reachable by anyone who knew the URL, exposing the studio's debts.
 */
require_once __DIR__ . '/functions.php';

checkLogin();

$today    = date('Y-m-d');
$dueSoon  = date('Y-m-d', strtotime('+30 days'));

$liabilities = $conn->query("SELECT * FROM liabilities ORDER BY due_date ASC");

$totals = $conn->query(
    "SELECT COUNT(*) AS records, COALESCE(SUM(amount), 0) AS total FROM liabilities"
)->fetch_assoc();

$soonStmt = $conn->prepare(
    "SELECT COUNT(*) AS records, COALESCE(SUM(amount), 0) AS total
     FROM liabilities
     WHERE due_date BETWEEN ? AND ?"
);

$soonStmt->bind_param('ss', $today, $dueSoon);
$soonStmt->execute();
$soon = $soonStmt->get_result()->fetch_assoc();

$nextStmt = $conn->prepare(
    "SELECT due_date FROM liabilities WHERE due_date >= ? ORDER BY due_date ASC LIMIT 1"
);

$nextStmt->bind_param('s', $today);
$nextStmt->execute();
$next = $nextStmt->get_result()->fetch_assoc();

/*
 * Feedback from process_liability.php / delete_liability.php.
 */
$flash = null;
$flashStatus = (string) ($_GET['status'] ?? '');
$flashMessage = trim((string) ($_GET['message'] ?? ''));

if ($flashStatus === 'success' || $flashStatus === 'deleted') {
    $flash = [
        'class' => 'alert-success',
        'message' => $flashStatus === 'deleted'
            ? 'Liability removed.'
            : 'Liability saved.',
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
    <title>Liabilities | SOULPRINT</title>

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
                <h1 class="page-title"><i class="ri-bank-card-line me-2"></i>Liabilities</h1>
                <p class="page-sub">What the studio owes, soonest due date first.</p>
            </div>

            <button type="button" class="btn btn-primary px-3" data-bs-toggle="modal" data-bs-target="#liabilityModal">
                <i class="ri-add-circle-line me-1"></i> Add liability
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
                <div class="label">Outstanding</div>
                <div class="value text-warning"><?= admin_money($totals['total'] ?? 0) ?></div>
                <div class="meta"><?= (int) ($totals['records'] ?? 0) ?> open record(s)</div>
            </div>
            <div class="stat-card">
                <div class="label">Due in 30 days</div>
                <div class="value"><?= admin_money($soon['total'] ?? 0) ?></div>
                <div class="meta"><?= (int) ($soon['records'] ?? 0) ?> payment(s) upcoming</div>
            </div>
            <div class="stat-card">
                <div class="label">Next due date</div>
                <div class="value" style="font-size:1.15rem;">
                    <?= $next ? admin_h(admin_date($next['due_date'])) : 'Nothing scheduled' ?>
                </div>
                <div class="meta"><?= admin_h(admin_date($today)) ?> today</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="ri-list-check-2 me-2"></i>Liability ledger</span>
            </div>

            <div class="table-responsive">
                <table class="table table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Due date</th>
                            <th>Creditor</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (($liabilities->num_rows ?? 0) > 0): ?>
                            <?php while ($row = $liabilities->fetch_assoc()): ?>
                                <?php
                                $dueDate = (string) ($row['due_date'] ?? '');
                                $isOverdue = $dueDate !== '' && $dueDate < $today;
                                ?>
                                <tr>
                                    <td>
                                        <?= admin_h(admin_date($dueDate)) ?>
                                        <?php if ($isOverdue): ?>
                                            <span class="chip chip-late ms-2">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-info"><?= admin_h($row['creditor'] ?? '') ?></td>
                                    <td><?= admin_h($row['description'] ?? '') ?></td>
                                    <td class="text-end text-warning fw-bold"><?= admin_money($row['amount'] ?? 0) ?></td>
                                    <td class="text-center">
                                        <form method="POST" action="delete_liability.php" class="d-inline"
                                              onsubmit="return confirm('Mark as settled or delete?');">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-link text-muted p-0 border-0 align-baseline"
                                                    title="Delete liability" aria-label="Delete liability">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="ri-bank-card-line"></i>
                                        <h3>No pending liabilities</h3>
                                        <p>Everything is settled. Add a new entry whenever the studio takes on debt.</p>
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

<div class="modal fade" id="liabilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="process_liability.php" method="POST">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title">New liability entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="liabilityCreditor">Creditor</label>
                    <input type="text" id="liabilityCreditor" name="creditor" class="form-control"
                           placeholder="e.g. Camera store, bank, supplier" maxlength="120" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="liabilityDescription">Description</label>
                    <input type="text" id="liabilityDescription" name="description" class="form-control"
                           placeholder="e.g. Lens installment, studio lights balance" maxlength="180" required>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label" for="liabilityDue">Due date</label>
                        <input type="date" id="liabilityDue" name="due_date" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="liabilityAmount">Amount</label>
                        <input type="number" step="0.01" min="0" id="liabilityAmount" name="amount"
                               class="form-control" placeholder="0.00" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save liability</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
