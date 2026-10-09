<?php

/**
 * Studio accounts (super admin only).
 *
 * Lets the studio owner create admin accounts, promote them to super admin,
 * disable them, reset their password, or remove them.
 */

require_once __DIR__ . '/functions.php';

checkSuperAdmin();

$pdo = $conn->pdo();

$rolesSupported = phodio_admin_roles_supported($pdo);
$currentAdmin = phodio_current_admin() ?? ['id' => 0, 'username' => '', 'role' => PHODIO_ROLE_ADMIN];

if ($rolesSupported) {
    $result = $conn->query(
        'SELECT
            id,
            username,
            role,
            full_name,
            (COALESCE(is_active, TRUE))::int AS is_active,
            last_login_at,
            created_at
         FROM admin
         ORDER BY id'
    );
} else {
    $result = $conn->query(
        "SELECT
            id,
            username,
            'super_admin' AS role,
            NULL AS full_name,
            1 AS is_active,
            NULL AS last_login_at,
            created_at
         FROM admin
         ORDER BY id"
    );
}

$accounts = $result ? $result->fetch_all() : [];

$superAdminCount = 0;

foreach ($accounts as $account) {
    if (strtolower((string) $account['role']) === PHODIO_ROLE_SUPER_ADMIN) {
        $superAdminCount++;
    }
}

function team_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function team_is_super(array $account): bool
{
    return strtolower((string) ($account['role'] ?? '')) === PHODIO_ROLE_SUPER_ADMIN;
}

$statusMessage = '';
$errorMessage = '';

if (isset($_GET['status'])) {
    $map = [
        'created' => 'Studio account created.',
        'updated' => 'Studio account updated.',
        'role' => 'Role updated.',
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
    <style>
        .role-chip{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;padding:4px 10px;font-size:.72rem;font-weight:750;letter-spacing:.02em}
        .role-super{background:rgba(239,68,68,.16);color:#fca5a5}
        .role-admin{background:rgba(59,130,246,.16);color:#93c5fd}
        .state-chip{border-radius:999px;padding:4px 10px;font-size:.72rem;font-weight:700}
        .state-on{background:rgba(34,197,94,.16);color:#86efac}
        .state-off{background:rgba(148,163,184,.16);color:#cbd5e1}
        .team-table td{vertical-align:middle}
        .team-actions{display:flex;flex-wrap:wrap;gap:.35rem;justify-content:flex-end}
        .team-actions .btn{padding:.25rem .55rem;font-size:.75rem}
        .form-control,.form-select{background:#222;border:1px solid #444;color:#fff}
        .form-control:focus,.form-select:focus{background:#222;border-color:#3b82f6;color:#fff;box-shadow:0 0 0 3px rgba(59,130,246,.25)}
        .form-label{font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#a0a0a0}
        code.migration{background:#0f0f0f;border:1px solid #333;border-radius:6px;padding:1px 6px;color:#93c5fd;font-size:.85em}
    </style>
</head>
<body>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
    <div class="container-fluid py-4">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <div class="text-uppercase small fw-bold text-info mb-1" style="letter-spacing:.12em">
                    Super admin
                </div>
                <h1 class="h3 text-white fw-bold mb-1">Studio accounts</h1>
                <p class="text-muted mb-0">
                    Control who can open the management panel and what they can reach.
                </p>
            </div>
            <span class="role-chip role-super">
                <i class="ri-shield-keyhole-line"></i>
                <?= team_h(currentAdminRoleLabel()) ?>
            </span>
        </div>

        <?php if (!$rolesSupported): ?>
            <div class="alert alert-warning border-0" style="background:rgba(245,158,11,.14);color:#fcd34d">
                <i class="ri-error-warning-line me-2"></i>
                <strong>Roles are not active yet.</strong>
                Run <code class="migration">api/db/migrations/20261009_01_admin_roles.sql</code>
                in the Supabase SQL Editor. Until then every studio account keeps full
                access and the role controls below are unavailable.
            </div>
        <?php endif; ?>

        <?php if ($statusMessage !== ''): ?>
            <div class="alert alert-success" role="status"><?= team_h($statusMessage) ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger" role="alert"><?= team_h($errorMessage) ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card shadow border-0">
                    <div class="card-header d-flex justify-content-between align-items-center text-white">
                        <span>
                            <i class="ri-team-line me-2 text-info"></i>
                            Accounts
                        </span>
                        <span class="small text-muted">
                            <?= count($accounts) ?> total ·
                            <?= $superAdminCount ?> super admin
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-borderless text-white mb-0 team-table">
                                <thead>
                                    <tr class="small text-muted text-uppercase">
                                        <th class="ps-4">Account</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Last sign-in</th>
                                        <th class="text-end pe-4">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($accounts as $account): ?>
                                        <?php
                                        $isSelf = (int) $account['id'] === (int) $currentAdmin['id'];
                                        $isSuper = team_is_super($account);
                                        $active = (int) $account['is_active'] === 1;
                                        $lastLogin = trim((string) ($account['last_login_at'] ?? ''));
                                        ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold">
                                                    <?= team_h($account['username']) ?>
                                                    <?php if ($isSelf): ?>
                                                        <span class="badge bg-secondary ms-1">you</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="small text-muted">
                                                    <?= team_h($account['full_name'] ?? '') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($rolesSupported): ?>
                                                    <form method="post" action="process_team.php" class="d-flex gap-2">
                                                        <input type="hidden" name="action" value="role">
                                                        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                                        <select
                                                            class="form-select form-select-sm"
                                                            name="role"
                                                            style="width:auto"
                                                            <?= ($isSelf || $isSuper && $superAdminCount <= 1) ? 'disabled' : '' ?>
                                                            aria-label="Role for <?= team_h($account['username']) ?>"
                                                        >
                                                            <option value="admin" <?= $isSuper ? '' : 'selected' ?>>Admin</option>
                                                            <option value="super_admin" <?= $isSuper ? 'selected' : '' ?>>Super admin</option>
                                                        </select>
                                                        <button class="btn btn-sm btn-outline-light" type="submit">
                                                            Save
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="role-chip role-super">
                                                        <i class="ri-shield-keyhole-line"></i> Super admin
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="state-chip <?= $active ? 'state-on' : 'state-off' ?>">
                                                    <?= $active ? 'Active' : 'Disabled' ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted">
                                                <?= $lastLogin !== '' ? team_h($lastLogin) : '—' ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="team-actions">
                                                    <form method="post" action="process_team.php">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                                        <button
                                                            class="btn btn-sm btn-outline-light"
                                                            type="submit"
                                                            <?= $isSelf ? 'disabled' : '' ?>
                                                        >
                                                            <i class="ri-<?= $active ? 'close' : 'check' ?>-line"></i>
                                                            <?= $active ? 'Disable' : 'Enable' ?>
                                                        </button>
                                                    </form>

                                                    <button
                                                        type="button"
                                                        class="btn btn-sm btn-outline-light"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#resetModal"
                                                        data-account-id="<?= (int) $account['id'] ?>"
                                                        data-account-name="<?= team_h($account['username']) ?>"
                                                    >
                                                        <i class="ri-key-2-line"></i> Reset
                                                    </button>

                                                    <form
                                                        method="post"
                                                        action="process_team.php"
                                                        onsubmit="return confirm('Remove <?= team_h($account['username']) ?> permanently?');"
                                                    >
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="id" value="<?= (int) $account['id'] ?>">
                                                        <button
                                                            class="btn btn-sm btn-outline-danger"
                                                            type="submit"
                                                            <?= ($isSelf || ($isSuper && $superAdminCount <= 1)) ? 'disabled' : '' ?>
                                                        >
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card shadow border-0">
                    <div class="card-header text-white">
                        <i class="ri-user-add-line me-2 text-info"></i>
                        Add studio account
                    </div>
                    <div class="card-body">
                        <?php if (!$rolesSupported): ?>
                            <p class="text-muted small mb-0">
                                Enable roles first (see the notice above) to create
                                accounts with the right level of access.
                            </p>
                        <?php else: ?>
                            <form method="post" action="process_team.php">
                                <input type="hidden" name="action" value="create">

                                <div class="mb-3">
                                    <label class="form-label" for="newUsername">Username</label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        id="newUsername"
                                        name="username"
                                        maxlength="50"
                                        required
                                        autocomplete="off"
                                        placeholder="e.g. front-desk"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newFullName">Display name <span class="text-muted">(optional)</span></label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        id="newFullName"
                                        name="full_name"
                                        maxlength="120"
                                        autocomplete="off"
                                        placeholder="e.g. Ana Reyes"
                                    >
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newRole">Role</label>
                                    <select class="form-select" id="newRole" name="role">
                                        <option value="admin">Admin — schedule, expenses, messages</option>
                                        <option value="super_admin">Super admin — full access + accounts</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newPassword">Temporary password</label>
                                    <input
                                        class="form-control"
                                        type="text"
                                        id="newPassword"
                                        name="password"
                                        minlength="8"
                                        maxlength="72"
                                        required
                                        autocomplete="off"
                                        placeholder="At least 8 characters"
                                    >
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

                <div class="card shadow border-0 mt-4">
                    <div class="card-header text-white">
                        <i class="ri-information-line me-2 text-info"></i>
                        What each role can do
                    </div>
                    <div class="card-body small text-muted">
                        <p class="mb-2">
                            <strong class="text-white">Admin</strong> — schedule and edit
                            appointments, post progress updates, record expenses and
                            liabilities, and reply to client messages.
                        </p>
                        <p class="mb-0">
                            <strong class="text-white">Super admin</strong> — everything an
                            admin can do, plus managing studio accounts on this page.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content border-0" style="background:#1a1a1a;border-radius:15px" method="post" action="process_team.php">
            <input type="hidden" name="action" value="password">
            <input type="hidden" name="id" id="resetAccountId" value="">
            <div class="modal-header border-secondary">
                <h2 class="modal-title fs-6 text-white">Reset password</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Set a new temporary password for
                    <strong class="text-white" id="resetAccountName"></strong>.
                </p>
                <label class="form-label" for="resetPassword">New password</label>
                <input
                    class="form-control"
                    type="text"
                    id="resetPassword"
                    name="password"
                    minlength="8"
                    maxlength="72"
                    required
                    autocomplete="off"
                >
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
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
