<?php

/**
 * Studio accounts.
 *
 * Every signed-in studio account can open this page: there is no super admin
 * any more. An account can be created, disabled, given a new temporary
 * password, or removed — but never the one you are signed in as.
 */

require_once __DIR__ . '/functions.php';

checkLogin();

$pdo = $conn->pdo();

$accountsSupported = phodio_admin_roles_supported($pdo);
$currentAdmin = phodio_current_admin() ?? ['id' => 0, 'username' => '', 'name' => ''];

if ($accountsSupported) {
    $result = $conn->query(
        'SELECT
            id,
            username,
            full_name,
            (COALESCE(is_active, TRUE))::int AS is_active,
            last_login_at
         FROM admin
         ORDER BY id'
    );
} else {
    $result = $conn->query(
        'SELECT
            id,
            username,
            NULL AS full_name,
            1 AS is_active,
            NULL AS last_login_at
         FROM admin
         ORDER BY id'
    );
}

$accounts = $result ? $result->fetch_all() : [];

$activeCount = 0;

foreach ($accounts as $account) {
    if ((int) ($account['is_active'] ?? 0) === 1) {
        $activeCount++;
    }
}

$disabledCount = count($accounts) - $activeCount;

$statusMessage = '';
$errorMessage = '';

if (isset($_GET['status'])) {
    $map = [
        'created' => 'Studio account created.',
        'enabled' => 'Studio account re-enabled.',
        'disabled' => 'Studio account disabled.',
        'password' => 'Password reset.',
        'deleted' => 'Studio account removed.',
    ];

    $statusMessage = $map[$_GET['status']] ?? '';
}

if (isset($_GET['error'])) {
    $errorMessage = (string) $_GET['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studio accounts | SOULPRINT</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="container-fluid py-4">

        <div class="page-head">
            <div>
                <h1 class="page-title"><i class="ri-team-line me-2"></i>Studio accounts</h1>
                <p class="page-sub">Who can open the management panel, and what state each account is in.</p>
            </div>
            <span class="chip chip-ok"><i class="ri-shield-check-line"></i>All accounts have full access</span>
        </div>

        <?php if (!$accountsSupported): ?>
            <div class="alert alert-warning border-0" style="background:rgba(245,158,11,.14);color:#fcd34d" role="alert">
                <i class="ri-error-warning-line me-2"></i>
                <strong>Account controls are not active yet.</strong>
                Run <code class="migration">api/db/migrations/20261009_01_admin_roles.sql</code>
                in the Supabase SQL Editor. Until then existing accounts work normally,
                but new ones cannot be created and accounts cannot be disabled.
            </div>
        <?php endif; ?>

        <?php if ($statusMessage !== ''): ?>
            <div class="alert alert-success" role="status"><?= admin_h($statusMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= admin_h($errorMessage) ?></div>
        <?php endif; ?>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="label">Accounts</div>
                <div class="value"><?= count($accounts) ?></div>
                <div class="meta">Able to open the panel</div>
            </div>
            <div class="stat-card">
                <div class="label">Active</div>
                <div class="value text-success"><?= $activeCount ?></div>
                <div class="meta">Signed in as <?= admin_h($currentAdmin['username'] ?? '') ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Disabled</div>
                <div class="value text-danger"><?= $disabledCount ?></div>
                <div class="meta">Cannot sign in until re-enabled</div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="ri-team-line me-2"></i>Accounts</span>
                        <span class="small text-muted"><?= count($accounts) ?> total</span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-finance mb-0">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Status</th>
                                    <th>Last sign-in</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($accounts) > 0): ?>
                                    <?php foreach ($accounts as $account): ?>
                                        <?php
                                        $isSelf = (int) $account['id'] === (int) $currentAdmin['id'];
                                        $active = (int) ($account['is_active'] ?? 0) === 1;
                                        $lastLogin = trim((string) ($account['last_login_at'] ?? ''));
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold">
                                                    <?= admin_h($account['username']) ?>
                                                    <?php if ($isSelf): ?>
                                                        <span class="badge bg-secondary ms-1">you</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?= admin_h($account['full_name'] ?? '') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="chip <?= $active ? 'chip-ok' : 'chip-late' ?>">
                                                    <?= $active ? 'Active' : 'Disabled' ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted">
                                                <?= $lastLogin !== '' ? admin_h($lastLogin) : '—' ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-flex flex-wrap gap-2 justify-content-end">
                                                    <form method="post" action="process_team.php">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                                        <button class="btn btn-sm btn-soft" type="submit"
                                                                <?= ($isSelf || !$accountsSupported) ? 'disabled' : '' ?>>
                                                            <i class="ri-<?= $active ? 'close' : 'check' ?>-line"></i>
                                                            <?= $active ? 'Disable' : 'Enable' ?>
                                                        </button>
                                                    </form>

                                                    <button type="button" class="btn btn-sm btn-soft"
                                                            data-bs-toggle="modal" data-bs-target="#resetModal"
                                                            data-account-id="<?= (int) $account['id'] ?>"
                                                            data-account-name="<?= admin_h($account['username']) ?>">
                                                        <i class="ri-key-2-line"></i> Reset
                                                    </button>

                                                    <form method="post" action="process_team.php"
                                                          onsubmit="return confirm('Remove <?= admin_h($account['username']) ?> permanently?');">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                                                <?= $isSelf ? 'disabled' : '' ?>
                                                                aria-label="Remove <?= admin_h($account['username']) ?>">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4">
                                            <div class="empty-state">
                                                <i class="ri-team-line"></i>
                                                <h3>No studio accounts</h3>
                                                <p>Add the first one with the form beside this table.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card">
                    <div class="card-header">
                        <i class="ri-user-add-line me-2"></i>Add studio account
                    </div>
                    <div class="card-body">
                        <?php if (!$accountsSupported): ?>
                            <p class="text-muted small mb-0">
                                Run the migration noted above to create accounts.
                            </p>
                        <?php else: ?>
                            <form method="post" action="process_team.php">
                                <input type="hidden" name="action" value="create">

                                <div class="mb-3">
                                    <label class="form-label" for="newUsername">Username</label>
                                    <input class="form-control" type="text" id="newUsername" name="username"
                                           maxlength="50" required autocomplete="off" placeholder="e.g. front-desk">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newFullName">
                                        Display name <span class="text-muted">(optional)</span>
                                    </label>
                                    <input class="form-control" type="text" id="newFullName" name="full_name"
                                           maxlength="120" autocomplete="off" placeholder="e.g. Ana Reyes">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newPassword">Temporary password</label>
                                    <input class="form-control" type="text" id="newPassword" name="password"
                                           minlength="8" maxlength="72" required autocomplete="off"
                                           placeholder="At least 8 characters">
                                    <div class="form-text text-muted">
                                        Share it with the new user and ask them to change it after signing in.
                                    </div>
                                </div>

                                <button class="btn btn-primary w-100" type="submit">
                                    <i class="ri-user-add-line me-1"></i> Create account
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="process_team.php">
            <input type="hidden" name="action" value="password">
            <input type="hidden" name="id" id="resetAccountId" value="">

            <div class="modal-header border-secondary">
                <h2 class="modal-title fs-6">Reset password</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted small">
                    Set a new temporary password for
                    <strong class="text-white" id="resetAccountName"></strong>.
                </p>

                <label class="form-label" for="resetPassword">New password</label>
                <input class="form-control" type="text" id="resetPassword" name="password"
                       minlength="8" maxlength="72" required autocomplete="off">
            </div>

            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset password</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('resetModal').addEventListener('show.bs.modal', function (event) {
    var trigger = event.relatedTarget;

    document.getElementById('resetAccountId').value =
        trigger.getAttribute('data-account-id');

    document.getElementById('resetAccountName').textContent =
        trigger.getAttribute('data-account-name');
});
</script>
</body>
</html>
