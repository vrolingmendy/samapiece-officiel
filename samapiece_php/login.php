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
                    if (auth_send_otp_email_html($email, $otp, $user['prenom'] ?? '', $user['nom'] ?? '', 'login')) {
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
                            if (auth_send_otp_email_html($email, $otp, $user['prenom'] ?? '', $user['nom'] ?? '', 'login')) {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body:has(.login-page) main {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-top: clamp(8px, 2vw, 20px);
        }
        .login-page {
            --lp-accent: #128c7e;
            --lp-accent-2: #0ea5e9;
            --lp-violet: #7c3aed;
            --lp-dark: #0f172a;
            --lp-muted: #64748b;
            --lp-line: #e5e7eb;
            position: relative;
            flex: 1 0 auto;
            width: 100%;
            min-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(24px, 5vw, 48px) max(16px, env(safe-area-inset-left)) clamp(32px, 6vw, 64px) max(16px, env(safe-area-inset-right));
            font-family: 'Outfit', system-ui, sans-serif;
            color: var(--lp-dark);
        }
        .login-page__container {
            position: relative;
            width: 100%;
            max-width: 440px;
            margin: 0 auto;
        }
        .login-form.login-card {
            position: relative;
            background: #ffffff;
            border: 1px solid var(--lp-line);
            border-radius: 24px;
            padding: clamp(28px, 5vw, 40px);
            box-shadow:
                0 1px 3px rgba(15, 23, 42, 0.06),
                0 12px 40px rgba(15, 23, 42, 0.08);
        }
        .login-card__brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .login-card__mark {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--lp-accent) 0%, var(--lp-accent-2) 100%);
            display: grid;
            place-items: center;
            color: #fff;
            font-size: 1.35rem;
            box-shadow: 0 8px 24px rgba(18, 140, 126, 0.35);
        }
        .login-form h2 {
            text-align: center;
            font-size: clamp(1.45rem, 4vw, 1.65rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
            color: var(--lp-dark);
        }
        .login-card__lead {
            text-align: center;
            font-size: 0.92rem;
            color: var(--lp-muted);
            font-weight: 500;
            margin-bottom: 24px;
            line-height: 1.45;
        }
        .tabs {
            display: flex;
            gap: 6px;
            padding: 5px;
            margin-bottom: 24px;
            background: rgba(15, 23, 42, 0.06);
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }
        .tabs a, .tabs span {
            flex: 1;
            text-align: center;
            padding: 11px 12px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            border: none;
            transition: color 0.2s, background 0.25s, box-shadow 0.25s;
        }
        .tabs a {
            color: var(--lp-muted);
            background: transparent;
        }
        .tabs a:hover:not(.active) {
            color: var(--lp-dark);
            background: rgba(255, 255, 255, 0.5);
        }
        .tabs a.active {
            background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
            color: var(--lp-dark);
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.1), 0 0 0 1px rgba(15, 23, 42, 0.04);
        }
        .tabs a .tab-icon {
            margin-right: 6px;
            opacity: 0.92;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            color: #334155;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--lp-line);
            border-radius: 12px;
            font-size: 16px;
            font-family: inherit;
            background: rgba(255, 255, 255, 0.85);
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: rgba(18, 140, 126, 0.55);
            box-shadow: 0 0 0 3px rgba(18, 140, 126, 0.15);
            background: #fff;
        }
        .form-group input#code {
            font-size: 1.35rem;
            letter-spacing: 0.35em;
            text-align: center;
            font-variant-numeric: tabular-nums;
            padding-left: 1rem;
            padding-right: 1rem;
        }
        .form-row { display: flex; gap: 10px; }
        .form-row .form-group { flex: 1; }
        .form-row-phone {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .form-row-phone .form-group--dial {
            flex: 0 0 min(46%, 168px);
            min-width: 140px;
        }
        .form-row-phone .form-group--num {
            flex: 1 1 160px;
            min-width: 0;
        }
        .password-field-wrap .password-field {
            position: relative;
        }
        .password-field-wrap .password-field input {
            padding-right: 48px;
        }
        .password-field__toggle {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            margin: 0;
            padding: 0;
            border: none;
            border-radius: 10px;
            background: transparent;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s, background 0.2s;
        }
        .password-field__toggle:hover {
            color: var(--lp-accent);
            background: rgba(18, 140, 126, 0.1);
        }
        .password-field__toggle:focus {
            outline: none;
        }
        .password-field__toggle:focus-visible {
            outline: 2px solid var(--lp-accent);
            outline-offset: 2px;
        }
        .password-field__icon {
            flex-shrink: 0;
            pointer-events: none;
        }
        .password-field__icon[hidden],
        .password-field__icon.is-hidden {
            display: none !important;
        }
        .login-page .phone-country-select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 36px;
            max-height: 200px;
        }
        .message {
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.92rem;
            font-weight: 500;
            line-height: 1.45;
            border: 1px solid transparent;
        }
        .message.error {
            background: linear-gradient(135deg, rgba(254, 226, 226, 0.95), rgba(255, 241, 242, 0.95));
            color: #b91c1c;
            border-color: rgba(248, 113, 113, 0.25);
        }
        .message.info {
            background: linear-gradient(135deg, rgba(224, 242, 254, 0.95), rgba(236, 254, 255, 0.95));
            color: #0369a1;
            border-color: rgba(56, 189, 248, 0.3);
        }
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 22px;
            flex-wrap: wrap;
        }
        .btn {
            flex: 1;
            min-width: 120px;
            padding: 14px 18px;
            border: none;
            border-radius: 14px;
            font-size: 0.98rem;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.25s, filter 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--lp-accent) 0%, #0e6b62 45%, var(--lp-accent-2) 100%);
            background-size: 200% 100%;
            color: #fff;
            box-shadow: 0 4px 16px rgba(18, 140, 126, 0.35), 0 12px 32px rgba(15, 23, 42, 0.12);
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(18, 140, 126, 0.4), 0 16px 40px rgba(15, 23, 42, 0.15);
            filter: brightness(1.03);
        }
        .btn-primary:active {
            transform: translateY(0);
        }
        .form-footer {
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid var(--lp-line);
            text-align: center;
        }
        .form-footer > a:first-child {
            display: inline-block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--lp-accent);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s, color 0.2s;
        }
        .form-footer > a:first-child:hover {
            border-bottom-color: var(--lp-accent);
        }
        .form-footer__signup {
            margin-top: 14px;
        }
        .muted {
            font-size: 0.88rem;
            color: var(--lp-muted);
            margin-top: 14px;
            text-align: center;
            line-height: 1.5;
        }
        .muted a {
            color: var(--lp-violet);
            font-weight: 600;
            text-decoration: none;
        }
        .muted a:hover {
            text-decoration: underline;
        }
        .input-readonly {
            background: rgba(241, 245, 249, 0.95) !important;
            color: var(--lp-dark);
            cursor: default;
            border-style: dashed !important;
        }
        .login-next-step {
            margin-top: 10px;
            padding-top: 20px;
            border-top: 1px dashed var(--lp-line);
        }
        .login-next-step .step-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--lp-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 14px;
        }
        .link-btn {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid var(--lp-line);
            color: #0e6b62;
            cursor: pointer;
            font-size: 0.88rem;
            font-weight: 600;
            font-family: inherit;
            width: 100%;
            margin-top: 10px;
            padding: 10px 14px;
            border-radius: 12px;
            transition: background 0.2s, border-color 0.2s;
        }
        .link-btn:hover {
            background: rgba(255, 255, 255, 0.9);
            border-color: rgba(18, 140, 126, 0.35);
        }
        .form-group--privacy { margin-bottom: 16px; }
        .privacy-check {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            font-size: 0.86rem;
            color: var(--lp-muted);
            line-height: 1.5;
            cursor: pointer;
        }
        .privacy-check input {
            width: auto;
            margin-top: 3px;
            flex-shrink: 0;
            accent-color: var(--lp-accent);
        }
        .privacy-check a {
            color: var(--lp-accent);
            font-weight: 700;
        }
        * { box-sizing: border-box; }
        body:has(.login-page) {
            margin: 0;
            min-height: 100vh;
            background: #fafafa;
        }
        @media (max-width: 480px) {
            .login-page .login-form.login-card {
                padding: 22px 16px !important;
                border-radius: 20px !important;
            }
            .login-card__lead {
                font-size: 0.86rem !important;
                margin-bottom: 18px !important;
            }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main class="login-page" id="contenu-principal">
        <div class="login-page__container">
        <div class="login-form login-card">
            <div class="login-card__brand">
                <div class="login-card__mark" aria-hidden="true">🔐</div>
            </div>
            <h2>Connexion</h2>
            <p class="login-card__lead">Accédez à votre espace pour retrouver ou déclarer un document.</p>

            <div class="tabs" role="tablist" aria-label="Mode de connexion">
                <a href="<?php echo htmlspecialchars(samapiece_url('login.php?tab=email'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $tab === 'email' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $tab === 'email' ? 'true' : 'false'; ?>"><span class="tab-icon" aria-hidden="true">✉</span>Email</a>
                <a href="<?php echo htmlspecialchars(samapiece_url('login.php?tab=phone'), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $tab === 'phone' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $tab === 'phone' ? 'true' : 'false'; ?>"><span class="tab-icon" aria-hidden="true">📱</span>Téléphone</a>
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
                            <div class="form-group password-field-wrap">
                                <label for="password">Votre mot de passe</label>
                                <div class="password-field">
                                    <input type="password" id="password" name="password" required autocomplete="current-password">
                                    <button type="button" class="password-field__toggle" aria-label="Afficher le mot de passe" aria-pressed="false">
                                        <svg class="password-field__icon password-field__icon--show" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        <svg class="password-field__icon password-field__icon--hide is-hidden" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                    </button>
                                </div>
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
                    <div class="form-group password-field-wrap">
                        <label for="pw">Mot de passe</label>
                        <div class="password-field">
                            <input type="password" id="pw" name="password" required autocomplete="current-password">
                            <button type="button" class="password-field__toggle" aria-label="Afficher le mot de passe" aria-pressed="false">
                                <svg class="password-field__icon password-field__icon--show" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                <svg class="password-field__icon password-field__icon--hide is-hidden" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                            </button>
                        </div>
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
                <p class="muted form-footer__signup">
                    Pas encore de compte ?
                    <a href="<?php echo htmlspecialchars(samapiece_url('register.php'), ENT_QUOTES, 'UTF-8'); ?>">Créer un compte</a>
                </p>
            </div>
        </div>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
    <script>
    (function () {
        document.querySelectorAll('.password-field').forEach(function (wrap) {
            var input = wrap.querySelector('input');
            var btn = wrap.querySelector('.password-field__toggle');
            var iconShow = wrap.querySelector('.password-field__icon--show');
            var iconHide = wrap.querySelector('.password-field__icon--hide');
            if (!input || !btn) return;
            btn.addEventListener('click', function () {
                var revealing = input.type === 'password';
                input.type = revealing ? 'text' : 'password';
                btn.setAttribute('aria-pressed', revealing ? 'true' : 'false');
                btn.setAttribute('aria-label', revealing ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
                if (iconShow && iconHide) {
                    iconShow.classList.toggle('is-hidden', revealing);
                    iconHide.classList.toggle('is-hidden', !revealing);
                }
            });
        });
    })();
    </script>
</body>
</html>
