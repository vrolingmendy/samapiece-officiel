<?php
require_once 'functions.php';

// Déjà connecté : accès direct au profil (évite de proposer « Créer un compte » à tort).
if (is_logged_in()) {
    header('Location: ' . samapiece_absolute_url('profil.php'), true, 302);
    exit;
}

$message = '';
$tab = $_POST['tab'] ?? $_GET['tab'] ?? 'email';
if ($tab !== 'phone') {
    $tab = 'email';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['login_action'] ?? '';

    if ($action === 'cancel_flow') {
        unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
        header('Location: ' . samapiece_absolute_url('login.php?tab=email'), true, 302);
        exit;
    }

    if ($action === 'email_resend') {
        $uid = trim((string) ($_SESSION['login_uid'] ?? ''));
        $kind = $_SESSION['login_kind'] ?? '';
        $email = trim((string) ($_SESSION['login_email'] ?? ''));
        if ($uid === '' || $kind !== 'otp') {
            $uid = trim((string) ($_POST['otp_user_id'] ?? ''));
            $email = trim((string) ($_POST['otp_login_email'] ?? ''));
        }
        if ($uid === '' || $email === '' || !SecurityManager::validate_email($email)) {
            $message = 'Impossible de renvoyer le code. Recommencez.';
            unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email']);
        } else {
            require_once __DIR__ . '/includes/smtp_mail.php';
            $last = $_SESSION['login_otp_sent_at'][$email] ?? 0;
            if (time() - $last < 60) {
                $message = 'Veuillez patienter une minute avant un nouvel envoi.';
            } elseif (!smtp_mail_is_configured()) {
                $message = 'Envoi d’email non configuré.';
            } else {
                $user = get_user_by_id($uid);
                if (!$user || trim((string) ($user['email'] ?? '')) !== $email || user_registration_type($user) !== 'otp_email') {
                    $message = 'Session invalide.';
                    unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email']);
                } else {
                    $_SESSION['login_uid'] = $uid;
                    $_SESSION['login_kind'] = 'otp';
                    $_SESSION['login_email'] = $email;
                    $otp = auth_generate_otp();
                    auth_set_email_otp($uid, $otp, 15);
                    if (auth_send_otp_email_html($email, $otp)) {
                        $_SESSION['login_otp_sent_at'][$email] = time();
                        $message = 'Un nouveau code vous a été envoyé.';
                    } else {
                        $message = 'Échec de l’envoi. Réessayez plus tard.';
                    }
                }
            }
        }
    } elseif ($action === 'email_start') {
        if (empty($_POST['accept_privacy'])) {
            $message = 'Vous devez accepter la politique de confidentialité pour vous connecter.';
        } else {
        $email_in = SecurityManager::sanitize_input($_POST['email'] ?? '');
        $same_otp = ($_SESSION['login_kind'] ?? '') === 'otp'
            && trim((string) ($_SESSION['login_email'] ?? '')) === trim($email_in);
        if (!$same_otp) {
            unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
        }
        $email = $email_in;
        if ($email === '' || !SecurityManager::validate_email($email)) {
            $message = 'Adresse email invalide.';
        } else {
            $user = get_user_by_email($email);
            if (!$user) {
                $message = 'Aucun compte ne correspond à cet email.';
            } else {
                $rt = user_registration_type($user);
                if ($rt === 'otp_email') {
                    require_once __DIR__ . '/includes/smtp_mail.php';
                    if (!smtp_mail_is_configured()) {
                        $message = 'Connexion par code indisponible : SMTP non configuré (.env ou variables d’environnement).';
                    } else {
                        $last = $_SESSION['login_otp_sent_at'][$email] ?? 0;
                        if (time() - $last < 60) {
                            $message = 'Veuillez patienter une minute avant un nouvel envoi de code.';
                            if ($same_otp) {
                                $_SESSION['login_uid'] = $user['id'];
                                $_SESSION['login_kind'] = 'otp';
                                $_SESSION['login_email'] = $email;
                                $_SESSION['login_privacy_accepted'] = true;
                            }
                        } else {
                            $otp = auth_generate_otp();
                            auth_set_email_otp($user['id'], $otp, 15);
                            if (auth_send_otp_email_html($email, $otp)) {
                                $_SESSION['login_otp_sent_at'][$email] = time();
                                $_SESSION['login_uid'] = $user['id'];
                                $_SESSION['login_kind'] = 'otp';
                                $_SESSION['login_email'] = $email;
                                $_SESSION['login_privacy_accepted'] = true;
                                $message = 'Un code de connexion vous a été envoyé par email.';
                            } else {
                                $message = 'Impossible d’envoyer le code. Réessayez plus tard.';
                            }
                        }
                    }
                } elseif ($rt === 'password_email') {
                    $_SESSION['login_uid'] = $user['id'];
                    $_SESSION['login_kind'] = 'password';
                    $_SESSION['login_email'] = $email;
                    $_SESSION['login_privacy_accepted'] = true;
                } else {
                    $message = 'Ce compte utilise la connexion par téléphone et mot de passe.';
                }
            }
        }
        }
    } elseif ($action === 'email_otp') {
        // Session parfois absente (cookie, onglet) : reprendre uid/e-mail du formulaire et vérifier en base.
        $uid = trim((string) ($_SESSION['login_uid'] ?? ''));
        $email = trim((string) ($_SESSION['login_email'] ?? ''));
        $kind = $_SESSION['login_kind'] ?? '';
        if ($uid === '' || $kind !== 'otp') {
            $uid = trim((string) ($_POST['otp_user_id'] ?? ''));
            $email = trim((string) ($_POST['otp_login_email'] ?? ''));
        }
        if ($uid === '' || $email === '' || !SecurityManager::validate_email($email)) {
            $message = 'Session expirée. Recommencez depuis l’étape email.';
            unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
        } else {
            $user = get_user_by_id($uid);
            if (!$user || trim((string) ($user['email'] ?? '')) !== $email || user_registration_type($user) !== 'otp_email') {
                $message = 'Session invalide.';
                unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
            } else {
                $_SESSION['login_uid'] = $uid;
                $_SESSION['login_kind'] = 'otp';
                $_SESSION['login_email'] = $email;
                if (empty($_SESSION['login_privacy_accepted'])) {
                    $message = 'Vous devez accepter la politique de confidentialité. Recommencez depuis l’étape email.';
                    unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
                } else {
                list($can_login, $attempts) = SecurityManager::check_login_attempts($email);
                if (!$can_login) {
                    $message = 'Compte temporairement bloqué. Réessayez plus tard.';
                } else {
                    list($ok, $errmsg) = auth_verify_email_otp($user, $_POST['code'] ?? '');
                    if (!$ok) {
                        $message = $errmsg;
                        SecurityManager::record_failed_attempt($email);
                    } else {
                        auth_clear_email_otp($uid);
                        $pdo = get_db_connection();
                        $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$uid]);
                        SecurityManager::reset_login_attempts($email);
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $uid;
                        $_SESSION['user_email'] = $user['email'];
                        unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
                        SecurityManager::log_security_event('LOGIN_SUCCESS', ['user_id' => $uid, 'via' => 'email_otp']);
                        session_write_close();
                        header('Location: ' . samapiece_absolute_url('profil.php'), true, 302);
                        exit;
                    }
                }
                }
            }
        }
    } elseif ($action === 'email_password') {
        $email = SecurityManager::sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $uid = $_SESSION['login_uid'] ?? '';
        $kind = $_SESSION['login_kind'] ?? '';
        if ($kind !== 'password' || $uid === '') {
            $message = 'Recommencez en indiquant votre email.';
            unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
        } elseif (empty($_SESSION['login_privacy_accepted'])) {
            $message = 'Vous devez accepter la politique de confidentialité. Recommencez depuis l’étape email.';
            unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
        } elseif ($email === '') {
            $message = 'Email requis.';
        } else {
            $user = get_user_by_email($email);
            if (!$user || (string) $user['id'] !== (string) $uid) {
                $message = 'Email ou mot de passe incorrect.';
                SecurityManager::record_failed_attempt($email);
            } else {
                list($can_login, $attempts) = SecurityManager::check_login_attempts($email);
                if (!$can_login) {
                    $message = 'Compte temporairement bloqué. Réessayez plus tard.';
                } elseif (!SecurityManager::verify_password($password, $user['password_hash'] ?? '')) {
                    $message = 'Email ou mot de passe incorrect.';
                    SecurityManager::record_failed_attempt($email);
                } elseif (EMAIL_VERIFICATION_REQUIRED && empty($user['is_verified'])) {
                    $message = 'Veuillez confirmer votre compte (email) avant de vous connecter.';
                } else {
                    SecurityManager::reset_login_attempts($email);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    unset($_SESSION['login_uid'], $_SESSION['login_kind'], $_SESSION['login_email'], $_SESSION['login_privacy_accepted']);
                    SecurityManager::log_security_event('LOGIN_SUCCESS', ['user_id' => $user['id']]);
                    session_write_close();
                    header('Location: ' . samapiece_absolute_url('profil.php'), true, 302);
                    exit;
                }
            }
        }
    } elseif ($action === 'phone_login') {
        if (empty($_POST['accept_privacy'])) {
            $message = 'Vous devez accepter la politique de confidentialité pour vous connecter.';
        } else {
        $code_pays = SecurityManager::sanitize_input($_POST['code_pays'] ?? '+221');
        $telephone = SecurityManager::sanitize_input($_POST['telephone'] ?? '');
        $password = $_POST['password'] ?? '';
        $full = normalize_full_phone($code_pays, $telephone);
        if ($full === '' || strlen(preg_replace('/\D/', '', $telephone)) < 6) {
            $message = 'Numéro de téléphone invalide.';
        } elseif ($password === '') {
            $message = 'Mot de passe requis.';
        } else {
            $user = get_user_by_telephone($full);
            if (!$user || !user_can_login_with_phone($user)) {
                $message = 'Numéro ou mot de passe incorrect.';
                SecurityManager::record_failed_attempt($full);
            } else {
                list($can_login, $attempts) = SecurityManager::check_login_attempts($full);
                if (!$can_login) {
                    $message = 'Compte temporairement bloqué. Réessayez plus tard.';
                } elseif (!SecurityManager::verify_password($password, $user['password_hash'] ?? '')) {
                    $message = 'Numéro ou mot de passe incorrect.';
                    SecurityManager::record_failed_attempt($full);
                } else {
                    SecurityManager::reset_login_attempts($full);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'] ?? '';
                    SecurityManager::log_security_event('LOGIN_SUCCESS', ['user_id' => $user['id'], 'via' => 'phone']);
                    session_write_close();
                    header('Location: ' . samapiece_absolute_url('profil.php'), true, 302);
                    exit;
                }
            }
        }
        }
    }
}

$login_uid = $_SESSION['login_uid'] ?? '';
$login_kind = $_SESSION['login_kind'] ?? null;
$login_email = $_SESSION['login_email'] ?? '';

$page_title = 'Connexion - Samapiece';
$message_class = 'error';
if ($message !== '' && (stripos($message, 'envoyé') !== false || stripos($message, 'Un code') !== false || stripos($message, 'patienter') !== false)) {
    $message_class = 'info';
}

require_once __DIR__ . '/includes/phone_country_select.php';
$posted_code_login = $_POST['code_pays'] ?? '+221';
$privacy_href = htmlspecialchars(samapiece_privacy_policy_url(), ENT_QUOTES, 'UTF-8');
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
        .container { max-width: 420px; margin: 40px auto; padding: 0 20px; }
        .login-form {
            background: var(--surface);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .login-form h2 { text-align: center; margin-bottom: 16px; color: var(--dark); }
        .tabs { display: flex; gap: 8px; margin-bottom: 20px; }
        .tabs a, .tabs span {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            border: 1px solid #e5edf6;
        }
        .tabs a { color: var(--muted); background: #f8fafc; }
        .tabs a.active { background: var(--accent); color: #fff; border-color: var(--accent); }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--dark); }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #e5edf6; border-radius: 6px; font-size: 16px; }
        .form-group input:focus { outline: none; border-color: var(--accent); }
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 18px;
            text-align: center;
            font-size: 0.95rem;
        }
        .message.error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .message.info { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .button-group { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .btn {
            flex: 1;
            min-width: 120px;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        .btn-primary { background: var(--accent); color: white; }
        .btn-primary:hover { background: var(--accent-2); }
        .form-footer {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5edf6;
            text-align: center;
        }
        .form-footer a { color: var(--accent); text-decoration: none; font-weight: 500; }
        .muted { font-size: 0.88rem; color: var(--muted); margin-top: 12px; text-align: center; }
        .input-readonly {
            background: #f1f5f9;
            color: var(--dark);
            cursor: default;
        }
        .login-next-step {
            margin-top: 8px;
            padding-top: 18px;
            border-top: 1px solid #e5edf6;
        }
        .login-next-step .step-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 12px;
        }
        .link-btn {
            background: none;
            border: none;
            color: var(--accent);
            cursor: pointer;
            text-decoration: underline;
            font-size: 0.9rem;
            width: 100%;
            margin-top: 8px;
        }
        .form-group--privacy {
            margin-bottom: 16px;
        }
        .privacy-check {
            display: flex;
            gap: 10px;
            align-items: flex-start;
            font-size: 0.88rem;
            color: var(--muted);
            line-height: 1.45;
            cursor: pointer;
        }
        .privacy-check input {
            width: auto;
            margin-top: 4px;
            flex-shrink: 0;
        }
        .privacy-check a {
            color: var(--accent);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main class="container">
        <div class="login-form">
            <h2>Connexion</h2>

            <div class="tabs">
                <a href="login.php?tab=email" class="<?php echo $tab === 'email' ? 'active' : ''; ?>">Email</a>
                <a href="login.php?tab=phone" class="<?php echo $tab === 'phone' ? 'active' : ''; ?>">Téléphone</a>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($message_class); ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'email'): ?>
                <?php if ($login_kind === 'otp'): ?>
                    <form method="POST">
                        <input type="hidden" name="login_action" value="email_otp">
                        <input type="hidden" name="tab" value="email">
                        <input type="hidden" name="otp_user_id" value="<?php echo htmlspecialchars((string) $login_uid, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="otp_login_email" value="<?php echo htmlspecialchars((string) $login_email, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="email_otp_show">Email</label>
                            <input type="email" id="email_otp_show" class="input-readonly" readonly autocomplete="username" value="<?php echo htmlspecialchars($login_email); ?>" aria-readonly="true">
                        </div>
                        <div class="login-next-step">
                            <div class="step-label">Code reçu par e-mail</div>
                            <div class="form-group">
                                <label for="code">Saisissez les 6 chiffres</label>
                                <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="000000">
                            </div>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">Valider et accéder à mon profil</button>
                        </div>
                    </form>
                    <form method="POST" style="margin-top:6px;">
                        <input type="hidden" name="login_action" value="email_resend">
                        <input type="hidden" name="tab" value="email">
                        <input type="hidden" name="otp_user_id" value="<?php echo htmlspecialchars((string) $login_uid, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="otp_login_email" value="<?php echo htmlspecialchars((string) $login_email, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="link-btn">Renvoyer le code par e-mail</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="login_action" value="cancel_flow">
                        <button type="submit" class="link-btn">Changer d’adresse e-mail</button>
                    </form>
                <?php elseif ($login_kind === 'password'): ?>
                    <form method="POST">
                        <input type="hidden" name="login_action" value="email_password">
                        <input type="hidden" name="tab" value="email">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($login_email); ?>" autocomplete="username">
                        </div>
                        <div class="login-next-step">
                            <div class="step-label">Mot de passe</div>
                            <div class="form-group">
                                <label for="password">Votre mot de passe</label>
                                <input type="password" id="password" name="password" required autocomplete="current-password">
                            </div>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">Connexion</button>
                        </div>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="login_action" value="cancel_flow">
                        <button type="submit" class="link-btn">Changer d’adresse e-mail</button>
                    </form>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="login_action" value="email_start">
                        <input type="hidden" name="tab" value="email">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="username">
                        </div>
                        <div class="form-group form-group--privacy">
                            <label class="privacy-check">
                                <input type="checkbox" name="accept_privacy" value="1" required>
                                <span>J’ai lu et j’accepte la <a href="<?php echo $privacy_href; ?>" target="_blank" rel="noopener noreferrer">politique de confidentialité</a>.</span>
                            </label>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">Connexion</button>
                        </div>
                    </form>
                <?php endif; ?>

            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="login_action" value="phone_login">
                    <input type="hidden" name="tab" value="phone">
                    <div class="form-row-phone">
                        <div class="form-group form-group--dial">
                            <label for="code_pays">Indicatif</label>
                            <?php render_phone_country_select('code_pays', 'code_pays', $posted_code_login); ?>
                        </div>
                        <div class="form-group form-group--num">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" required value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pw">Mot de passe</label>
                        <input type="password" id="pw" name="password" required autocomplete="current-password">
                    </div>
                    <div class="form-group form-group--privacy">
                        <label class="privacy-check">
                            <input type="checkbox" name="accept_privacy" value="1" required>
                            <span>J’ai lu et j’accepte la <a href="<?php echo $privacy_href; ?>" target="_blank" rel="noopener noreferrer">politique de confidentialité</a>.</span>
                        </label>
                    </div>
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">Connexion</button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="form-footer">
                <a href="<?php echo htmlspecialchars(samapiece_url('forgot_password.php'), ENT_QUOTES, 'UTF-8'); ?>">Mot de passe oublié (compte téléphone)</a>
                <p class="muted" style="margin-top:14px;">
                    Pas encore de compte ?
                    <a href="<?php echo htmlspecialchars(samapiece_url('register.php'), ENT_QUOTES, 'UTF-8'); ?>">Créer un compte</a>
                </p>
            </div>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
