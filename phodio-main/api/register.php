<?php

/**
 * Client self-registration.
 *
 * Errors are logged, never printed: the previous version showed visitors the
 * raw PostgreSQL message, which exposes table and column names.
 */

require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

$values = [
    'firstname' => '',
    'lastname' => '',
    'username' => '',
    'phone' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {

    $values['firstname'] = trim((string) ($_POST['firstname'] ?? ''));
    $values['lastname']  = trim((string) ($_POST['lastname'] ?? ''));
    $values['username']  = trim((string) ($_POST['username'] ?? ''));
    $values['phone']     = trim((string) ($_POST['phone'] ?? ''));

    $password  = (string) ($_POST['password'] ?? '');
    $confirm   = (string) ($_POST['confirm_password'] ?? '');

    // -----------------------------------------
    // VALIDATION
    // -----------------------------------------

    if (
        $values['firstname'] === '' ||
        $values['lastname'] === '' ||
        $values['username'] === '' ||
        $values['phone'] === '' ||
        $password === ''
    ) {
        $error = 'Please fill all the fields.';
    } elseif (
        !filter_var($values['username'], FILTER_VALIDATE_EMAIL) ||
        !str_ends_with(strtolower($values['username']), '@gmail.com')
    ) {
        $error = 'Please use a valid Gmail address.';
    } elseif (!preg_match('/^09\d{9}$/', $values['phone'])) {
        $error = 'Phone must start with 09 and be 11 digits.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {

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
                'SELECT id
                 FROM users
                 WHERE username = :username
                    OR phone = :phone
                 LIMIT 1'
            );

            $check->execute([
                ':username' => $values['username'],
                ':phone'    => $values['phone'],
            ]);

            $existingUser = $check->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {

                $error = 'That Gmail address or phone number is already registered. Try signing in instead.';

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
                 * Vercel's filesystem is temporary.
                 *
                 * For now, use the default image.
                 * We can move profile uploads to Supabase
                 * Storage afterward.
                 */

                $profile_image = 'default.png';

                // -----------------------------------------
                // INSERT USER
                // -----------------------------------------

                $stmt = $pdo->prepare(
                    'INSERT INTO users
                    (
                        firstname,
                        lastname,
                        username,
                        phone,
                        password,
                        profile_image
                    )
                    VALUES
                    (
                        :firstname,
                        :lastname,
                        :username,
                        :phone,
                        :password,
                        :profile_image
                    )'
                );

                $stmt->execute([
                    ':firstname'     => $values['firstname'],
                    ':lastname'      => $values['lastname'],
                    ':username'      => $values['username'],
                    ':phone'         => $values['phone'],
                    ':password'      => $hashedPassword,
                    ':profile_image' => $profile_image,
                ]);

                $success = 'Your account is ready. Sign in to book your first session.';

                $values = [
                    'firstname' => '',
                    'lastname' => '',
                    'username' => '',
                    'phone' => '',
                ];
            }

        } catch (Throwable $e) {

            /*
             * Reason goes to the function log; the visitor gets a message
             * they can act on.
             */
            error_log('Phodio: registration failed — ' . $e->getMessage());

            $error = 'We could not create your account just now. Please try again in a moment.';
        }
    }
}

function register_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Create account | SOULPRINT</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --bg: #0b0d12;
            --panel: #141a25;
            --line: #272f3f;
            --text: #e9edf5;
            --muted: #98a3b7;
            --red: #ef4444;
            --blue: #3b82f6;
            --radius: 18px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 34px 18px;
            background:
                radial-gradient(900px 480px at 12% -10%, rgba(239, 68, 68, .18), transparent 60%),
                radial-gradient(800px 420px at 110% 110%, rgba(59, 130, 246, .16), transparent 60%),
                var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .card {
            width: 100%;
            max-width: 470px;
            background: linear-gradient(180deg, var(--panel), #111621);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 32px 28px;
            box-shadow: 0 26px 60px rgba(0, 0, 0, .5);
        }

        .brand {
            font-size: 21px;
            font-weight: 800;
            letter-spacing: .22em;
            text-transform: uppercase;
            margin-bottom: 22px;
        }

        .brand span { color: var(--red); }

        h1 {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -.01em;
            margin: 0 0 6px;
        }

        .sub {
            color: var(--muted);
            font-size: .92rem;
            margin: 0 0 24px;
            line-height: 1.6;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .field { margin-bottom: 15px; }

        .field label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #b6c0d1;
            margin-bottom: 8px;
        }

        .control { position: relative; display: flex; align-items: center; }

        .control > i.lead {
            position: absolute;
            left: 14px;
            color: #7c8699;
            font-size: 1.05rem;
            pointer-events: none;
        }

        .control input {
            width: 100%;
            background: #0d121b;
            border: 1px solid #333c4e;
            border-radius: 11px;
            color: #fff;
            font-size: .93rem;
            padding: 12px 14px 12px 42px;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .control input::placeholder { color: #6b758a; }

        .control input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .22);
        }

        .control.no-icon input { padding-left: 14px; }

        .control .trail {
            position: absolute;
            right: 6px;
            background: none;
            border: 0;
            color: #8b95a8;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
            display: grid;
            place-items: center;
        }

        .control .trail:hover { color: #dbe3f0; }
        .control .trail:focus-visible { outline: 2px solid #93c5fd; outline-offset: 1px; }

        .field .note {
            color: #7f8a9e;
            font-size: .76rem;
            margin-top: 7px;
        }

        .submit {
            width: 100%;
            margin-top: 8px;
            border: 0;
            border-radius: 11px;
            padding: 14px 18px;
            font-size: .92rem;
            font-weight: 700;
            letter-spacing: .04em;
            color: #fff;
            cursor: pointer;
            background: linear-gradient(180deg, #f05252, #dc2626);
            box-shadow: 0 12px 26px rgba(220, 38, 38, .28);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            transition: transform .18s ease, filter .18s ease;
        }

        .submit:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .submit:focus-visible { outline: 2px solid #fca5a5; outline-offset: 2px; }
        .submit[disabled] { opacity: .75; cursor: progress; }

        .spinner {
            width: 15px;
            height: 15px;
            border: 2px solid rgba(255, 255, 255, .45);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
            display: none;
        }

        .submit.is-loading .spinner { display: inline-block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        .alert {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: .86rem;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .alert i { font-size: 1.05rem; }

        .alert--error {
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .28);
            color: #fca5a5;
        }

        .alert--success {
            background: rgba(34, 197, 94, .1);
            border: 1px solid rgba(34, 197, 94, .28);
            color: #86efac;
        }

        .alert--success a { color: #bbf7d0; font-weight: 600; }

        .switch {
            text-align: center;
            color: var(--muted);
            font-size: .88rem;
            margin: 20px 0 0;
        }

        .switch a { color: #93c5fd; text-decoration: none; font-weight: 600; }
        .switch a:hover { text-decoration: underline; }
        a:focus-visible { outline: 2px solid #93c5fd; outline-offset: 2px; border-radius: 4px; }

        @media (max-width: 460px) {
            .row { grid-template-columns: 1fr; gap: 0; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>

<div class="card">

    <div class="brand">SOUL<span>PRINT</span></div>

    <h1>Create your client account</h1>
    <p class="sub">
        It takes a minute. You will be able to book a session, follow its
        progress and message the studio.
    </p>

    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert">
            <i class="ri-error-warning-line"></i>
            <span><?= register_h($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert--success" role="status">
            <i class="ri-checkbox-circle-line"></i>
            <span>
                <?= register_h($success) ?>
                <a href="login.php">Sign in now</a>
            </span>
        </div>
    <?php endif; ?>

    <form method="POST" id="registerForm" novalidate>

        <div class="row">
            <div class="field">
                <label for="firstname">First name</label>
                <div class="control">
                    <i class="ri-user-3-line lead"></i>
                    <input type="text" id="firstname" name="firstname"
                           placeholder="Juan"
                           value="<?= register_h($values['firstname']) ?>"
                           autocomplete="given-name" required>
                </div>
            </div>

            <div class="field">
                <label for="lastname">Last name</label>
                <div class="control">
                    <i class="ri-user-3-line lead"></i>
                    <input type="text" id="lastname" name="lastname"
                           placeholder="Dela Cruz"
                           value="<?= register_h($values['lastname']) ?>"
                           autocomplete="family-name" required>
                </div>
            </div>
        </div>

        <div class="field">
            <label for="username">Gmail address</label>
            <div class="control">
                <i class="ri-mail-line lead"></i>
                <input type="email" id="username" name="username"
                       placeholder="you@gmail.com"
                       value="<?= register_h($values['username']) ?>"
                       autocomplete="email" required>
            </div>
        </div>

        <div class="field">
            <label for="phone">Phone number</label>
            <div class="control">
                <i class="ri-phone-line lead"></i>
                <input type="tel" id="phone" name="phone"
                       placeholder="09XXXXXXXXX" maxlength="11"
                       inputmode="numeric"
                       value="<?= register_h($values['phone']) ?>"
                       autocomplete="tel" required>
            </div>
            <div class="note">11 digits, starting with 09.</div>
        </div>

        <div class="field">
            <label for="passwordInput">Password</label>
            <div class="control">
                <i class="ri-lock-2-line lead"></i>
                <input type="password" id="passwordInput" name="password"
                       placeholder="At least 8 characters"
                       autocomplete="new-password" required>
                <button type="button" class="trail" id="togglePassword"
                        aria-label="Show password" aria-controls="passwordInput">
                    <i class="ri-eye-line" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <div class="field">
            <label for="confirmInput">Confirm password</label>
            <div class="control">
                <i class="ri-lock-2-line lead"></i>
                <input type="password" id="confirmInput" name="confirm_password"
                       placeholder="Type it again"
                       autocomplete="new-password" required>
                <button type="button" class="trail" id="toggleConfirm"
                        aria-label="Show password" aria-controls="confirmInput">
                    <i class="ri-eye-line" id="eyeIconConfirm"></i>
                </button>
            </div>
        </div>

        <button type="submit" name="register" value="1" class="submit" id="submitButton">
            <span class="spinner" aria-hidden="true"></span>
            <span id="submitLabel">Create account</span>
        </button>

    </form>

    <p class="switch">
        Already have an account?
        <a href="login.php">Sign in</a>
    </p>

</div>

<script>
(function () {
    function wirePasswordToggle(buttonId, inputId, iconId) {
        var button = document.getElementById(buttonId);
        var input = document.getElementById(inputId);
        var icon = document.getElementById(iconId);

        if (!button || !input) {
            return;
        }

        button.addEventListener('click', function () {
            var next = input.getAttribute('type') === 'password' ? 'text' : 'password';

            input.setAttribute('type', next);
            button.setAttribute('aria-label', next === 'password' ? 'Show password' : 'Hide password');

            icon.classList.toggle('ri-eye-line');
            icon.classList.toggle('ri-eye-off-line');
        });
    }

    wirePasswordToggle('togglePassword', 'passwordInput', 'eyeIcon');
    wirePasswordToggle('toggleConfirm', 'confirmInput', 'eyeIconConfirm');

    var form = document.getElementById('registerForm');
    var button = document.getElementById('submitButton');

    form.addEventListener('submit', function () {
        button.classList.add('is-loading');
        button.disabled = true;
        document.getElementById('submitLabel').textContent = 'Creating account…';
    });
})();
</script>

</body>
</html>
