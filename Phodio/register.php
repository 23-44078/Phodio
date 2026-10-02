<?php
session_start();
require_once __DIR__ . '/db/db.php';
require_once __DIR__ . '/includes/ui.php';

$error = null;
$success = null;
$form = ['firstname' => '', 'lastname' => '', 'username' => '', 'phone' => ''];

if (isset($_POST['register'])) {
    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $username  = trim($_POST['username']); // gmail
    $phone     = trim($_POST['phone']);
    $password  = trim($_POST['password']);
    $form = compact('firstname', 'lastname', 'username', 'phone');

    // Handle photo upload
    $profile_image = 'default.png'; // default
    if (isset($_FILES['profile']) && $_FILES['profile']['error'] === 0) {
        $ext = pathinfo($_FILES['profile']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($ext), $allowed, true)) {
            $newName = uniqid('profile_', true) . '.' . $ext;
            $uploadDir = 'uploads/profile/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            if (move_uploaded_file($_FILES['profile']['tmp_name'], $uploadDir . $newName)) {
                $profile_image = $newName;
            }
        } else {
            $error = 'Profile photo must be a JPG, JPEG, PNG, or GIF file.';
        }
    }

    if ($error === null) {
        if (empty($firstname) || empty($lastname) || empty($username) || empty($phone) || empty($password)) {
            $error = 'Please complete every field before creating your account.';
        } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL) || !str_contains($username, '@gmail.com')) {
            $error = 'Please use a valid Gmail address.';
        } elseif (!preg_match('/^09\d{9}$/', $phone)) {
            $error = 'Phone number must start with 09 and contain 11 digits.';
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE username=? OR phone=?");
            $check->bind_param("ss", $username, $phone);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = 'That Gmail address or phone number is already registered. Try logging in instead.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, phone, password, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssss", $firstname, $lastname, $username, $phone, $hashedPassword, $profile_image);
                if ($stmt->execute()) {
                    $success = 'Account created. You can log in now.';
                    $form = ['firstname' => '', 'lastname' => '', 'username' => '', 'phone' => ''];
                } else {
                    $error = 'Something went wrong while saving your account. Please try again.';
                }
            }
        }
    }
}

$spTitle = 'Create Account';
$spDescription = 'Create a SOULPRINT client account to request photography sessions and track their progress.';
include __DIR__ . '/includes/page_top.php';
?>
<div class="sp-auth">
    <div class="sp-auth__card sp-enter">
        <div class="text-center">
            <div class="sp-auth__logo mb-2"><?= SP_BRAND_MARKUP ?></div>
            <p class="sp-eyebrow justify-content-center mb-3"><?= sp_h(SP_PORTAL_LABEL) ?></p>
            <h1 class="h5 fw-bold mb-1">Create client account</h1>
            <p class="sp-subtitle mx-auto mb-4">Request sessions and follow every update from one place.</p>
        </div>

        <?php if ($error !== null): ?>
            <?= sp_flash('danger', $error) ?>
        <?php endif; ?>
        <?php if ($success !== null): ?>
            <?= sp_flash('success', $success) ?>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" novalidate>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="firstname">First name</label>
                    <input type="text" id="firstname" name="firstname" class="form-control"
                           placeholder="Juan" value="<?= sp_h($form['firstname']) ?>" required>
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="lastname">Last name</label>
                    <input type="text" id="lastname" name="lastname" class="form-control"
                           placeholder="Dela Cruz" value="<?= sp_h($form['lastname']) ?>" required>
                </div>
            </div>

            <div class="sp-field mt-3">
                <label class="form-label" for="username">Gmail address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-mail-line"></i></span>
                    <input type="email" id="username" name="username" class="form-control"
                           placeholder="you@gmail.com" value="<?= sp_h($form['username']) ?>"
                           autocomplete="username" required>
                </div>
            </div>

            <div class="sp-field">
                <label class="form-label" for="phone">Mobile number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-smartphone-line"></i></span>
                    <input type="tel" id="phone" name="phone" class="form-control" maxlength="11"
                           inputmode="numeric" placeholder="09XXXXXXXXX" value="<?= sp_h($form['phone']) ?>"
                           autocomplete="tel" required>
                </div>
                <div class="form-hint mt-1">Starts with 09 and contains 11 digits.</div>
            </div>

            <div class="sp-field">
                <label class="form-label" for="passwordInput">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="ri-lock-2-line"></i></span>
                    <input type="password" id="passwordInput" name="password" class="form-control"
                           placeholder="••••••••" autocomplete="new-password" required>
                    <button class="input-group-text" type="button"
                            data-sp-toggle-password="passwordInput" aria-label="Show password">
                        <i class="ri-eye-line"></i>
                    </button>
                </div>
            </div>

            <div class="sp-field">
                <label class="form-label" for="profile">Profile photo <span class="sp-faint fw-normal text-lowercase">(optional)</span></label>
                <input type="file" id="profile" name="profile" class="form-control" accept="image/*">
            </div>

            <div class="d-grid mt-4">
                <button type="submit" name="register" class="btn btn-primary py-2">
                    <i class="ri-user-add-line me-2"></i>Create account
                </button>
            </div>
        </form>

        <div class="sp-auth__switch">
            Already have an account? <a href="client_login.php">Log in</a>
        </div>
    </div>
</div>
<?php include __DIR__ . '/includes/page_bottom.php'; ?>
