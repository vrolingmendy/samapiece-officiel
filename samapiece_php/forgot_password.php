<?php
require_once 'functions.php';

$message = '';
$is_error = false;
$step = !empty($_SESSION['forgot_reset_uid']) ? 2 : 1;

if (isset($_GET['reset']) && $_GET['reset'] === 'ok') {
    $message = 'Votre mot de passe a été mis à jour. Vous pouvez vous connecter.';
    $is_error = false;
    $step = 3;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'cancel') {
        unset($_SESSION['forgot_reset_uid']);
        header('Location: forgot_password.php');
        exit;
    }

    if ($action === 'verify_identity') {
        unset($_SESSION['forgot_reset_uid']);
        $nom = SecurityManager::sanitize_input($_POST['nom'] ?? '');
        $prenom = SecurityManager::sanitize_input($_POST['prenom'] ?? '');
        $date_naissance = trim($_POST['date_naissance'] ?? '');
        $code_pays = SecurityManager::sanitize_input($_POST['code_pays'] ?? '+221');
        $telephone = SecurityManager::sanitize_input($_POST['telephone'] ?? '');
        $full = normalize_full_phone($code_pays, $telephone);

        if ($nom === '' || $prenom === '' || $date_naissance === '' || strlen(preg_replace('/\D/', '', $telephone)) < 6) {
            $message = 'Remplissez tous les champs.';
            $is_error = true;
        } else {
            $user = get_user_by_telephone($full);
            if (!$user || user_registration_type($user) !== 'password_phone') {
                $message = 'Les informations ne correspondent à aucun compte « téléphone ».';
                $is_error = true;
            } elseif (!auth_user_names_match($user, $nom, $prenom, $date_naissance)) {
                $message = 'Les informations ne correspondent pas à ce numéro.';
                $is_error = true;
            } else {
                $_SESSION['forgot_reset_uid'] = $user['id'];
                header('Location: forgot_password.php');
                exit;
            }
        }
    } elseif ($action === 'set_password') {
        $uid = $_SESSION['forgot_reset_uid'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($uid === '') {
            $message = 'Session expirée. Recommencez.';
            $is_error = true;
        } else {
            list($ok, $msg) = SecurityManager::validate_password($password);
            if (!$ok) {
                $message = $msg;
                $is_error = true;
            } elseif ($password !== $confirm) {
                $message = 'Les mots de passe ne correspondent pas.';
                $is_error = true;
            } else {
                $user = get_user_by_id($uid);
                if (!$user || user_registration_type($user) !== 'password_phone') {
                    unset($_SESSION['forgot_reset_uid']);
                    $message = 'Session invalide.';
                    $is_error = true;
                } else {
                    $pdo = get_db_connection();
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([
                        SecurityManager::hash_password($password),
                        $uid,
                    ]);
                    unset($_SESSION['forgot_reset_uid']);
                    SecurityManager::log_security_event('PASSWORD_RESET', ['user_id' => $uid]);
                    header('Location: forgot_password.php?reset=ok');
                    exit;
                }
            }
        }
    }
}

if ($step !== 3 && !isset($_GET['reset'])) {
    $step = !empty($_SESSION['forgot_reset_uid']) ? 2 : 1;
}

require_once __DIR__ . '/includes/phone_country_select.php';
$posted_code_forgot = $_POST['code_pays'] ?? '+221';

$page_title = 'Mot de passe oublié - Samapiece';
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
        .form-box {
            background: var(--surface);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .form-box h2 { text-align: center; margin-bottom: 10px; color: var(--dark); }
        .intro {
            text-align: center;
            font-size: 0.9rem;
            color: var(--muted);
            margin-bottom: 22px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e5edf6;
            border-radius: 6px;
            font-size: 16px;
        }
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
        .message.success { background: #d1fae5; color: #059669; border: 1px solid #a7f3d0; }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            background: var(--accent);
            color: white;
            margin-bottom: 10px;
        }
        .btn:hover { background: var(--accent-2); }
        .btn-ghost {
            background: transparent;
            color: var(--accent);
            border: 1px solid var(--accent);
        }
        .back { text-align: center; margin-top: 16px; }
        .back a { color: var(--accent); text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main class="container">
        <div class="form-box">
            <h2>Mot de passe oublié</h2>

            <?php if ($step === 1): ?>
                <p class="intro">Réservé aux comptes créés avec <strong>téléphone et mot de passe</strong>. Saisissez les mêmes informations qu’à l’inscription.</p>
            <?php elseif ($step === 2): ?>
                <p class="intro">Choisissez un nouveau mot de passe.</p>
            <?php else: ?>
                <p class="intro">Vous pouvez fermer cette page ou vous connecter.</p>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="message <?php echo $is_error ? 'error' : 'success'; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="verify_identity">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="date_naissance">Date de naissance</label>
                        <input type="date" id="date_naissance" name="date_naissance" required value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
                    </div>
                    <div class="form-row-phone">
                        <div class="form-group form-group--dial">
                            <label for="code_pays">Indicatif</label>
                            <?php render_phone_country_select('code_pays', 'code_pays', $posted_code_forgot); ?>
                        </div>
                        <div class="form-group form-group--num">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" id="telephone" name="telephone" required value="<?php echo htmlspecialchars($_POST['telephone'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn">Vérifier</button>
                </form>
            <?php elseif ($step === 2): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="set_password">
                    <div class="form-group">
                        <label for="password">Nouveau mot de passe</label>
                        <input type="password" id="password" name="password" required autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirmer</label>
                        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn">Enregistrer</button>
                </form>
                <form method="POST">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="btn btn-ghost">Annuler</button>
                </form>
            <?php endif; ?>

            <div class="back">
                <a href="login.php">← Retour à la connexion</a>
            </div>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
