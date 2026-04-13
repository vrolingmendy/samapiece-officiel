<?php
require_once 'functions.php';

$message = '';
$success = false;
$message_type = 'error';
$register_mode = $_POST['register_mode'] ?? $_GET['mode'] ?? 'email';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reg_action = $_POST['register_action'] ?? '';

    if ($reg_action === 'register_cancel_otp') {
        unset($_SESSION['register_verify_user_id'], $_SESSION['register_otp_sent_at'], $_SESSION['register_privacy_accepted']);
        header('Location: ' . samapiece_absolute_url('register.php'), true, 302);
        exit;
    }

    if ($reg_action === 'register_resend_otp') {
        $user_id = trim((string) ($_SESSION['register_verify_user_id'] ?? $_POST['reg_otp_user_id'] ?? ''));
        $user = $user_id !== '' ? get_user_by_id($user_id) : null;
        if (!$user || user_registration_type($user) !== 'otp_email' || !empty($user['is_verified'])) {
            unset($_SESSION['register_verify_user_id'], $_SESSION['register_otp_sent_at']);
            $message = 'Session invalide. Recommencez votre inscription.';
        } else {
            $_SESSION['register_verify_user_id'] = $user_id;
            require_once __DIR__ . '/includes/smtp_mail.php';
            $last = $_SESSION['register_otp_sent_at'] ?? 0;
            if (time() - $last < 60) {
                $message = 'Veuillez patienter une minute avant de redemander un code.';
                $message_type = 'info';
            } elseif (!smtp_mail_is_configured()) {
                $message = 'Envoi d’email non configuré.';
            } else {
                $otp = auth_generate_otp();
                auth_set_email_otp($user_id, $otp, 15);
                $user = get_user_by_id($user_id);
                if ($user && auth_send_otp_email_html($user['email'], $otp, $user['prenom'] ?? '', $user['nom'] ?? '', 'register')) {
                    $_SESSION['register_otp_sent_at'] = time();
                    $message = 'Un nouveau code vous a été envoyé par e-mail.';
                    $message_type = 'info';
                } else {
                    $message = 'Échec de l’envoi. Réessayez plus tard.';
                }
            }
        }
    } elseif ($reg_action === 'register_verify_otp') {
        if (empty($_SESSION['register_privacy_accepted'])) {
            $message = 'Session invalide. Recommencez l’inscription.';
            unset($_SESSION['register_verify_user_id'], $_SESSION['register_otp_sent_at']);
        } else {
        $user_id = trim((string) ($_SESSION['register_verify_user_id'] ?? $_POST['reg_otp_user_id'] ?? ''));
        $user = $user_id !== '' ? get_user_by_id($user_id) : null;
        if (!$user || user_registration_type($user) !== 'otp_email' || !empty($user['is_verified'])) {
            $message = 'Session expirée. Recommencez l’inscription.';
            unset($_SESSION['register_verify_user_id'], $_SESSION['register_otp_sent_at'], $_SESSION['register_privacy_accepted']);
        } else {
            $_SESSION['register_verify_user_id'] = $user_id;
            list($ok, $errmsg) = auth_verify_email_otp($user, $_POST['code'] ?? '');
            if (!$ok) {
                $message = $errmsg;
            } else {
                auth_clear_email_otp($user_id);
                $pdo = get_db_connection();
                $pdo->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$user_id]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_email'] = $user['email'];
                unset($_SESSION['register_verify_user_id'], $_SESSION['register_otp_sent_at'], $_SESSION['register_privacy_accepted']);
                SecurityManager::log_security_event('LOGIN_SUCCESS', ['user_id' => $user_id, 'via' => 'register_email_code']);
                session_write_close();
                header('Location: ' . samapiece_absolute_url('profil.php'), true, 302);
                exit;
            }
        }
        }
    } else {
        $mode = $_POST['register_mode'] ?? 'email';

        if ($mode === 'email') {
            $nom = SecurityManager::sanitize_input($_POST['nom'] ?? '');
            $prenom = SecurityManager::sanitize_input($_POST['prenom'] ?? '');
            $email = SecurityManager::sanitize_input($_POST['email'] ?? '');
            $telephone = SecurityManager::sanitize_input($_POST['telephone'] ?? '');
            $code_pays = SecurityManager::sanitize_input($_POST['code_pays'] ?? '+221');
            $errors = [];

            if (empty($_POST['accept_privacy'])) {
                $errors[] = 'Vous devez accepter la politique de confidentialité pour créer un compte.';
            }

            if ($nom === '' || $prenom === '' || $email === '' || $telephone === '') {
                $errors[] = 'Remplissez le nom, le prénom, l’email et le numéro de téléphone.';
            }
            if (!SecurityManager::validate_email($email)) {
                $errors[] = 'Adresse email invalide.';
            }
            if (get_user_by_email($email)) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }
            $full_phone = normalize_full_phone($code_pays, $telephone);
            if (strlen(preg_replace('/\D/', '', $telephone)) < 6) {
                $errors[] = 'Numéro de téléphone invalide.';
            }
            if ($full_phone !== '' && get_user_by_telephone($full_phone)) {
                $errors[] = 'Ce numéro de téléphone est déjà utilisé.';
            }

            if (empty($errors)) {
                require_once __DIR__ . '/includes/smtp_mail.php';
                if (!smtp_mail_is_configured()) {
                    $errors[] = 'L’envoi d’email n’est pas configuré : renseignez le mot de passe SMTP dans un fichier .env (copie de .env.example), ou dans config.local.php, ou la variable SAMAPIECE_SMTP_PASSWORD sur l’hébergeur.';
                }
            }

            if (empty($errors)) {
                $user_id = generate_uuid();
                $otp = auth_generate_otp();
                $pdo = get_db_connection();
                try {
                    $stmt = $pdo->prepare('INSERT INTO users (id, nom, prenom, email, telephone, code_pays, password_hash, is_verified, verification_token, date_creation, registration_type, date_naissance, email_otp_hash, email_otp_expires) VALUES (?, ?, ?, ?, ?, ?, NULL, 0, NULL, NOW(), ?, NULL, NULL, NULL)');
                    $stmt->execute([$user_id, $nom, $prenom, $email, $full_phone, $code_pays, 'otp_email']);
                    auth_set_email_otp($user_id, $otp, 15);
                    $sent = auth_send_otp_email_html($email, $otp, $prenom, $nom, 'register');
                    if (!$sent) {
                        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$user_id]);
                        $errors[] = 'Impossible d’envoyer l’email. Vérifiez le mot de passe SMTP (.env / config.local.php / variables serveur) et la connexion réseau (SSL).';
                    } else {
                        $_SESSION['register_verify_user_id'] = $user_id;
                        $_SESSION['register_otp_sent_at'] = time();
                        $_SESSION['register_privacy_accepted'] = true;
                        SecurityManager::log_security_event('USER_REGISTERED', ['user_id' => $user_id, 'email' => $email, 'type' => 'otp_email']);
                        $message = 'Un code à 6 chiffres vous a été envoyé par e-mail. Saisissez-le ci-dessous pour activer votre compte.';
                        $message_type = 'info';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Erreur lors de l’inscription. Réessayez plus tard.';
                }
            }
            if (!empty($errors)) {
                $message = implode('<br>', $errors);
                $message_type = 'error';
            }
        } else {
            $nom = SecurityManager::sanitize_input($_POST['nom'] ?? '');
            $prenom = SecurityManager::sanitize_input($_POST['prenom'] ?? '');
            $date_naissance = trim($_POST['date_naissance'] ?? '');
            $telephone = SecurityManager::sanitize_input($_POST['telephone_phone'] ?? '');
            $code_pays = SecurityManager::sanitize_input($_POST['code_pays_phone'] ?? '+221');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $errors = [];

            if (empty($_POST['accept_privacy'])) {
                $errors[] = 'Vous devez accepter la politique de confidentialité pour créer un compte.';
            }

            if ($nom === '' || $prenom === '' || $date_naissance === '' || $telephone === '') {
                $errors[] = 'Remplissez tous les champs obligatoires.';
            }
            $full_phone = normalize_full_phone($code_pays, $telephone);
            if (strlen(preg_replace('/\D/', '', $telephone)) < 6) {
                $errors[] = 'Numéro de téléphone invalide.';
            }
            if (get_user_by_telephone($full_phone)) {
                $errors[] = 'Ce numéro de téléphone est déjà enregistré.';
            }
            list($valid_password, $password_message) = SecurityManager::validate_password($password);
            if (!$valid_password) {
                $errors[] = $password_message;
            }
            if ($password !== $confirm_password) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (empty($errors)) {
                $user_id = generate_uuid();
                $pdo = get_db_connection();
                $dn_enc = samapiece_birth_date_encrypt($date_naissance);
                $stmt = $pdo->prepare('INSERT INTO users (id, nom, prenom, email, telephone, code_pays, password_hash, is_verified, verification_token, date_creation, registration_type, date_naissance, email_otp_hash, email_otp_expires) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NULL, NOW(), ?, ?, NULL, NULL)');
                $stmt->execute([
                    $user_id,
                    $nom,
                    $prenom,
                    null,
                    $full_phone,
                    $code_pays,
                    SecurityManager::hash_password($password),
                    'password_phone',
                    $dn_enc !== null ? $dn_enc : $date_naissance,
                ]);
                $success = true;
                $message = 'Compte créé. Vous pouvez vous connecter avec votre numéro de téléphone et votre mot de passe.';
                SecurityManager::log_security_event('USER_REGISTERED', ['user_id' => $user_id, 'type' => 'password_phone']);
            } else {
                $message = implode('<br>', $errors);
                $message_type = 'error';
            }
        }
    }
}

$reg_verify_id = $_SESSION['register_verify_user_id'] ?? '';
$verify_user = $reg_verify_id !== '' ? get_user_by_id($reg_verify_id) : null;
$show_otp_step = $verify_user
    && user_registration_type($verify_user) === 'otp_email'
    && empty($verify_user['is_verified']);

if ($show_otp_step) {
    $register_mode = 'email';
}

require_once __DIR__ . '/includes/phone_country_select.php';
$posted_code_email = $_POST['code_pays'] ?? '+221';
$posted_code_phone = $_POST['code_pays_phone'] ?? $_POST['code_pays'] ?? '+221';

$otp_expires_ts = 0;
if ($show_otp_step && !empty($verify_user['email_otp_expires'])) {
    $t = strtotime((string) $verify_user['email_otp_expires']);
    $otp_expires_ts = $t !== false ? $t : 0;
}
$reg_otp_uid_attr = htmlspecialchars((string) $reg_verify_id, ENT_QUOTES, 'UTF-8');
$reg_otp_email_attr = htmlspecialchars((string) ($verify_user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
$privacy_href = htmlspecialchars(samapiece_privacy_policy_url(), ENT_QUOTES, 'UTF-8');

$page_title = 'Inscription - Samapiece';
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
        body:has(.register-page) main {
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-top: clamp(8px, 2vw, 20px);
        }
        .register-page {
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
        .register-page__container {
            position: relative;
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
        }
        .register-form.register-card {
            position: relative;
            background: #ffffff;
            border: 1px solid var(--lp-line);
            border-radius: 24px;
            padding: clamp(28px, 5vw, 40px);
            box-shadow:
                0 1px 3px rgba(15, 23, 42, 0.06),
                0 12px 40px rgba(15, 23, 42, 0.08);
        }
        .register-card__brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .register-card__mark {
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
        .register-card__mark--logo {
            width: auto;
            max-width: min(200px, 85vw);
            height: 44px;
            padding: 4px 0;
            background: transparent;
            box-shadow: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-card__mark--logo img {
            display: block;
            height: 36px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }
        .register-form h2 {
            text-align: center;
            font-size: clamp(1.45rem, 4vw, 1.65rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            margin-bottom: 6px;
            color: var(--lp-dark);
        }
        .register-card__lead {
            text-align: center;
            font-size: 0.92rem;
            color: var(--lp-muted);
            font-weight: 500;
            margin-bottom: 22px;
            line-height: 1.45;
        }
        .mode-tabs {
            display: flex;
            gap: 6px;
            padding: 5px;
            margin-bottom: 22px;
            background: rgba(15, 23, 42, 0.06);
            border-radius: 999px;
            border: 1px solid rgba(15, 23, 42, 0.06);
        }
        .mode-tabs button {
            flex: 1;
            text-align: center;
            padding: 11px 12px;
            border-radius: 999px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            font-family: inherit;
            color: var(--lp-muted);
            background: transparent;
            transition: color 0.2s, background 0.25s, box-shadow 0.25s;
        }
        .mode-tabs button:hover:not(.active) {
            color: var(--lp-dark);
            background: rgba(255, 255, 255, 0.5);
        }
        .mode-tabs button.active {
            background: linear-gradient(135deg, #fff 0%, #f8fafc 100%);
            color: var(--lp-dark);
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.1), 0 0 0 1px rgba(15, 23, 42, 0.04);
        }
        .mode-tabs button .tab-icon {
            margin-right: 6px;
            opacity: 0.92;
        }
        .mode-panel { display: none; }
        .mode-panel.active { display: block; }
        .form-row {
            display: flex;
            gap: 12px;
            margin-bottom: 0;
        }
        .form-row .form-group { flex: 1; min-width: 0; }
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
        .register-page .phone-country-select {
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
        .message.success {
            background: linear-gradient(135deg, rgba(212, 239, 234, 0.95), rgba(236, 248, 246, 0.96));
            color: #0a5048;
            border-color: rgba(18, 140, 126, 0.35);
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
        .btn-primary:active { transform: translateY(0); }
        .form-footer {
            margin-top: 26px;
            padding-top: 22px;
            border-top: 1px solid var(--lp-line);
            text-align: center;
        }
        .form-footer a {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--lp-accent);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }
        .form-footer a:hover { border-bottom-color: var(--lp-accent); }
        .links {
            text-align: center;
            margin-top: 20px;
        }
        .links a {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--lp-violet);
            text-decoration: none;
        }
        .links a:hover { text-decoration: underline; }
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
        .login-next-step .step-label,
        .step-label {
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
        body:has(.register-page) {
            margin: 0;
            min-height: 100vh;
            background: #fafafa;
        }
        @media (max-width: 480px) {
            .register-page .register-form.register-card {
                padding: 22px 16px !important;
                border-radius: 20px !important;
            }
            .register-card__lead {
                font-size: 0.86rem !important;
                margin-bottom: 18px !important;
            }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <?php if ($show_otp_step): ?>
    <main class="register-page" id="contenu-principal">
        <div class="register-page__container">
            <div class="register-form register-card">
                <div class="register-card__brand">
                    <div class="register-card__mark<?php echo app_logo_available() ? ' register-card__mark--logo' : ''; ?>">
                        <?php if (app_logo_available()): ?>
                            <img src="<?php echo htmlspecialchars(samapiece_url(APP_LOGO_URL), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="160" height="40" decoding="async">
                        <?php else: ?>
                            <span aria-hidden="true">✉</span>
                        <?php endif; ?>
                    </div>
                </div>
                <h2>Confirmer votre e-mail</h2>
                <p class="register-card__lead">Saisissez le code à 6 chiffres reçu dans votre boîte mail.</p>
                <?php if ($message): ?>
                    <div class="message <?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" id="reg-form-otp">
                    <input type="hidden" name="register_action" value="register_verify_otp">
                    <input type="hidden" name="reg_otp_user_id" value="<?php echo $reg_otp_uid_attr; ?>">
                    <div class="form-group">
                        <label for="email_otp_show">Email</label>
                        <input type="email" id="email_otp_show" class="input-readonly" readonly autocomplete="username" value="<?php echo $reg_otp_email_attr; ?>" aria-readonly="true">
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
                <form method="POST">
                    <input type="hidden" name="register_action" value="register_resend_otp">
                    <input type="hidden" name="reg_otp_user_id" value="<?php echo $reg_otp_uid_attr; ?>">
                    <button type="submit" class="link-btn">Renvoyer le code par e-mail</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="register_action" value="register_cancel_otp">
                    <button type="submit" class="link-btn">Modifier mon inscription</button>
                </form>
                <div class="form-footer">
                    <a href="<?php echo htmlspecialchars(samapiece_url('login.php'), ENT_QUOTES, 'UTF-8'); ?>">Déjà un compte ? Se connecter</a>
                </div>
            </div>
        </div>
    </main>
    <?php else: ?>
    <main class="register-page" id="contenu-principal">
        <div class="register-page__container">
        <form class="register-form register-card" method="POST" id="reg-form">
            <div class="register-card__brand">
                <div class="register-card__mark<?php echo app_logo_available() ? ' register-card__mark--logo' : ''; ?>">
                    <?php if (app_logo_available()): ?>
                        <img src="<?php echo htmlspecialchars(samapiece_url(APP_LOGO_URL), ENT_QUOTES, 'UTF-8'); ?>" alt="" width="160" height="40" decoding="async">
                    <?php else: ?>
                        <span aria-hidden="true">✨</span>
                    <?php endif; ?>
                </div>
            </div>
            <h2>S’inscrire</h2>
            <p class="register-card__lead">Créez un compte pour déclarer ou retrouver un document perdu.</p>

            <div class="mode-tabs" role="tablist" aria-label="Mode d’inscription">
                <button type="button" class="<?php echo $register_mode === 'email' ? 'active' : ''; ?>" data-mode="email" role="tab" aria-selected="<?php echo $register_mode === 'email' ? 'true' : 'false'; ?>"><span class="tab-icon" aria-hidden="true">✉</span>Avec email</button>
                <button type="button" class="<?php echo $register_mode === 'phone' ? 'active' : ''; ?>" data-mode="phone" role="tab" aria-selected="<?php echo $register_mode === 'phone' ? 'true' : 'false'; ?>"><span class="tab-icon" aria-hidden="true">📱</span>Téléphone seulement</button>
            </div>

            <input type="hidden" name="register_mode" id="register_mode" value="<?php echo htmlspecialchars($register_mode === 'phone' ? 'phone' : 'email'); ?>">

            <?php if ($message): ?>
                <div class="message <?php echo $success ? 'success' : ($message_type === 'info' ? 'info' : 'error'); ?>">
                    <?php echo ($success || $message_type === 'info') ? htmlspecialchars($message) : $message; ?>
                </div>
            <?php endif; ?>

            <div class="mode-panel <?php echo $register_mode === 'email' ? 'active' : ''; ?>" id="panel-email" role="tabpanel">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="form-row-phone">
                    <div class="form-group form-group--dial">
                        <label for="code_pays">Indicatif</label>
                        <?php render_phone_country_select('code_pays', 'code_pays', $posted_code_email); ?>
                    </div>
                    <div class="form-group form-group--num">
                        <label for="telephone">Téléphone *</label>
                        <input type="tel" id="telephone" name="telephone" required value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="mode-panel <?php echo $register_mode === 'phone' ? 'active' : ''; ?>" id="panel-phone" role="tabpanel">
                <div class="form-row">
                    <div class="form-group">
                        <label for="nom_p">Nom *</label>
                        <input type="text" id="nom_p" name="nom" value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom_p">Prénom *</label>
                        <input type="text" id="prenom_p" name="prenom" value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="date_naissance">Date de naissance *</label>
                    <input type="date" id="date_naissance" name="date_naissance" value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
                </div>
                <div class="form-row-phone">
                    <div class="form-group form-group--dial">
                        <label for="code_pays_p">Indicatif</label>
                        <?php render_phone_country_select('code_pays_phone', 'code_pays_p', $posted_code_phone); ?>
                    </div>
                    <div class="form-group form-group--num">
                        <label for="telephone_p">Téléphone *</label>
                        <input type="tel" id="telephone_p" name="telephone_phone" value="<?php echo htmlspecialchars($_POST['telephone_phone'] ?? $_POST['telephone'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe *</label>
                    <input type="password" id="password" name="password" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe *</label>
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
                </div>
            </div>

            <div class="form-group form-group--privacy">
                <label class="privacy-check">
                    <input type="checkbox" name="accept_privacy" value="1" required>
                    <span>J’ai lu et j’accepte la <a href="<?php echo $privacy_href; ?>" target="_blank" rel="noopener noreferrer">politique de confidentialité</a>.</span>
                </label>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">Continuer</button>
            </div>

            <div class="form-footer">
                <a href="<?php echo htmlspecialchars(samapiece_url('login.php'), ENT_QUOTES, 'UTF-8'); ?>">Déjà un compte ? Se connecter</a>
            </div>
        </form>
        </div>
    </main>
    <?php endif; ?>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
    <script>
    (function() {
        var form = document.getElementById('reg-form');
        if (!form) return;
        var modeInput = document.getElementById('register_mode');
        var tabs = document.querySelectorAll('.mode-tabs button');
        var pe = document.getElementById('panel-email');
        var pp = document.getElementById('panel-phone');
        function setMode(m) {
            modeInput.value = m;
            tabs.forEach(function(b) {
                var on = b.getAttribute('data-mode') === m;
                b.classList.toggle('active', on);
                b.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            pe.classList.toggle('active', m === 'email');
            pp.classList.toggle('active', m === 'phone');
            var emailReq = ['nom', 'prenom', 'email', 'telephone'];
            pe.querySelectorAll('input,select').forEach(function(i) {
                i.disabled = (m !== 'email');
                i.required = (m === 'email' && emailReq.indexOf(i.id) !== -1);
            });
            var phoneReq = ['nom_p', 'prenom_p', 'date_naissance', 'telephone_p', 'password', 'confirm_password'];
            pp.querySelectorAll('input,select').forEach(function(i) {
                i.disabled = (m !== 'phone');
                i.required = (m === 'phone' && phoneReq.indexOf(i.id) !== -1);
            });
        }
        tabs.forEach(function(btn) {
            btn.addEventListener('click', function() { setMode(btn.getAttribute('data-mode')); });
        });
        setMode(modeInput.value === 'phone' ? 'phone' : 'email');
    })();
    </script>
</body>
</html>
