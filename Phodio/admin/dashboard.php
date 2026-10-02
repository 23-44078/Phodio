<?php
require_once __DIR__ . '/functions.php';
checkLogin(); // This stops unauthorized admin immediately

$range = isset($_GET['range']) ? $_GET['range'] : 'month';
$range = in_array($range, ['today', 'month'], true) ? $range : 'month';
$data = getDashboardData($conn, $range);
$stats = $data['stats'];
$rental_breakdown = $data['rentals'];
$liability_breakdown = $data['liabilities_list'];

// Fetch the very latest Daily Tracker entry for the highlight card
$today_track = $conn->query("SELECT * FROM daily_tracker ORDER BY track_date DESC LIMIT 1")->fetch_assoc();
$gap = ($today_track) ? ((float) $today_track['target'] - (float) $today_track['income_today']) : 0.0;

$incomeToday = (float) ($today_track['income_today'] ?? 0);
$dailyTarget = (float) ($today_track['target'] ?? 0);
$goalPercent = $dailyTarget > 0 ? min(100, ($incomeToday / $dailyTarget) * 100) : 0;
$marginPercent = $stats['income'] > 0 ? ($stats['profit'] / $stats['income']) * 100 : 0;

$rentalRows = [];
while ($rb = $rental_breakdown->fetch_assoc()) {
    $rentalRows[] = $rb;
}
$liabilityRows = [];
while ($lb = $liability_breakdown->fetch_assoc()) {
    $liabilityRows[] = $lb;
}

$spTitle = 'Dashboard';
$spSubtitle = 'Viewing data for: ' . ($range === 'today' ? 'Today' : 'This month');
$spPageActions = '<nav class="sp-filter" aria-label="Reporting range">'
    . '<a href="dashboard.php?range=today" class="' . ($range === 'today' ? 'is-active' : '') . '">Today</a>'
    . '<a href="dashboard.php?range=month" class="' . ($range === 'month' ? 'is-active' : '') . '">Monthly</a>'
    . '</nav>';

require __DIR__ . '/includes/header.php';
?>
<section class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <?= sp_stat_card('Income', sp_money($stats['income']), 'ri-money-dollar-circle-line', 'success', 'Booked revenue', $stats['income'], 2, SP_MONEY_PREFIX) ?>
    </div>
    <div class="col-6 col-lg-3">
        <?= sp_stat_card('Expenses', sp_money($stats['expense']), 'ri-wallet-3-line', 'brand', 'Costs recorded', $stats['expense'], 2, SP_MONEY_PREFIX) ?>
    </div>
    <div class="col-6 col-lg-3">
        <?= sp_stat_card('Liabilities', sp_money($stats['liabilities']), 'ri-bank-card-line', 'warning', 'Outstanding balances', $stats['liabilities'], 2, SP_MONEY_PREFIX) ?>
    </div>
    <div class="col-6 col-lg-3">
        <?= sp_stat_card(
            'Net profit',
            sp_money_signed($stats['profit']),
            $stats['profit'] < 0 ? 'ri-arrow-down-line' : 'ri-arrow-up-line',
            $stats['profit'] < 0 ? 'brand' : 'accent',
            number_format($marginPercent, 1) . '% margin on income',
            abs((float) $stats['profit']),
            2,
            $stats['profit'] < 0 ? SP_MONEY_PREFIX . '' : SP_MONEY_PREFIX
        ) ?>
    </div>
</section>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="surface mb-4 sp-enter sp-enter-2" data-sp-reveal>
            <?= sp_surface_header(
                'ri-flashlight-line',
                'Daily goal progress',
                '<span class="badge soft-badge">' . sp_h(sp_date($today_track['track_date'] ?? null)) . '</span>'
            ) ?>
            <div class="surface-body">
                <div class="sp-meter mb-3" data-sp-meter="<?= sp_h((string) round($goalPercent, 1)) ?>"
                     role="progressbar" aria-label="Daily goal progress"
                     aria-valuenow="<?= (int) round($goalPercent) ?>" aria-valuemin="0" aria-valuemax="100">
                    <span></span>
                </div>
                <div class="row text-center g-3">
                    <div class="col-4">
                        <div class="sp-stat__label">Income today</div>
                        <div class="h4 fw-bold mt-1 mb-0" data-sp-count="<?= sp_h((string) $incomeToday) ?>"
                             data-sp-decimals="2" data-sp-prefix="<?= sp_h(SP_MONEY_PREFIX) ?>">
                            <?= sp_h(sp_money($incomeToday)) ?>
                        </div>
                    </div>
                    <div class="col-4 border-start border-secondary">
                        <div class="sp-stat__label">Remaining gap</div>
                        <div class="h4 fw-bold mt-1 mb-0 <?= $gap > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= $gap <= 0 && $today_track ? 'Cleared' : sp_h(sp_money($gap)) ?>
                        </div>
                    </div>
                    <div class="col-4 border-start border-secondary">
                        <div class="sp-stat__label">Total clients</div>
                        <div class="h4 fw-bold mt-1 mb-0" data-sp-count="<?= sp_h((string) (int) ($today_track['client_today'] ?? 0)) ?>">
                            <?= (int) ($today_track['client_today'] ?? 0) ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="row g-4">
            <div class="col-lg-6 mb-4">
                <section class="surface h-100" data-sp-reveal="left">
                    <?= sp_surface_header('ri-pie-chart-2-line', 'Revenue by package') ?>
                    <?php if ($rentalRows === []): ?>
                        <?= sp_empty_state('ri-inbox-line', 'No revenue recorded', 'Booked sessions in this period will be grouped here by package.') ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Package</th><th class="sp-num">Revenue</th></tr></thead>
                                <tbody>
                                <?php foreach ($rentalRows as $rb): ?>
                                    <tr>
                                        <td><?= sp_h($rb['package_type']) ?></td>
                                        <td class="sp-num sp-price"><?= sp_h(sp_money($rb['amt'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <div class="col-lg-6 mb-4">
                <section class="surface h-100" data-sp-reveal="right">
                    <?= sp_surface_header('ri-user-received-2-line', 'Creditor breakdown') ?>
                    <?php if ($liabilityRows === []): ?>
                        <?= sp_empty_state('ri-shield-check-line', 'No liabilities recorded', 'Amounts owed to creditors in this period will appear here.') ?>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead><tr><th>Creditor</th><th class="sp-num">Amount</th></tr></thead>
                                <tbody>
                                <?php foreach ($liabilityRows as $lb): ?>
                                    <tr>
                                        <td><?= sp_h($lb['creditor']) ?></td>
                                        <td class="sp-num text-warning fw-bold"><?= sp_h(sp_money($lb['amt'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <section class="surface mb-4" data-sp-reveal="right">
            <?= sp_surface_header('ri-rocket-2-line', 'Quick access') ?>
            <div class="surface-body d-grid gap-2">
                <a href="bookings.php" class="btn btn-outline-light text-start sp-quick-link">
                    <i class="ri-calendar-event-line me-2"></i>Manage bookings &amp; progress
                </a>
                <a href="expenses.php" class="btn btn-outline-light text-start sp-quick-link">
                    <i class="ri-wallet-3-line me-2"></i>Record an expense
                </a>
                <a href="liabilities.php" class="btn btn-outline-light text-start sp-quick-link">
                    <i class="ri-bank-card-line me-2"></i>Track liabilities
                </a>
                <a href="tracker.php" class="btn btn-outline-light text-start sp-quick-link">
                    <i class="ri-line-chart-line me-2"></i>Log daily performance
                </a>
            </div>
        </section>

        <section class="surface" data-sp-reveal="right">
            <?= sp_surface_header('ri-guide-line', 'How the numbers work') ?>
            <div class="surface-body">
                <ul class="list-unstyled small muted mb-0 d-grid gap-2">
                    <li><i class="ri-arrow-right-s-line text-danger me-1"></i>Income counts confirmed bookings, excluding cancelled sessions.</li>
                    <li><i class="ri-arrow-right-s-line text-danger me-1"></i>Net profit is income minus expenses; liabilities are shown separately.</li>
                    <li><i class="ri-arrow-right-s-line text-danger me-1"></i>Switch the range above to compare today with the full month.</li>
                </ul>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
