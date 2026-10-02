<?php
/**
 * SOULPRINT — admin sidebar navigation.
 * Rendered by admin/includes/header.php on every admin screen.
 */
require_once __DIR__ . '/../includes/ui.php';

$adminUser = (string) ($_SESSION['admin'] ?? '');
$currentFile = basename($_SERVER['PHP_SELF'] ?? '');

$adminNav = [
    'dashboard.php' => ['ri-dashboard-3-line', 'Dashboard'],
    'bookings.php' => ['ri-calendar-event-line', 'Bookings'],
    'expenses.php' => ['ri-wallet-3-line', 'Expenses'],
    'liabilities.php' => ['ri-bank-card-line', 'Liabilities'],
    'tracker.php' => ['ri-line-chart-line', 'Daily Tracker'],
];
?>
<aside class="sp-sidebar" id="mainSidebar" aria-label="Admin navigation">
    <div class="sp-sidebar__head">
        <a href="dashboard.php" class="text-decoration-none"><?= sp_brand() ?></a>
        <button class="btn-icon d-lg-none" type="button" data-sp-sidebar-toggle aria-label="Close navigation">
            <i class="ri-close-line ri-lg"></i>
        </button>
    </div>

    <nav class="sp-sidebar__nav">
        <p class="sp-sidebar__label">Studio</p>
        <?php foreach ($adminNav as $href => [$icon, $label]): ?>
            <a href="<?= sp_h($href) ?>"
               class="sp-nav-link <?= $currentFile === $href ? 'is-active' : '' ?>"
               <?= $currentFile === $href ? 'aria-current="page"' : '' ?>>
                <i class="<?= sp_h($icon) ?>"></i>
                <span><?= sp_h($label) ?></span>
            </a>
        <?php endforeach; ?>

        <div class="sp-sidebar__foot">
            <p class="sp-sidebar__label">Session</p>
            <div class="sp-sidebar__user">
                <span class="sp-avatar" aria-hidden="true"><?= sp_h(strtoupper(substr($adminUser !== '' ? $adminUser : 'A', 0, 1))) ?></span>
                <span class="sp-sidebar__user-name" title="<?= sp_h($adminUser) ?>"><?= sp_h($adminUser !== '' ? $adminUser : 'Administrator') ?></span>
            </div>
            <a href="#" data-bs-toggle="modal" data-bs-target="#logoutModal" class="sp-nav-link sp-nav-link--danger">
                <i class="ri-logout-box-r-line"></i>
                <span>Log out</span>
            </a>
        </div>
    </nav>
</aside>

<div class="sp-sidebar-overlay" id="sidebarOverlay"></div>

<div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body p-4 text-center">
                <div class="sp-empty__icon mb-3" style="width:56px;height:56px;font-size:1.5rem">
                    <i class="ri-logout-circle-line"></i>
                </div>
                <h2 class="h5 fw-bold mb-2">End this session?</h2>
                <p class="sp-subtitle mx-auto mb-4">You will be signed out of the <?= sp_h(SP_BRAND) ?> studio console.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-light px-3" data-bs-dismiss="modal">Cancel</button>
                    <a href="logout.php" class="btn btn-danger px-3">Log out</a>
                </div>
            </div>
        </div>
    </div>
</div>
