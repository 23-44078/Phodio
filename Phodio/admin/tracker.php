<?php
require_once __DIR__ . '/functions.php';
checkLogin();

// Fetch tracker data — latest first
$tracker = $conn->query("SELECT *, (target - income_today) as gap FROM daily_tracker ORDER BY track_date DESC");

$trackerRows = [];
$totalIncome = 0.0;
$totalClients = 0;
$goalsMet = 0;
while ($row = $tracker->fetch_assoc()) {
    $trackerRows[] = $row;
    $totalIncome += (float) $row['income_today'];
    $totalClients += (int) $row['client_today'];
    if ((float) $row['gap'] <= 0) {
        $goalsMet++;
    }
}

$spTitle = 'Daily Tracker';
$spSubtitle = 'Log income, clients and target for each studio day, newest first.';
$spPageActions = '<button class="btn btn-primary px-4" type="button" data-bs-toggle="modal" data-bs-target="#trackerModal">'
    . '<i class="ri-add-circle-line me-1"></i>Log today</button>';

require __DIR__ . '/includes/header.php';
?>
<?php if (($_GET['status'] ?? '') === 'saved'): ?>
    <?= sp_flash('success', 'Daily entry saved.', 4200) ?>
<?php elseif (($_GET['status'] ?? '') === 'deleted'): ?>
    <?= sp_flash('success', 'Daily entry deleted.', 4200) ?>
<?php elseif (isset($_GET['error'])): ?>
    <?= sp_flash('danger', (string) $_GET['error']) ?>
<?php endif; ?>

<section class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-4">
        <?= sp_stat_card('Logged income', sp_money($totalIncome), 'ri-money-dollar-circle-line', 'success', count($trackerRows) . ' tracked days', $totalIncome, 2, SP_MONEY_PREFIX) ?>
    </div>
    <div class="col-sm-6 col-lg-4">
        <?= sp_stat_card('Clients served', (string) $totalClients, 'ri-group-line', 'accent', 'Across all tracked days', (float) $totalClients) ?>
    </div>
    <div class="col-sm-6 col-lg-4">
        <?= sp_stat_card('Goals met', (string) $goalsMet, 'ri-trophy-line', 'warning', 'Days at or above target', (float) $goalsMet) ?>
    </div>
</section>

<section class="surface" data-sp-reveal>
    <?= sp_surface_header('ri-line-chart-line', 'Performance tracker') ?>
    <?php if ($trackerRows === []): ?>
        <?= sp_empty_state(
            'ri-bar-chart-grouped-line',
            'No daily entries yet',
            'Log today’s income, clients and target to start tracking studio performance.',
            '<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#trackerModal"><i class="ri-add-circle-line me-2"></i>Log today</button>'
        ) ?>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                <tr>
                    <th>Date</th>
                    <th class="sp-num">Income today</th>
                    <th class="sp-num">Clients</th>
                    <th class="sp-num">Target</th>
                    <th class="sp-num">Gap</th>
                    <th class="sp-center">Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($trackerRows as $row): ?>
                    <?php $met = (float) $row['gap'] <= 0; ?>
                    <tr>
                        <td class="text-nowrap"><?= sp_h(sp_date($row['track_date'])) ?></td>
                        <td class="sp-num text-success"><?= sp_h(sp_money($row['income_today'])) ?></td>
                        <td class="sp-num"><?= (int) $row['client_today'] ?></td>
                        <td class="sp-num text-info"><?= sp_h(sp_money($row['target'])) ?></td>
                        <td class="sp-num">
                            <?php if ($met): ?>
                                <span class="status-chip status-completed">Goal met</span>
                            <?php else: ?>
                                <span class="text-danger fw-bold"><?= sp_h(sp_money($row['gap'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="sp-center">
                            <a href="delete_tracker.php?id=<?= (int) $row['id'] ?>"
                               class="btn-icon btn-icon--danger"
                               data-sp-confirm="Delete this log?"
                               data-sp-confirm-message="<?= sp_h(sp_date($row['track_date'])) ?> will be removed permanently."
                               aria-label="Delete daily entry">
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

<div class="modal fade" id="trackerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" action="process_tracker.php" method="POST">
            <div class="modal-header">
                <div>
                    <div class="sp-eyebrow sp-eyebrow--cool mb-1">New record</div>
                    <h2 class="modal-title fs-5">Log daily performance</h2>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="sp-field">
                    <label class="form-label" for="trackDate">Date</label>
                    <input type="date" name="track_date" id="trackDate" class="form-control"
                           value="<?= sp_h(date('Y-m-d')) ?>" required>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label class="form-label" for="trackIncome">Income today (<?= sp_h(SP_MONEY_PREFIX) ?>)</label>
                        <input type="number" step="0.01" min="0" name="income_today" id="trackIncome"
                               class="form-control" placeholder="0.00" required>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label" for="trackClients">Total clients</label>
                        <input type="number" min="0" name="client_today" id="trackClients"
                               class="form-control" placeholder="0" required>
                    </div>
                </div>
                <div class="sp-field mt-3">
                    <label class="form-label" for="trackTarget">Daily target (<?= sp_h(SP_MONEY_PREFIX) ?>)</label>
                    <input type="number" step="0.01" min="0" name="target" id="trackTarget"
                           class="form-control" value="5000.00" required>
                    <div class="form-hint mt-1">Logging the same date again updates that day’s entry.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save entry</button>
            </div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
