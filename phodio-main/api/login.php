<?php

/**
 * Unified sign-in for Phodio.
 *
 * One form serves both account types. The username is looked up in the studio
 * table first and then in the client table, so clients and staff share a
 * single URL and a single set of error messages.
 *
 *   /login.php            client mode
 *   /login.php?as=admin   studio mode
 */

require_once __DIR__ . '/includes/auth.php';

phodio_start_session();

$pdo = $conn->pdo();

/*
 * Already signed in? Send people straight to their home page.
 */
if (phodio_current_admin() !== null) {
    header('Location: admin/dashboard.php');
    exit;
}

if (phodio_current_client_id($pdo) !== null) {
    header('Location: client_dashboard.php');
    exit;
}

$mode = strtolower(trim((string) ($_GET['as'] ?? 'client')));

if (!in_array($mode, ['client', 'admin'], true)) {
    $mode = 'client';
}

$error = '';
$usernameValue = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $usernameValue = trim((string) ($_POST['username'] ?? ''));
    $postedMode = strtolower(trim((string) ($_POST['as'] ?? $mode)));

    if (in_array($postedMode, ['client', 'admin'], true)) {
        $mode = $postedMode;
    }

    $result = phodio_unified_login(
        $pdo,
        $usernameValue,
        (string) ($_POST['password'] ?? '')
    );

    if ($result['ok']) {
        header(
            'Location: ' . (
                $result['type'] === 'admin'
                    ? 'admin/dashboard.php'
                    : 'client_dashboard.php'
            )
        );
        exit;
    }

    $error = $result['error'] ?? 'Sign-in failed. Please try again.';
}

$usernameLabel = $mode === 'admin' ? 'Studio username' : 'Email address';
$usernamePlaceholder = $mode === 'admin' ? 'e.g. soulprint' : 'you@example.com';
$usernameType = $mode === 'admin' ? 'text' : 'email';
$usernameAutocomplete = $mode === 'admin' ? 'username' : 'email';

function login_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in | SOULPRINT</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --bg-dark: #0f0f0f;
            --card-bg: #1a1a1a;
            --accent-red: #ef4444;
            --accent-blue: #3b82f6;
        }

        * { box-sizing: border-box; }

        body {
            background:
                radial-gradient(1000px 500px at 15% -10%, rgba(59,130,246,.16), transparent 60%),
                radial-gradient(800px 400px at 110% 110%, rgba(239,68,68,.12), transparent 60%),
                var(--bg-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            margin: 0;
            color: #fff;
            padding: 20px 0;
        }

        .login-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 16px;
            width: 100%;
            max-width: 430px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, .65);
            border: 1px solid #2b2b2b;
            margin: 15px;
        }

        .brand-logo {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 3px;
            margin-bottom: 6px;
        }

        .segmented {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            background: #12161f;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 4px;
            margin-bottom: 22px;
        }

        .segmented input { position: absolute; opacity: 0; pointer-events: none; }

        .segmented label {
            margin: 0;
            text-align: center;
            padding: 9px 6px;
            border-radius: 7px;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: #9aa4b2;
            cursor: pointer;
            transition: .2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
        }

        .segmented label:hover { color: #e5e7eb; }
        .segmented input:checked + label { background: var(--accent-blue); color: #fff; }
        .segmented input:focus-visible + label { outline: 2px solid #fff; outline-offset: 2px; }

        .form-label {
            color: #fff !important;
            letter-spacing: .07em;
            font-size: .7rem;
        }

        .form-control {
            background: #222 !important;
            border: 1px solid #444 !important;
            color: #fff !important;
            padding: 12px 15px;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: var(--accent-blue) !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .25) !important;
        }

        .form-control::placeholder { color: #666 !important; }

        .input-group-text {
            border-color: #444 !important;
            color: #fff !important;
            cursor: pointer;
        }

        .btn-login {
            background: var(--accent-blue);
            border: none;
            padding: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: .3s;
        }

        .btn-login:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-login:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }

        .error-msg {
            background: rgba(239, 68, 68, .1);
            color: var(--accent-red);
            padding: 11px 12px;
            border-radius: 8px;
            font-size: .85rem;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, .25);
            display: flex;
            gap: .5rem;
            align-items: flex-start;
        }

        .hint {
            background: rgba(59, 130, 246, .09);
            border: 1px solid rgba(59, 130, 246, .25);
            color: #bfdbfe;
            font-size: .78rem;
            padding: 10px 12px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .text-muted { color: #a1a1aa !important; }

        a.link-accent { color: #93c5fd; text-decoration: none; }
        a.link-accent:hover { text-decoration: underline; }
        a:focus-visible { outline: 2px solid #93c5fd; outline-offset: 2px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="login-card">
    <div class="text-center">
        <div class="brand-logo">
            SOUL<span style="color:var(--accent-red)">PRINT</span>
        </div>
        <p class="text-muted small mb-4 text-uppercase fw-bold">
            Photography Studio
        </p>
    </div>

    <div class="segmented" role="radiogroup" aria-label="Account type">
        <input
            type="radio"
            name="as"
            id="asClient"
            value="client"
            <?= $mode === 'client' ? 'checked' : '' ?>
        >
        <label for="asClient">
            <i class="ri-user-3-line"></i> Client
        </label>

        <input
            type="radio"
            name="as"
            id="asAdmin"
            value="admin"
            <?= $mode === 'admin' ? 'checked' : '' ?>
        >
        <label for="asAdmin">
            <i class="ri-briefcase-4-line"></i> Studio
        </label>
    </div>

    <?php if ($error !== ''): ?>
        <div class="error-msg" role="alert">
            <i class="ri-error-warning-line"></i>
            <span><?= login_h($error) ?></span>
        </div>
    <?php endif; ?>

    <form method="POST" novalidate>
        <input type="hidden" name="as" id="asInput" value="<?= login_h($mode) ?>">

        <div class="mb-3">
            <label class="form-label fw-bold text-uppercase" for="usernameInput" id="usernameLabel">
                <?= login_h($usernameLabel) ?>
            </label>
            <div class="input-group">
                <span class="input-group-text bg-transparent">
                    <i class="ri-user-3-line"></i>
                </span>
                <input
                    type="<?= login_h($usernameType) ?>"
                    name="username"
                    id="usernameInput"
                    class="form-control"
                    placeholder="<?= login_h($usernamePlaceholder) ?>"
                    autocomplete="<?= login_h($usernameAutocomplete) ?>"
                    value="<?= login_h($usernameValue) ?>"
                    required
                >
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold text-uppercase" for="passwordInput">
                Password
            </label>
            <div class="input-group">
                <span class="input-group-text bg-transparent">
                    <i class="ri-lock-2-line"></i>
                </span>
                <input
                    type="password"
                    name="password"
                    id="passwordInput"
                    class="form-control"
                    placeholder="••••••••"
                    autocomplete="current-password"
                    required
                >
                <span class="input-group-text bg-transparent" id="togglePassword" role="button" aria-label="Show password">
                    <i class="ri-eye-line" id="eyeIcon"></i>
                </span>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-login text-white">
                Sign in
            </button>
        </div>
    </form>

    <p class="hint mt-4 mb-0" id="modeHint">
        <i class="ri-information-line me-1"></i>
        <span id="modeHintText"></span>
    </p>

    <p class="text-center small text-muted mt-3 mb-0">
        New client?
        <a class="link-accent" href="register.php">Create an account</a>
    </p>
</div>

<script>
(function () {
    var hidden = document.getElementById('asInput');
    var radios = document.querySelectorAll('input[type="radio"][name="as"]');
    var label = document.getElementById('usernameLabel');
    var input = document.getElementById('usernameInput');
    var hint = document.getElementById('modeHintText');

    var copy = {
        client: {
            label: 'Email address',
            placeholder: 'you@example.com',
            type: 'email',
            autocomplete: 'email',
            hint: 'Sign in with the email you booked with to follow your session progress and message the studio.'
        },
        admin: {
            label: 'Studio username',
            placeholder: 'e.g. soulprint',
            type: 'text',
            autocomplete: 'username',
            hint: 'Studio accounts open the management panel: schedule, expenses, liabilities and client messages.'
        }
    };

    function apply(mode) {
        var config = copy[mode] || copy.client;

        hidden.value = mode;
        label.textContent = config.label;
        input.placeholder = config.placeholder;
        input.type = config.type;
        input.setAttribute('autocomplete', config.autocomplete);
        hint.textContent = config.hint;
    }

    Array.prototype.forEach.call(radios, function (radio) {
        radio.addEventListener('change', function () {
            if (radio.checked) {
                apply(radio.value);
            }
        });
    });

    apply(hidden.value || 'client');

    var toggle = document.querySelector('#togglePassword');
    var password = document.querySelector('#passwordInput');
    var eyeIcon = document.querySelector('#eyeIcon');

    toggle.addEventListener('click', function () {
        var next = password.getAttribute('type') === 'password' ? 'text' : 'password';

        password.setAttribute('type', next);
        toggle.setAttribute(
            'aria-label',
            next === 'password' ? 'Show password' : 'Hide password'
        );

        eyeIcon.classList.toggle('ri-eye-line');
        eyeIcon.classList.toggle('ri-eye-off-line');
    });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
