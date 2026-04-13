<?php
require_once 'functions.php';
require_login();

$user = get_user_by_id($_SESSION['user_id']);
if (!$user) {
    header('Location: logout.php');
    exit;
}

list($posted_code, $posted_local) = phone_local_parts_for_form($user);
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = $_POST['nom'] ?? '';
    $prenom = $_POST['prenom'] ?? '';
    $email = $_POST['email'] ?? '';
    $posted_code = SecurityManager::sanitize_input($_POST['code_pays'] ?? $posted_code);
    $posted_local = SecurityManager::sanitize_input($_POST['telephone'] ?? $posted_local);

    list($ok, $extra) = update_user_profile($user, $nom, $prenom, $email, $posted_code, $posted_local);
    if (!$ok) {
        $message = $extra;
        $message_type = 'error';
    } else {
        $message_type = 'success';
        if ($extra === 'email_verification_sent') {
            $message = 'Profil mis à jour. Un code de vérification a été envoyé à votre nouvelle adresse email : confirmez-le pour réactiver le compte.';
        } else {
            $message = 'Vos informations ont été enregistrées.';
        }
        $user = get_user_by_id($_SESSION['user_id']);
        list($posted_code, $posted_local) = phone_local_parts_for_form($user);
    }
}

require_once __DIR__ . '/includes/phone_country_select.php';

$rt = user_registration_type($user);
$email_required = ($rt === 'otp_email' || $rt === 'password_email');
$inscription = $user['date_creation'] ?? $user['created_at'] ?? '';
$inscription_label = $inscription !== '' ? date('d/m/Y', strtotime($inscription)) : '—';

$page_title = 'Mes informations - Samapiece';
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
            background: var(--bg);
            color: var(--dark);
            line-height: 1.6;
        }
        .container { max-width: 560px; margin: 0 auto; padding: 0 20px; }
        main { padding: 28px 0 0; }
        h1 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        .lead {
            color: var(--muted);
            font-size: 0.95rem;
            margin-bottom: 24px;
        }
        .msg {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        .msg--error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .msg--success {
            background: #e8f5f3;
            border: 1px solid #b8dfd8;
            color: #166534;
        }
        .msg--info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }
        .card {
            background: var(--surface);
            border: 1px solid #e5edf6;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(15,23,42,0.06);
        }
        .field { margin-bottom: 18px; }
        .field label {
            display: block;
            font-weight: 600;
            font-size: 0.88rem;
            margin-bottom: 6px;
        }
        .field input[type="text"],
        .field input[type="email"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d0d8e4;
            border-radius: 8px;
            font-size: 1rem;
        }
        .field .readonly {
            padding: 10px 12px;
            background: #f8fafc;
            border-radius: 8px;
            color: var(--muted);
            font-size: 0.95rem;
        }
        .phone-row {
            display: grid;
            grid-template-columns: minmax(120px, 1fr) 2fr;
            gap: 10px;
            align-items: end;
        }
        @media (max-width: 480px) {
            .phone-row { grid-template-columns: 1fr; }
        }
        .actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            font-size: 0.95rem;
            border: 2px solid transparent;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--accent);
            color: #fff;
        }
        .btn-primary:hover { background: var(--accent-2); }
        .btn-secondary {
            background: #fff;
            color: var(--muted);
            border-color: #e5edf6;
        }
        .btn-secondary:hover {
            border-color: #cbd5e1;
            color: var(--dark);
        }
        .hint { font-size: 0.82rem; color: var(--muted); margin-top: 4px; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main>
        <div class="container">
            <h1>Mes informations</h1>
            <p class="lead">Modifiez vos coordonnées. L’email et le téléphone doivent rester uniques sur la plateforme.</p>

            <?php if ($message !== ''): ?>
                <div class="msg msg--<?php echo $message_type === 'error' ? 'error' : ($message_type === 'success' ? 'success' : 'info'); ?>">
                    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="post" action="account.php" autocomplete="on">
                    <div class="field">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" required
                               value="<?php echo htmlspecialchars($user['nom'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" required
                               value="<?php echo htmlspecialchars($user['prenom'] ?? ''); ?>">
                    </div>
                    <div class="field">
                        <label for="email">Email<?php echo $email_required ? '' : ' (facultatif)'; ?></label>
                        <input type="email" id="email" name="email"
                               <?php echo $email_required ? 'required' : ''; ?>
                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                               placeholder="<?php echo $email_required ? '' : 'Ajouter une adresse email'; ?>">
                        <?php if ($rt === 'otp_email'): ?>
                            <p class="hint">Si vous changez l’email, un code de vérification vous sera envoyé.</p>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <span class="label-like" style="display:block;font-weight:600;font-size:0.88rem;margin-bottom:6px;">Téléphone</span>
                        <div class="phone-row">
                            <div>
                                <label for="code_pays" class="sr-only">Indicatif</label>
                                <?php render_phone_country_select('code_pays', 'code_pays', $posted_code); ?>
                            </div>
                            <div>
                                <label for="telephone" class="sr-only">Numéro</label>
                                <input type="text" id="telephone" name="telephone" required inputmode="numeric"
                                       value="<?php echo htmlspecialchars($posted_local); ?>"
                                       autocomplete="tel-national">
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Inscription</label>
                        <div class="readonly"><?php echo htmlspecialchars($inscription_label); ?></div>
                    </div>
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                        <a href="dashboard.php" class="btn btn-secondary">Retour au tableau de bord</a>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
