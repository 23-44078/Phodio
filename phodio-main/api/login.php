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
    header('Location: /admin/dashboard.php');
    exit;
}

if (phodio_current_client_id($pdo) !== null) {
    header('Location: /client_dashboard.php');
    exit;
}

$mode = strtolower(trim((string) ($_GET['as'] ?? 'client')));

if (!in_array($mode, ['client', 'admin'], true)) {
    $mode = 'client';
}

/*
 * Brute-force throttle.
 *
 * A wrong password costs an attacker the same either way, but ten wrong
 * guesses in ten minutes is not a person mistyping. The counter lives in the
 * session, so it only ever slows down that one browser and never locks an
 * account out permanently.
 */
$attemptWindow = 600;   // seconds
$attemptLimit  = 10;    // failed attempts allowed inside the window

$attempts = $_SESSION['phodio_login_attempts'] ?? [];

if (!is_array($attempts)) {
    $attempts = [];
}

$cutoff = time() - $attemptWindow;

$attempts = array_values(
    array_filter(
        $attempts,
        static function ($stamp): bool {
            return is_int($stamp);
        }
    )
);

$attempts = array_values(
    array_filter(
        $attempts,
        static function (int $stamp) use ($cutoff): bool {
            return $stamp > $cutoff;
        }
    )
);

$_SESSION['phodio_login_attempts'] = $attempts;

$throttled = count($attempts) >= $attemptLimit;

$error = '';
$usernameValue = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $usernameValue = trim((string) ($_POST['username'] ?? ''));
    $postedMode = strtolower(trim((string) ($_POST['as'] ?? $mode)));

    if (in_array($postedMode, ['client', 'admin'], true)) {
        $mode = $postedMode;
    }

    if ($throttled) {
        $error = 'Too many sign-in attempts from this browser. Wait a few '
            . 'minutes and try again.';
    } else {
        $result = phodio_unified_login(
            $pdo,
            $usernameValue,
            (string) ($_POST['password'] ?? '')
        );

        if ($result['ok']) {
            $_SESSION['phodio_login_attempts'] = [];

            header(
                'Location: ' . (
                    $result['type'] === 'admin'
                        ? '/admin/dashboard.php'
                        : '/client_dashboard.php'
                )
            );
            exit;
        }

        $attempts[] = time();
        $_SESSION['phodio_login_attempts'] = $attempts;

        $error = $result['error'] ?? 'Sign-in failed. Please try again.';

        if (count($attempts) >= $attemptLimit) {
            $throttled = true;
            $error = 'Too many sign-in attempts from this browser. Wait a few '
                . 'minutes and try again.';
        }
    }
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Sign in | SOULPRINT</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --bg: #0b0d12;
            --panel: #141a25;
            --panel-soft: #1a2130;
            --line: #272f3f;
            --line-soft: #1e2635;
            --text: #e9edf5;
            --muted: #98a3b7;
            --red: #ef4444;
            --blue: #3b82f6;
            --radius: 18px;
        }

        * { box-sizing: border-box; }

        html, body { min-height: 100%; }

        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: #93c5fd; }

        .auth {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
            gap: 28px;
            padding: 32px 20px 40px;
            max-width: 1080px;
            margin: 0 auto;
            align-content: center;
        }

        /* ------------------------------------------------------------------
           Left: brand panel
        ------------------------------------------------------------------ */

        .intro {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--line-soft);
            border-radius: var(--radius);
            background:
                radial-gradient(120% 90% at 12% 0%, rgba(239, 68, 68, .22), transparent 55%),
                radial-gradient(90% 80% at 100% 100%, rgba(59, 130, 246, .18), transparent 60%),
                #10141d;
            padding: 34px 30px;
        }

        .intro::after {
            content: "";
            position: absolute;
            inset: auto -20% -60% 40%;
            height: 320px;
            background: radial-gradient(closest-side, rgba(239, 68, 68, .16), transparent);
            pointer-events: none;
        }

        .brand {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: .22em;
            text-transform: uppercase;
        }

        .brand span { color: var(--red); }

        .intro h1 {
            font-size: clamp(1.6rem, 3.6vw, 2.15rem);
            line-height: 1.15;
            font-weight: 800;
            letter-spacing: -.02em;
            margin: 26px 0 12px;
        }

        .intro p.lede {
            color: var(--muted);
            margin: 0 0 26px;
            line-height: 1.7;
            max-width: 34ch;
        }

        .points {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            gap: 14px;
            position: relative;
        }

        .points li {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: .93rem;
            color: #cbd4e3;
            line-height: 1.55;
        }

        .points i {
            flex: 0 0 auto;
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, .05);
            border: 1px solid var(--line);
            color: var(--red);
            font-size: 1.05rem;
        }

        .points strong { color: #fff; font-weight: 600; }

        /* ------------------------------------------------------------------
           Right: form panel
        ------------------------------------------------------------------ */

        .panel {
            display: flex;
            align-items: center;
        }

        .card {
            width: 100%;
            background: linear-gradient(180deg, var(--panel), #111621);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 30px 28px;
            box-shadow: 0 26px 60px rgba(0, 0, 0, .5);
        }

        .card-brand {
            display: none;
            margin-bottom: 18px;
        }

        .card h2 {
            font-size: 1.28rem;
            font-weight: 700;
            letter-spacing: -.01em;
            margin: 0 0 6px;
        }

        .card .sub {
            color: var(--muted);
            font-size: .92rem;
            margin: 0 0 22px;
        }

        .segmented {
            position: relative;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4px;
            background: #0e131c;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 5px;
            margin-bottom: 20px;
        }

        .segmented input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .segmented label {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            padding: 10px 8px;
            border-radius: 9px;
            font-size: .76rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
            color: var(--muted);
            cursor: pointer;
            transition: background .2s ease, color .2s ease;
        }

        .segmented label:hover { color: #dbe3f0; }

        .segmented input:checked + label {
            background: linear-gradient(180deg, #2f6fe0, #2563eb);
            color: #fff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, .3);
        }

        .segmented input:focus-visible + label {
            outline: 2px solid #93c5fd;
            outline-offset: 2px;
        }

        .field { margin-bottom: 16px; }

        .field label {
            display: block;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #b6c0d1;
            margin-bottom: 8px;
        }

        .control {
            position: relative;
            display: flex;
            align-items: center;
        }

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
            font-size: .95rem;
            padding: 13px 44px 13px 42px;
            transition: border-color .18s ease, box-shadow .18s ease;
        }

        .control input::placeholder { color: #6b758a; }

        .control input:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, .22);
        }

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

        .submit {
            width: 100%;
            margin-top: 6px;
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
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }

        .submit:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .submit:active { transform: translateY(0); }
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
            background: rgba(239, 68, 68, .1);
            border: 1px solid rgba(239, 68, 68, .28);
            color: #fca5a5;
            border-radius: 12px;
            padding: 12px 13px;
            font-size: .86rem;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .alert i { font-size: 1.05rem; }

        .hint {
            background: rgba(59, 130, 246, .08);
            border: 1px solid rgba(59, 130, 246, .22);
            color: #bfdbfe;
            border-radius: 12px;
            padding: 11px 13px;
            font-size: .8rem;
            line-height: 1.55;
            margin: 18px 0 0;
        }

        .switch {
            text-align: center;
            color: var(--muted);
            font-size: .88rem;
            margin: 20px 0 0;
        }

        .switch a { text-decoration: none; font-weight: 600; }
        .switch a:hover { text-decoration: underline; }

        a:focus-visible { outline: 2px solid #93c5fd; outline-offset: 2px; border-radius: 4px; }

        @media (min-width: 992px) {
            .auth {
                grid-template-columns: 1.05fr .95fr;
                gap: 34px;
                padding: 48px 32px;
                align-items: center;
            }

            .intro { padding: 42px 38px; }
        }

        @media (max-width: 991px) {
            .card-brand { display: block; }
            .intro h1 { margin-top: 20px; }
            .intro p.lede { margin-bottom: 20px; }
        }

        @media (prefers-reduced-motion: reduce) {
            * { animation: none !important; transition: none !important; }
        }
    </style>
</head>
<body>

<main class="auth">

    <section class="intro">
        <div class="brand">SOUL<span>PRINT</span></div>

        <h1>Your moments,<br>kept in focus.</h1>

        <p class="lede">
            One account for everything SoulPrint — from the moment you book a
            session to the day your photos are ready.
        </p>

        <ul class="points">
            <li>
                <i class="ri-calendar-check-line"></i>
                <span><strong>Follow your booking.</strong> See the schedule,
                    the package you chose and every progress update.</span>
            </li>
            <li>
                <i class="ri-chat-3-line"></i>
                <span><strong>Message the studio.</strong> Ask a question on
                    the booking itself — no separate thread to lose.</span>
            </li>
            <li>
                <i class="ri-briefcase-4-line"></i>
                <span><strong>Run the studio.</strong> Staff accounts open the
                    bookings, expenses and client messages panel.</span>
            </li>
        </ul>
    </section>

    <section class="panel">

        <div class="card">

            <div class="brand card-brand">SOUL<span>PRINT</span></div>

            <h2>Welcome back</h2>
            <p class="sub">Sign in to continue to your dashboard.</p>

            <div class="segmented" role="radiogroup" aria-label="Account type">
                <input type="radio" name="as" id="asClient" value="client"
                    <?= $mode === 'client' ? 'checked' : '' ?>>
                <label for="asClient"><i class="ri-user-3-line"></i> Client</label>

                <input type="radio" name="as" id="asAdmin" value="admin"
                    <?= $mode === 'admin' ? 'checked' : '' ?>>
                <label for="asAdmin"><i class="ri-briefcase-4-line"></i> Studio</label>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert" role="alert">
                    <i class="ri-error-warning-line"></i>
                    <span><?= login_h($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm" novalidate>
                <input type="hidden" name="as" id="asInput" value="<?= login_h($mode) ?>">

                <div class="field">
                    <label for="usernameInput" id="usernameLabel"><?= login_h($usernameLabel) ?></label>
                    <div class="control">
                        <i class="ri-user-3-line lead"></i>
                        <input
                            type="<?= login_h($usernameType) ?>"
                            name="username"
                            id="usernameInput"
                            placeholder="<?= login_h($usernamePlaceholder) ?>"
                            autocomplete="<?= login_h($usernameAutocomplete) ?>"
                            value="<?= login_h($usernameValue) ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="field">
                    <label for="passwordInput">Password</label>
                    <div class="control">
                        <i class="ri-lock-2-line lead"></i>
                        <input
                            type="password"
                            name="password"
                            id="passwordInput"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="trail" id="togglePassword"
                                aria-label="Show password" aria-controls="passwordInput">
                            <i class="ri-eye-line" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="submit" id="submitButton">
                    <span class="spinner" aria-hidden="true"></span>
                    <span id="submitLabel">Sign in</span>
                </button>
            </form>

            <p class="hint" id="modeHint">
                <i class="ri-information-line"></i>
                <span id="modeHintText"></span>
            </p>

            <p class="switch">
                New client?
                <a href="register.php">Create an account</a>
            </p>

        </div>

    </section>

</main>

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

    /*
     * Show / hide password.
     */
    var toggle = document.getElementById('togglePassword');
    var password = document.getElementById('passwordInput');
    var eyeIcon = document.getElementById('eyeIcon');

    toggle.addEventListener('click', function () {
        var next = password.getAttribute('type') === 'password' ? 'text' : 'password';

        password.setAttribute('type', next);
        toggle.setAttribute('aria-label', next === 'password' ? 'Show password' : 'Hide password');

        eyeIcon.classList.toggle('ri-eye-line');
        eyeIcon.classList.toggle('ri-eye-off-line');
    });

    /*
     * Give the button a busy state so a double-click cannot fire two posts.
     */
    var form = document.getElementById('loginForm');
    var button = document.getElementById('submitButton');

    form.addEventListener('submit', function () {
        button.classList.add('is-loading');
        button.disabled = true;
        document.getElementById('submitLabel').textContent = 'Signing in…';
    });
})();
</script>

</body>
</html>
