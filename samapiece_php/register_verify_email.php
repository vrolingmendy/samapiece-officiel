<?php
require_once 'functions.php';

if (empty($_SESSION['register_verify_user_id'])) {
    header('Location: ' . samapiece_absolute_url('register.php'), true, 302);
    exit;
}

$user_id = $_SESSION['register_verify_user_id'];

$message = '';
$is_error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend') {
    require_once __DIR__ . '/includes/smtp_mail.php';
    $last = $_SESSION['register_otp_sent_at'] ?? 0;
    if (time() - $last < 60) {
        $message = 'Veuillez patienter une minute avant de redemander un code.';
        $is_error = true;
    } elseif (!smtp_mail_is_configured()) {
        $message = 'Envoi d’email non configuré (.env, config.local.php ou SAMAPIECE_SMTP_PASSWORD).';
        $is_error = true;
    } else {
        $otp = auth_generate_otp();
        auth_set_email_otp($user_id, $otp, 15);
        $user = get_user_by_id($user_id);
        if ($user && auth_send_otp_email_html($user['email'], $otp)) {
            $_SESSION['register_otp_sent_at'] = time();
            $message = 'Un nouveau code vous a été envoyé par e-mail. Il est à nouveau valable 15 minutes.';
        } else {
            $message = 'Échec de l’envoi. Réessayez plus tard.';
            $is_error = true;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $user = get_user_by_id($user_id);
    list($ok, $errmsg) = auth_verify_email_otp($user, $code);
    if (!$ok) {
        $message = $errmsg;
        $is_error = true;
    } else {
        auth_clear_email_otp($user_id);
        $pdo = get_db_connection();
        $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$user_id]);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_email'] = $user['email'];
        unset($_SESSION['register_verify_user_id'], $_SESSION['register_otp_sent_at']);
        SecurityManager::log_security_event('LOGIN_SUCCESS', ['user_id' => $user_id, 'via' => 'register_email_code']);
        // Compte validé : session connectée → profil (tableau de bord onglet profil).
        session_write_close();
        header('Location: ' . samapiece_absolute_url('profil.php'), true, 302);
        exit;
    }
}

$user = get_user_by_id($user_id);
if (!$user || user_registration_type($user) !== 'otp_email' || !empty($user['is_verified'])) {
    unset($_SESSION['register_verify_user_id'], $_SESSION['register_otp_sent_at']);
    header('Location: ' . samapiece_absolute_url('register.php'), true, 302);
    exit;
}

$email_display = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
$otp_expires_ts = 0;
if (!empty($user['email_otp_expires'])) {
    $t = strtotime((string) $user['email_otp_expires']);
    $otp_expires_ts = $t !== false ? $t : 0;
}

$page_title = 'Code reçu par e-mail - Samapiece';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php require __DIR__ . '/includes/head_favicon.php'; ?>
    <style>
        :root {
            --accent: #00b7ff;
            --accent-2: #0ea5e9;
            --dark: #0f172a;
            --muted: #425466;
            --bg: #f9fbff;
            --surface: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
        }
        .container { max-width: 440px; margin: 40px auto; padding: 0 20px; }
        .box {
            background: var(--surface);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        h2 { text-align: center; margin-bottom: 14px; font-size: 1.35rem; }
        .intro {
            text-align: center;
            color: var(--muted);
            font-size: 0.95rem;
            margin-bottom: 20px;
        }
        .intro strong { color: var(--dark); }
        .email-badge {
            display: inline-block;
            margin-top: 8px;
            padding: 8px 14px;
            background: #e0f2fe;
            color: #0369a1;
            border-radius: 8px;
            font-weight: 600;
            word-break: break-all;
        }
        .countdown-card {
            text-align: center;
            padding: 18px 16px;
            margin-bottom: 22px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #bae6fd;
            border-radius: 12px;
        }
        .countdown-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .countdown-value {
            font-family: ui-monospace, 'Cascadia Code', 'Segoe UI Mono', monospace;
            font-size: 2.25rem;
            font-weight: 700;
            color: #0369a1;
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }
        .countdown-value.is-expired { color: #b91c1c; font-size: 1.1rem; }
        .countdown-hint {
            margin-top: 10px;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; }
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 1px solid #e5edf6;
            border-radius: 8px;
            font-size: 1.25rem;
            letter-spacing: 0.3em;
            text-align: center;
        }
        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 0.95rem;
        }
        .message.error { background: #fee2e2; color: #b91c1c; }
        .message.success { background: #d1fae5; color: #047857; }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            background: var(--accent);
            color: #fff;
            margin-bottom: 10px;
        }
        .btn:hover { background: var(--accent-2); }
        .btn-ghost {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
        }
        .links { text-align: center; margin-top: 16px; }
        .links a { color: var(--accent); }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main class="container">
        <div class="box">
            <h2>Vérifiez votre e-mail</h2>
            <p class="intro">
                Un <strong>code à 6 chiffres</strong> vient de vous être envoyé <strong>par e-mail</strong>.<br>
                Consultez votre boîte de réception (et les courriers indésirables) à l’adresse&nbsp;:<br>
                <span class="email-badge"><?php echo $email_display; ?></span>
            </p>
            <p class="intro" style="margin-top:-6px;margin-bottom:14px;">
                Saisissez ce code ci-dessous pour activer votre compte. Une fois le code correct, vous serez redirigé vers <strong>votre profil</strong>.
            </p>

            <div class="countdown-card" id="otp-countdown-root" data-expires="<?php echo (int) $otp_expires_ts; ?>">
                <div class="countdown-label">Validité du code (15 minutes)</div>
                <div class="countdown-value" id="otp-countdown-display" aria-live="polite">—</div>
                <p class="countdown-hint" id="otp-countdown-hint">Le décompte se met à jour automatiquement.</p>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $is_error ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="code">Code reçu par e-mail</label>
                    <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required placeholder="000000">
                </div>
                <button type="submit" class="btn">Valider le code et accéder à mon profil</button>
            </form>

            <form method="POST" style="margin-top:8px;">
                <input type="hidden" name="action" value="resend">
                <button type="submit" class="btn btn-ghost">Renvoyer le code par e-mail</button>
            </form>

            <div class="links">
                <a href="register.php">← Modifier mon inscription</a>
            </div>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
    <script>
    (function() {
        var root = document.getElementById('otp-countdown-root');
        var display = document.getElementById('otp-countdown-display');
        var hint = document.getElementById('otp-countdown-hint');
        if (!root || !display) return;

        var endSec = parseInt(root.getAttribute('data-expires'), 10);
        if (!endSec || endSec <= 0) {
            display.textContent = '—';
            if (hint) hint.textContent = 'Aucune date d’expiration connue. Si besoin, renvoyez un code.';
            return;
        }

        function pad(n) { return n < 10 ? '0' + n : String(n); }

        function tick() {
            var now = Math.floor(Date.now() / 1000);
            var left = endSec - now;
            if (left <= 0) {
                display.textContent = '00:00';
                display.classList.add('is-expired');
                if (hint) {
                    hint.innerHTML = 'Ce code a expiré. Utilisez <strong>Renvoyer le code par e-mail</strong> pour recevoir un nouveau code (encore valable 15 minutes).';
                }
                return;
            }
            var m = Math.floor(left / 60);
            var s = left % 60;
            display.textContent = pad(m) + ':' + pad(s);
            if (m >= 60) {
                var h = Math.floor(m / 60);
                m = m % 60;
                display.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
            }
        }

        tick();
        setInterval(tick, 1000);
    })();
    </script>
</body>
</html>
