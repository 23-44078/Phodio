<?php
session_start();
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/includes/ui.php';

$error = null;

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, firstname, lastname, password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['client_id'] = $user['id'];
            $_SESSION['client'] = $username;
            $_SESSION['client_name'] = $user['firstname'] . ' ' . $user['lastname'];

            header("Location: client_dashboard.php");
            exit;
        }
    }
    // One message for both cases so the form never reveals which detail was wrong.
    $error = 'Incorrect Gmail address or password. Please try again.';
}

$spTitle = 'Client Login';
$spDescription = 'Sign in to the SOULPRINT client portal to request sessions and follow your service progress.';
include __DIR__ . '/includes/page_top.php';
?>
<div class="sp-auth">
    <div class="sp-auth__card sp-enter">
        <div class="text-center">
            <div class="sp-auth__logo mb-2"><?= SP_BRAND_MARKUP ?></div>
            <p class="sp-eyebrow justify-content-center mb-3"><?= sp_h(SP_PORTAL_LABEL) ?></p>
            <h1 class="h5 fw-bold mb-1">Sign in to your account</h1>
            <p class="sp-subtitle mx-auto mb-4">Request sessions and follow every update from one place.</p>
        </div>

        <?php if ($error !== null): ?>
            <?= sp_flash('danger', $error) ?>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="sp-field">
                <label class="form-label" for="username">Gmail address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-mail-line"></i></span>
                    <input type="email" name="username" id="username" class="form-control"
                           placeholder="you@gmail.com" autocomplete="username" required>
                </div>
            </div>

            <div class="sp-field">
                <label class="form-label" for="passwordInput">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
                    <input type="password" name="password" id="passwordInput" class="form-control"
                           placeholder="••••••••" autocomplete="current-password" required>
                    <button class="input-group-text" type="button" id="togglePassword"
                            data-sp-toggle-password="passwordInput" aria-label="Show password">
                        <i class="ri-eye-line" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" name="login" class="btn btn-primary py-2">
                    <i class="ri-login-box-line me-2"></i>Log in
                </button>
            </div>
        </form>

        <div class="sp-auth__divider">New here</div>

        <div class="sp-auth__switch">
            Don&rsquo;t have an account? <a href="register.php">Create one</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/page_bottom.php'; ?>
