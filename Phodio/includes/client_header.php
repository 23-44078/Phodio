<?php
/**
 * SOULPRINT — client portal navigation bar.
 * Uses the shared design tokens so it matches the admin console.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/ui.php';

$clientName = trim((string) ($_SESSION['client_name'] ?? 'Client'));
$profileUrl = null;
if (isset($_SESSION['client_id'])) {
    $clientId = (int) $_SESSION['client_id'];
    $stmt = $conn->prepare('SELECT firstname, lastname, profile_image FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $clientId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    if ($profile) {
        $clientName = trim(($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? '')) ?: 'Client';
        $profileFile = basename((string) ($profile['profile_image'] ?? ''));
        $profilePath = __DIR__ . '/../uploads/profile/' . $profileFile;
        if ($profileFile !== '' && is_file($profilePath)) {
            $profileUrl = 'uploads/profile/' . rawurlencode($profileFile);
        }
    }
}
$initial = strtoupper(substr($clientName, 0, 1));
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$clientNavItems = [
    'client_dashboard.php' => ['ri-dashboard-3-line', 'Dashboard'],
    'client_booking.php' => ['ri-calendar-check-line', 'Bookings &amp; Progress'],
];
?>
<nav class="sp-navbar navbar navbar-expand-lg px-3 px-lg-4" aria-label="Client portal">
    <div class="container-fluid">
        <a class="navbar-brand" href="client_dashboard.php"><?= sp_brand() ?></a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#clientNav"
                aria-controls="clientNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="clientNav">
            <?php if (isset($_SESSION['client_id'])): ?>
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1 pt-3 pt-lg-0">
                    <?php foreach ($clientNavItems as $href => [$icon, $label]): ?>
                        <a class="nav-link sp-underline <?= $currentPage === $href ? 'active is-active' : '' ?>"
                           href="<?= sp_h($href) ?>">
                            <i class="<?= sp_h($icon) ?> me-1"></i><?= $label ?>
                        </a>
                    <?php endforeach; ?>

                    <div class="nav-item dropdown ms-lg-3 mt-2 mt-lg-0">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2 sp-account" href="#" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($profileUrl): ?>
                                <img src="<?= sp_h($profileUrl) ?>" class="sp-avatar" width="34" height="34" alt="">
                            <?php else: ?>
                                <span class="sp-avatar" aria-hidden="true"><?= sp_h($initial) ?></span>
                            <?php endif; ?>
                            <span class="sp-account__name"><?= sp_h($clientName) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><span class="dropdown-item-text small"><?= sp_h(SP_PORTAL_LABEL) ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="logout.php">
                                    <i class="ri-logout-box-r-line me-2"></i>Log out
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
