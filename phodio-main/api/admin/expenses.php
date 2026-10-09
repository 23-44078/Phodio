<?php
/*
 * checkLogin() is required: without it this page (and process_expense.php)
 * was reachable by anyone who knew the URL, exposing the studio's spending.
 */
require_once __DIR__ . '/functions.php';

checkLogin();

$monthStart = date('Y-m-01');

$expenses = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC");

$allTime = $conn->query(
    "SELECT COUNT(*) AS records, COALESCE(SUM(amount), 0) AS total FROM expenses"
)->fetch_assoc();

$monthStmt = $conn->prepare(
    "SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE expense_date >= ?"
);

$monthStmt->bind_param('s', $monthStart);
$monthStmt->execute();
$monthTotal = (float) ($monthStmt->get_result()->fetch_assoc()['total'] ?? 0);

$recordCount   = (int) ($allTime['records'] ?? 0);
$allTimeTotal  = (float) ($allTime['total'] ?? 0);
$averageExpense = $recordCount > 0 ? $allTimeTotal / $recordCount : 0.0;

/*
 * Feedback from process_expense.php / delete_expense.php so a failed save is
 * no longer a silent redirect back to an unchanged page.
 */
$flash = null;
$flashStatus = (string) ($_GET['status'] ?? '');
$flashMessage = trim((string) ($_GET['message'] ?? ''));

if ($flashStatus === 'success' || $flashStatus === 'deleted') {
    $flash = [
        'class' => 'alert-success',
        'message' => $flashStatus === 'deleted'
            ? 'Expense record deleted.'
            : 'Expense saved.',
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
    <title>Expenses | SOULPRINT</title>

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
                <h1 class="page-title"><i class="ri-wallet-3-line me-2"></i>Expenses</h1>
                <p class="page-sub">Everything the studio spends, newest first.</p>
            </div>

            <button type="button" class="btn btn-primary px-3" data-bs-toggle="modal" data-bs-target="#expenseModal">
                <i class="ri-add-circle-line me-1"></i> Add expense
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
                <div class="label">This month</div>
                <div class="value text-danger"><?= admin_money($monthTotal) ?></div>
                <div class="meta">Since <?= admin_h(date('M d', strtotime($monthStart))) ?></div>
            </div>
            <div class="stat-card">
                <div class="label">All time</div>
                <div class="value"><?= admin_money($allTimeTotal) ?></div>
                <div class="meta"><?= $recordCount ?> record<?= $recordCount === 1 ? '' : 's' ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Average</div>
                <div class="value"><?= admin_money($averageExpense) ?></div>
                <div class="meta">Per recorded expense</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="ri-list-check-2 me-2"></i>Expense history</span>
            </div>

            <div class="table-responsive">
                <table class="table table-finance mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (($expenses->num_rows ?? 0) > 0): ?>
                            <?php while ($row = $expenses->fetch_assoc()): ?>
                                <tr>
                                    <td><?= admin_h(admin_date($row['expense_date'] ?? '')) ?></td>
                                    <td><?= admin_h($row['description'] ?? '') ?></td>
                                    <td class="text-end text-danger fw-bold"><?= admin_money($row['amount'] ?? 0) ?></td>
                                    <td class="text-center">
                                        <form method="POST" action="delete_expense.php" class="d-inline"
                                              onsubmit="return confirm('Delete this record?');">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-link text-muted p-0 border-0 align-baseline"
                                                    title="Delete expense" aria-label="Delete expense">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="ri-wallet-3-line"></i>
                                        <h3>No expenses recorded</h3>
                                        <p>Add the first one with the button above and it will show up here.</p>
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

<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="process_expense.php" method="POST">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title">Record new expense</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="expenseDescription">Description</label>
                    <input type="text" id="expenseDescription" name="description" class="form-control"
                           placeholder="e.g. Electricity bill, studio rent" maxlength="180" required>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="expenseDate">Date</label>
                    <input type="date" id="expenseDate" name="expense_date" class="form-control"
                           value="<?= admin_h(date('Y-m-d')) ?>" required>
                </div>

                <div class="mb-1">
                    <label class="form-label" for="expenseAmount">Amount</label>
                    <input type="number" step="0.01" min="0" id="expenseAmount" name="amount"
                           class="form-control" placeholder="0.00" required>
                </div>
            </div>

            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save expense</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
