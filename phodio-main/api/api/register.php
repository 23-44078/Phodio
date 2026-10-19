<?php

/*
|--------------------------------------------------------------------------
| AJAX DETECTION
|--------------------------------------------------------------------------
|
| The sliding sign-up form on client_login.php posts here in the background.
| Deciding this BEFORE any include runs lets every failure path (including
| the "database connection failed" exit inside config/database.php and any
| PHP fatal) still answer with valid JSON instead of an HTML page, which the
| browser could only report as "Could not connect".
|
*/

$phodioIsAjax =
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

// Buffer everything so stray notices/warnings can never corrupt the JSON body.
ob_start();

/**
 * Send the JSON answer for an AJAX registration attempt and stop.
 */
function phodio_register_json(?string $success, ?string $error): void
{
    if (ob_get_level() > 0) {
        ob_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    define('PHODIO_REGISTER_RESPONDED', true);

    echo json_encode([
        'success' => $success,
        'error'   => $error,
    ]);

    exit;
}

// Last-resort JSON answer if the request dies unexpectedly (missing DB
// extension, connection failure, timeout, fatal error...).
if ($phodioIsAjax) {
    register_shutdown_function(function () {
        if (defined('PHODIO_REGISTER_RESPONDED')) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
        }

        echo json_encode([
            'success' => null,
            'error'   => 'We could not create your account right now. Please try again.',
        ]);
    });
}

require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';

    // Gmail addresses are not case sensitive.
    $username = strtolower($username);

    // Accept 09171234567, 0917 123 4567, +63 917 123 4567 and 639171234567.
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    if (str_starts_with($phone, '+63')) {
        $phone = '0' . substr($phone, 3);
    } elseif (str_starts_with($phone, '63') && strlen($phone) === 12) {
        $phone = '0' . substr($phone, 2);
    }

    // -----------------------------------------
    // VALIDATION
    // -----------------------------------------

    if (
        $firstname === '' ||
        $lastname === '' ||
        $username === '' ||
        $phone === '' ||
        $password === ''
    ) {
        $error = 'Please fill all the fields.';
    }

    elseif (
        !filter_var($username, FILTER_VALIDATE_EMAIL) ||
        !str_ends_with($username, '@gmail.com')
    ) {
        $error = 'Please use a valid Gmail address.';
    }

    elseif (!preg_match('/^09\d{9}$/', $phone)) {
        $error = 'Phone must start with 09 and be 11 digits.';
    }

    else {

        try {

            /*
             * Use PDO directly.
             *
             * The database.php compatibility layer still exists
             * for the rest of the application, but registration
             * uses PDO directly so PostgreSQL parameters work
             * correctly.
             */

            $pdo = $conn->pdo();

            // -----------------------------------------
            // CHECK EXISTING USER
            // -----------------------------------------

            $check = $pdo->prepare(
                'SELECT id, username, phone
                 FROM users
                 WHERE LOWER(username) = LOWER(:username)
                    OR phone = :phone
                 LIMIT 1'
            );

            $check->execute([
                ':username' => $username,
                ':phone'    => $phone
            ]);

            $existingUser = $check->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {

                if (
                    isset($existingUser['username']) &&
                    strtolower((string) $existingUser['username']) === $username
                ) {
                    $error = 'An account with this Gmail already exists.';
                } else {
                    $error = 'An account with this phone number already exists.';
                }

            } else {

                // -----------------------------------------
                // HASH PASSWORD
                // -----------------------------------------

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // -----------------------------------------
                // PROFILE IMAGE
                // -----------------------------------------

                /*
                 * Vercel's filesystem is temporary, so profile photos are
                 * added later (see the note under the file input).
                 */

                $profile_image = 'default.png';

                $insertColumns = 'firstname, lastname, username, phone, password, profile_image';
                $insertValues  = ':firstname, :lastname, :username, :phone, :password, :profile_image';

                $params = [
                    ':firstname'     => $firstname,
                    ':lastname'      => $lastname,
                    ':username'      => $username,
                    ':phone'         => $phone,
                    ':password'      => $hashedPassword,
                    ':profile_image' => $profile_image,
                ];

                $attempt = 0;

                while (true) {

                    $attempt++;

                    // Drop the profile_image column if this database was
                    // created before that column existed.
                    if (!isset($params[':profile_image'])) {
                        $insertColumns = 'firstname, lastname, username, phone, password';
                        $insertValues  = ':firstname, :lastname, :username, :phone, :password';
                    }

                    try {

                        $stmt = $pdo->prepare(
                            'INSERT INTO users (' . $insertColumns . ')
                             VALUES (' . $insertValues . ')'
                        );

                        $stmt->execute($params);

                        $success = 'Successfully Registered!';
                        break;

                    } catch (PDOException $insertError) {

                        $sqlState = (string) $insertError->getCode();
                        $message  = $insertError->getMessage();

                        /*
                         * The imported sample rows keep ids 1 and 2 but the
                         * PostgreSQL sequence can fall behind them. The very
                         * next account would then collide on users_pkey and
                         * never be created. Re-sync the sequence and retry.
                         */
                        if (
                            $sqlState === '23505' &&
                            $attempt === 1 &&
                            stripos($message, 'users_pkey') !== false
                        ) {
                            try {
                                $pdo->query(
                                    "SELECT setval(
                                         pg_get_serial_sequence('users', 'id'),
                                         COALESCE((SELECT MAX(id) FROM users), 0) + 1,
                                         false
                                     )"
                                );
                            } catch (Throwable $sequenceError) {
                                // Ignore: the retry below will report the
                                // real problem if it still fails.
                            }

                            continue;
                        }

                        // Old database without the profile_image column.
                        if (
                            $sqlState === '42703' &&
                            stripos($message, 'profile_image') !== false &&
                            isset($params[':profile_image'])
                        ) {
                            unset($params[':profile_image']);
                            continue;
                        }

                        if ($sqlState === '23505') {
                            $error = 'An account with this Gmail or phone number already exists.';
                        } else {
                            $error = 'We could not create your account right now. Please try again.';
                        }

                        error_log('Phodio registration failed: ' . $message);

                        break;
                    }
                }
            }

        } catch (PDOException $e) {

            /*
             * Keep the reason in the server log; never render raw SQL in the
             * browser.
             */
            error_log('Phodio registration failed: ' . $e->getMessage());

            $error = 'We could not create your account right now. Please try again.';

        } catch (Throwable $e) {

            error_log('Phodio registration failed: ' . $e->getMessage());

            $error = 'We could not create your account right now. Please try again.';
        }
    }
}


/*
|--------------------------------------------------------------------------
| AJAX RESPONSE
|--------------------------------------------------------------------------
|
| When the request comes from the sliding sign-up form in client_login.php
| return JSON so the page can display the result without a full reload.
|
*/

if (
    $phodioIsAjax &&
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['register'])
) {
    phodio_register_json(
        $success !== '' ? $success : null,
        $error !== '' ? $error : null
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Register | SOULPRINT</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<style>

body {
    background: #0f0f0f;
    color: white;
    font-family: Arial, sans-serif;
}

.register-card {
    background: #1a1a1a;
    padding: 30px;
    border-radius: 15px;
    max-width: 450px;
    margin: 50px auto;
    border: 1px solid #333;
}

.form-control {
    background: #222;
    color: #fff;
    border: 1px solid #444;
}

.form-control:focus {
    background: #222;
    color: #fff;
    border-color: #3b82f6;
    box-shadow: none;
}

.form-control::placeholder {
    color: #888;
}

.btn-register {
    background: #ef4444;
    border: none;
    width: 100%;
    font-weight: bold;
    margin-top: 10px;
}

.btn-register:hover {
    background: #dc2626;
}

.success-msg {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.error-msg {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.switch-link {
    text-align: center;
    margin-top: 10px;
}

.switch-link a {
    color: #3b82f6;
    text-decoration: none;
}

.switch-link a:hover {
    text-decoration: underline;
}

</style>

</head>

<body>

<div class="register-card">

    <h4 class="text-center mb-3">
        Create Client Account
    </h4>

    <?php if ($error !== ''): ?>

        <div class="error-msg">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($success !== ''): ?>

        <div class="success-msg">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        enctype="multipart/form-data"
        autocomplete="off"
    >

        <div class="mb-2">

            <label for="firstname">
                First Name
            </label>

            <input
                type="text"
                id="firstname"
                name="firstname"
                class="form-control"
                placeholder="Enter your first name"
                value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>"
                required
            >

        </div>

        <div class="mb-2">

            <label for="lastname">
                Last Name
            </label>

            <input
                type="text"
                id="lastname"
                name="lastname"
                class="form-control"
                placeholder="Enter your last name"
                value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>"
                required
            >

        </div>

        <div class="mb-2">

            <label for="username">
                Gmail
            </label>

            <input
                type="email"
                id="username"
                name="username"
                class="form-control"
                placeholder="Enter your Gmail"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                autocomplete="off"
                required
            >

        </div>

        <div class="mb-2">

            <label for="phone">
                Phone
            </label>

            <input
                type="text"
                id="phone"
                name="phone"
                class="form-control"
                maxlength="11"
                placeholder="09XXXXXXXXX"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                required
            >

        </div>

        <div class="mb-2">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="passwordInput"
                name="password"
                class="form-control"
                placeholder="Enter a password"
                autocomplete="new-password"
                required
            >

        </div>

        <div class="mb-3">

            <label for="profile">
                Profile Photo
            </label>

            <input
                type="file"
                id="profile"
                name="profile"
                class="form-control"
                accept="image/*"
            >

            <small class="text-secondary">
                You can add your profile photo later.
            </small>

        </div>

        <button
            type="submit"
            name="register"
            class="btn btn-register"
        >
            Register
        </button>

    </form>

    <div class="switch-link">

        Already have an account?

        <a href="client_login.php">
            Login
        </a>

    </div>

</div>

</body>

</html>
