<?php
/**
 * SOULPRINT — admin credential repair tool.
 *
 * Rebuilds the `admin` account with a known password. Run it once from XAMPP,
 * log in, then delete this file: it is a maintenance tool, not part of the app.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/ui.php';

$newUser = 'soulprint';
$newPass = 'SoulprintMP';

$hashedPass = password_hash($newPass, PASSWORD_DEFAULT);

// Clear the admin table first to avoid duplicate-entry errors.
$conn->query("TRUNCATE TABLE admin");

$stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $newUser, $hashedPass);
$saved = (bool) $stmt->execute();

$spDepth = 1;
$spTitle = 'Credential Repair';
$spDescription = 'Reset the SOULPRINT studio admin credentials.';
require __DIR__ . '/../includes/page_top.php';
?>
<div class="sp-auth">
    <div class="sp-auth__card sp-enter">
        <div class="text-center">
            <div class="sp-auth__logo mb-2"><?= SP_BRAND_MARKUP ?></div>
            <p class="sp-eyebrow justify-content-center mb-3"><?= sp_h(SP_ADMIN_LABEL) ?></p>
            <h1 class="h5 fw-bold mb-1">Credential repair</h1>
            <p class="sp-subtitle mx-auto mb-4">Rebuilds the studio admin account with a known password.</p>
        </div>

        <?php if ($saved): ?>
            <?= sp_flash('success', 'The admin account was rebuilt successfully.') ?>
        <?php else: ?>
            <?= sp_flash('danger', 'The admin account could not be rebuilt: ' . $conn->error) ?>
        <?php endif; ?>

        <dl class="sp-detail-table mb-3">
            <dt class="sp-label mb-1">Username</dt>
            <dd class="mb-3 sp-mono"><?= sp_h($newUser) ?></dd>
            <dt class="sp-label mb-1">Password</dt>
            <dd class="mb-3 sp-mono"><?= sp_h($newPass) ?></dd>
            <dt class="sp-label mb-1">Stored hash</dt>
            <dd class="mb-0 small muted" style="word-break:break-all"><?= sp_h($hashedPass) ?></dd>
        </dl>

        <div class="sp-alert sp-alert--warning mb-3">
            <i class="ri-shield-keyhole-line"></i>
            <div>Delete <code>admin/repair.php</code> once you have signed in. Leaving it in place lets anyone reset the admin password.</div>
        </div>

        <div class="d-grid">
            <a href="login.php" class="btn btn-primary py-2">
                <i class="ri-login-box-line me-2"></i>Go to the admin login
            </a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/page_bottom.php'; ?>
