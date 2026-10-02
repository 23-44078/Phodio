<?php
require_once __DIR__ . '/functions.php';
checkLogin();

// Fetching liabilities — grouped by due date
$liabilities = $conn->query("SELECT * FROM liabilities ORDER BY due_date ASC");

$liabilityRows = [];
$totalLiabilities = 0.0;
$today = date('Y-m-d');
$overdueCount = 0;
while ($row = $liabilities->fetch_assoc()) {
    $liabilityRows[] = $row;
    $totalLiabilities += (float) $row['amount'];
    if ((string) $row['due_date'] < $today) {
        $overdueCount++;
    }
}

$spTitle = 'Liabilities';
$spSubtitle = 'Keep every amount the studio owes visible, with the earliest due dates first.';
$spPageActions = '<button class="btn btn-primary px-4" type="button" data-bs-toggle="modal" data-bs-target="#liabilityModal">'
    . '<i class="ri-add-circle-line me-1"></i>Add liability</button>';

require __DIR__ . '/includes/header.php';
?>
<?php if (($_GET['status'] ?? '') === 'saved'): ?>
    <?= sp_flash('success', 'Liability saved.', 4200) ?>
<?php elseif (($_GET['status'] ?? '') === 'deleted'): ?>
    <?= sp_flash('success', 'Liability deleted.', 4200) ?>
<?php elseif (isset($_GET['error'])): ?>
    <?= sp_flash('danger', (string) $_GET['error']) ?>
<?php endif; ?>

<section class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <?= sp_stat_card('Outstanding balance', sp_money($totalLiabilities), 'ri-bank-card-line', 'warning', count($liabilityRows) . ' open entries', $totalLiabilities, 2, SP_MONEY_PREFIX) ?>
    </div>
    <div class="col-sm-6 col-lg-4">
        <?= sp_stat_card('Past due', (string) $overdueCount, 'ri-alarm-warning-line', $overdueCount > 0 ? 'brand' : 'success', 'Entries with a due date before today', (float) $overdueCount) ?>
    </div>
</section>

<section class="surface" data-sp-reveal>
    <?= sp_surface_header('ri-bank-card-line', 'Liabilities & debts') ?>
    <?php if ($liabilityRows === []): ?>
        <?= sp_empty_state(
            'ri-shield-check-line',
            'No pending liabilities',
            'Add a liability to track instalments and balances you still owe.',
            '<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#liabilityModal"><i class="ri-add-circle-line me-2"></i>Add liability</button>'
        ) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Due date</th>
                    <th>Creditor</th>
                    <th>Description</th>
                    <th class="sp-num">Amount</th>
                    <th class="sp-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($liabilityRows as $row): ?>
                    <?php $isOverdue = (string) $row['due_date'] < $today; ?>
                    <tr>
                        <td class="text-nowrap">
                            <?= sp_h(sp_date($row['due_date'])) ?>
                            <?php if ($isOverdue): ?>
                                <span class="status-chip status-cancelled ms-1">Past due</span>
                            <?php endif; ?>
                        </td>
                        <td class="fw-bold text-info"><?= sp_h($row['creditor']) ?></td>
                        <td><?= sp_h($row['description']) ?></td>
                        <td class="sp-num text-warning"><?= sp_h(sp_money($row['amount'])) ?></td>
                        <td class="sp-center">
                            <a href="delete_liability.php?id=<?= (int) $row['id'] ?>"
                               class="btn-icon btn-icon--danger"
                               data-sp-confirm="Delete this liability?"
                               data-sp-confirm-message="<?= sp_h($row['creditor']) ?> will be removed permanently."
                               aria-label="Delete liability">
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

<div class="modal fade" id="liabilityModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="process_liability.php" method="POST">
            <div class="modal-header">
                <div>
                    <div class="sp-eyebrow sp-eyebrow--cool mb-1">New record</div>
                    <h2 class="modal-title fs-5">New liability entry</h2>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="sp-field">
                    <label class="form-label" for="liabilityCreditor">Creditor <span class="sp-faint fw-normal text-lowercase">(who do you owe?)</span></label>
                    <input type="text" name="creditor" id="liabilityCreditor" class="form-control"
                           placeholder="e.g. Camera store, bank, supplier" required>
                </div>
                <div class="sp-field">
                    <label class="form-label" for="liabilityDescription">Description</label>
                    <input type="text" name="description" id="liabilityDescription" class="form-control"
                           placeholder="e.g. Lens instalment, balance for studio lights" required>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="liabilityDueDate">Due date</label>
                        <input type="date" name="due_date" id="liabilityDueDate" class="form-control" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="liabilityAmount">Amount (<?= sp_h(SP_MONEY_PREFIX) ?>)</label>
                        <input type="number" step="0.01" min="0" name="amount" id="liabilityAmount"
                               class="form-control" placeholder="0.00" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save liability</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
