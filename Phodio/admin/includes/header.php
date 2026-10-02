<?php
/**
 * SOULPRINT — admin console chrome (opens the page).
 *
 * Every admin screen starts with this file, which guarantees the same head,
 * sidebar and layout on all of them. Set before including:
 *
 *   $spTitle        — page name, e.g. 'Expenses'
 *   $spSubtitle     — optional one-line description under the title
 *   $spPageActions  — optional HTML for the right-hand side of the page head
 *   $spFullCalendar — true on screens that render the calendar
 *   $spExtraHead    — optional extra <head> markup
 */

require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../../includes/ui.php';

checkLogin();

$spDepth = 1;
$spTitle = isset($spTitle) && $spTitle !== '' ? (string) $spTitle : SP_ADMIN_LABEL;
$spDescription = $spDescription ?? 'SOULPRINT studio management console.';
$spExtraHead = ($spExtraHead ?? '')
    . '\n    <link rel="stylesheet" href="assets/css/style.css">';

require __DIR__ . '/../../includes/page_top.php';
require __DIR__ . '/../sidebar.php';

$adminCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
$adminPageTitles = [
    'dashboard.php' => ['ri-dashboard-3-line', 'Dashboard'],
    'bookings.php' => ['ri-calendar-event-line', 'Bookings'],
    'expenses.php' => ['ri-wallet-3-line', 'Expenses'],
    'liabilities.php' => ['ri-bank-card-line', 'Liabilities'],
    'tracker.php' => ['ri-line-chart-line', 'Daily Tracker'],
];
[$adminPageIcon, $adminPageName] = $adminPageTitles[$adminCurrentPage] ?? ['ri-apps-line', SP_BRAND];
?>
<div class="sp-topbar d-lg-none">
    <button class="btn-icon" type="button" data-sp-sidebar-toggle aria-label="Open navigation">
        <i class="ri-menu-3-line ri-lg"></i>
    </button>
    <?= sp_brand('sp-brand sp-brand--sm') ?>
    <span class="sp-topbar__spacer"></span>
</div>

<main class="sp-admin">
    <div class="sp-admin__inner">
        <header class="sp-page-head sp-enter">
            <div>
                <div class="sp-eyebrow sp-eyebrow--cool mb-2">
                    <i class="<?= sp_h($adminPageIcon) ?>"></i><?= sp_h(SP_ADMIN_LABEL) ?>
                </div>
                <h1 class="sp-title"><?= sp_h($adminPageName) ?></h1>
                <?php if (!empty($spSubtitle)): ?>
                    <p class="sp-subtitle"><?= sp_h($spSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <?php if (!empty($spPageActions)) { echo $spPageActions; } ?>
        </header>
