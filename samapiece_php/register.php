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
        .container {
            max-width: 520px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .register-form {
            background: var(--surface);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .register-form h2 {
            text-align: center;
            margin-bottom: 8px;
            color: var(--dark);
        }
        .mode-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            border-bottom: 1px solid #e5edf6;
            padding-bottom: 12px;
        }
        .mode-tabs button {
            flex: 1;
            padding: 10px;
            border: 1px solid #e5edf6;
            background: #f8fafc;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: var(--muted);
        }
        .mode-tabs button.active {
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .mode-panel { display: none; }
        .mode-panel.active { display: block; }
        .form-row { display: flex; gap: 10px; margin-bottom: 20px; }
        .form-row .form-group { flex: 1; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5edf6;
            border-radius: 6px;
            font-size: 16px;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--accent);
        }
        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.95rem;
        }
        .message.error {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .message.success {
            background-color: #d1fae5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .message.info {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .button-group { display: flex; gap: 10px; margin-top: 20px; }
        .btn {
            flex: 1;
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
        .links { text-align: center; margin-top: 20px; }
        .links a { color: var(--accent); text-decoration: none; }
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
        .step-label {
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
        <?php if ($show_otp_step): ?>
            <form class="register-form" method="POST" id="reg-form-otp">
                <h2>Confirmer votre e-mail</h2>
                <?php if ($message): ?>
                    <div class="message <?php echo htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
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
            <form class="register-form" method="POST" style="margin-top:-10px;padding-top:0;box-shadow:none;background:transparent;">
                <input type="hidden" name="register_action" value="register_resend_otp">
                <input type="hidden" name="reg_otp_user_id" value="<?php echo $reg_otp_uid_attr; ?>">
                <button type="submit" class="link-btn">Renvoyer le code par e-mail</button>
            </form>
            <form class="register-form" method="POST" style="margin-top:-8px;padding-top:0;box-shadow:none;background:transparent;">
                <input type="hidden" name="register_action" value="register_cancel_otp">
                <button type="submit" class="link-btn">Modifier mon inscription</button>
            </form>
            <div class="links" style="margin-top:16px;">
                <a href="login.php">Déjà un compte ? Se connecter</a>
            </div>
        <?php else: ?>
        <form class="register-form" method="POST" id="reg-form">
            <h2>S’inscrire</h2>

            <div class="mode-tabs" role="tablist">
                <button type="button" class="<?php echo $register_mode === 'email' ? 'active' : ''; ?>" data-mode="email" role="tab" aria-selected="<?php echo $register_mode === 'email' ? 'true' : 'false'; ?>">Avec email</button>
                <button type="button" class="<?php echo $register_mode === 'phone' ? 'active' : ''; ?>" data-mode="phone" role="tab" aria-selected="<?php echo $register_mode === 'phone' ? 'true' : 'false'; ?>">Téléphone seulement</button>
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

            <div class="links">
                <a href="login.php">Déjà un compte ? Se connecter</a>
            </div>
        </form>
        <?php endif; ?>
    </main>
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
