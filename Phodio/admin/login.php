<?php
// MUST BE THE VERY FIRST LINE
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/ui.php';

$error = null;

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if ($result && password_verify($password, $result['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin'] = $result['username'];

        // Force the session to save before redirecting
        session_write_close();

        header("Location: dashboard.php");
        exit();
    }
    // One message for both cases so the form never reveals which detail was wrong.
    $error = 'Incorrect username or password. Please try again.';
}

$spDepth = 1;
$spTitle = 'Admin Login';
$spDescription = 'Sign in to the SOULPRINT studio management console.';
require __DIR__ . '/../includes/page_top.php';
?>
<div class="sp-auth">
    <div class="sp-auth__card sp-enter">
        <div class="text-center">
            <div class="sp-auth__logo mb-2"><?= SP_BRAND_MARKUP ?></div>
            <p class="sp-eyebrow justify-content-center mb-3"><?= sp_h(SP_ADMIN_LABEL) ?></p>
            <h1 class="h5 fw-bold mb-1">Studio console</h1>
            <p class="sp-subtitle mx-auto mb-4">Bookings, expenses, liabilities and daily performance in one place.</p>
        </div>

        <?php if ($error !== null): ?>
            <?= sp_flash('danger', $error) ?>
        <?php endif; ?>

        <form method="POST" novalidate>
            <div class="sp-field">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-user-3-line"></i></span>
                    <input type="text" name="username" id="username" class="form-control"
                           placeholder="Enter username" autocomplete="username" required autofocus>
                </div>
            </div>

            <div class="sp-field">
                <label class="form-label" for="passwordInput">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
                    <input type="password" name="password" id="passwordInput" class="form-control"
                           placeholder="••••••••" autocomplete="current-password" required>
                    <button class="input-group-text" type="button"
                            data-sp-toggle-password="passwordInput" aria-label="Show password">
                        <i class="ri-eye-line"></i>
                    </button>
                </div>
            </div>

            <div class="d-grid mt-4">
                <button type="submit" name="login" class="btn btn-primary py-2">
                    <i class="ri-shield-keyhole-line me-2"></i>Access studio
                </button>
            </div>
        </form>

        <div class="sp-auth__divider">Client</div>

        <div class="sp-auth__switch">
            Booking a session? <a href="../client_login.php">Open the client portal</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/page_bottom.php'; ?>
