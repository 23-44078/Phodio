<?php
require_once __DIR__ . '/functions.php';
checkLogin();

$expenses = $conn->query("SELECT * FROM expenses ORDER BY expense_date DESC");

$totalExpense = 0.0;
$expenseRows = [];
while ($row = $expenses->fetch_assoc()) {
    $expenseRows[] = $row;
    $totalExpense += (float) $row['amount'];
}

$spTitle = 'Expenses';
$spSubtitle = 'Record every studio cost so income, expenses and net profit always agree.';
$spPageActions = '<button class="btn btn-primary px-4" type="button" data-bs-toggle="modal" data-bs-target="#expenseModal">'
    . '<i class="ri-add-circle-line me-1"></i>Add expense</button>';

require __DIR__ . '/includes/header.php';
?>
<?php if (($_GET['status'] ?? '') === 'saved'): ?>
    <?= sp_flash('success', 'Expense saved.', 4200) ?>
<?php elseif (($_GET['status'] ?? '') === 'deleted'): ?>
    <?= sp_flash('success', 'Expense deleted.', 4200) ?>
<?php elseif (isset($_GET['error'])): ?>
    <?= sp_flash('danger', (string) $_GET['error']) ?>
<?php endif; ?>

<section class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <?= sp_stat_card('Total expenses', sp_money($totalExpense), 'ri-wallet-3-line', 'brand', count($expenseRows) . ' recorded entries', $totalExpense, 2, SP_MONEY_PREFIX) ?>
    </div>
</section>

<section class="surface" data-sp-reveal>
    <?= sp_surface_header('ri-wallet-3-line', 'Expense management') ?>
    <?php if ($expenseRows === []): ?>
        <?= sp_empty_state(
            'ri-receipt-line',
            'No expenses recorded',
            'Add your first expense to start tracking studio costs against income.',
            '<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="ri-add-circle-line me-2"></i>Add expense</button>'
        ) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th class="sp-num">Amount</th>
                    <th class="sp-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($expenseRows as $row): ?>
                    <tr>
                        <td class="text-nowrap"><?= sp_h(sp_date($row['expense_date'])) ?></td>
                        <td class="text-white"><?= sp_h($row['description']) ?></td>
                        <td class="sp-num text-danger"><?= sp_h(sp_money($row['amount'])) ?></td>
                        <td class="sp-center">
                            <a href="delete_expense.php?id=<?= (int) $row['id'] ?>"
                               class="btn-icon btn-icon--danger"
                               data-sp-confirm="Delete this expense?"
                               data-sp-confirm-message="<?= sp_h($row['description']) ?> will be removed permanently."
                               aria-label="Delete expense">
                                <i class="ri-delete-bin-line"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<div class="modal fade" id="expenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="process_expense.php" method="POST">
            <div class="modal-header">
                <div>
                    <div class="sp-eyebrow sp-eyebrow--cool mb-1">New record</div>
                    <h2 class="modal-title fs-5">Record new expense</h2>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="sp-field">
                    <label class="form-label" for="expenseDescription">Description</label>
                    <input type="text" name="description" id="expenseDescription" class="form-control"
                           placeholder="e.g. Electricity bill, studio rent" required>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="expenseDate">Date</label>
                        <input type="date" name="expense_date" id="expenseDate" class="form-control"
                               value="<?= sp_h(date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="expenseAmount">Amount (<?= sp_h(SP_MONEY_PREFIX) ?>)</label>
                        <input type="number" step="0.01" min="0" name="amount" id="expenseAmount"
                               class="form-control" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save expense</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
