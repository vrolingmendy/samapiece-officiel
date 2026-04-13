<?php
require_once 'functions.php';
require_login();

$user = get_user_by_id($_SESSION['user_id']);
if (!$user) {
    header('Location: logout.php');
    exit;
}

$page_title = 'Déclarer un objet perdu - Samapiece';

function sanitize_filename($filename) {
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
    return preg_replace('/_+/', '_', $filename);
}

// Gestion de la déclaration
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $date_naissance = trim($_POST['date_naissance'] ?? '');
    $lieu_naissance = trim($_POST['lieu_naissance'] ?? '');
    $categorie = trim($_POST['categorie'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validation
    if (empty($nom)) $errors[] = 'Le nom est requis.';
    if (empty($prenom)) $errors[] = 'Le prénom est requis.';
    if (empty($date_naissance)) $errors[] = 'La date de naissance est requise.';
    if (empty($lieu_naissance)) $errors[] = 'Le lieu de naissance est requis.';
    if (empty($categorie)) {
        $errors[] = 'La catégorie est requise.';
    } elseif (!in_array($categorie, lost_item_category_valid_codes(), true)) {
        $errors[] = 'La catégorie sélectionnée n’est pas valide.';
    }

    // Validation des fichiers
    $photo1_path = null;
    $photo2_path = null;

    $upload_errors = [
        UPLOAD_ERR_INI_SIZE => 'Le fichier est trop volumineux selon la configuration du serveur.',
        UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux (max 5MB).',
        UPLOAD_ERR_PARTIAL => 'Le fichier a été partiellement uploadé.',
        UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été envoyé pour la photo recto.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
        UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque.',
        UPLOAD_ERR_EXTENSION => 'Une extension PHP a stoppé l\'upload du fichier.'
    ];

    if (!isset($_FILES['photo1'])) {
        $errors[] = 'La photo recto du document est requise.';
    } else {
        $photo1 = $_FILES['photo1'];
        if ($photo1['error'] !== UPLOAD_ERR_OK) {
            $errors[] = $upload_errors[$photo1['error']] ?? 'Erreur lors de l\'upload de la photo recto.';
        } elseif (!allowed_file($photo1['name'])) {
            $errors[] = 'Type de fichier non autorisé pour la photo recto.';
        } elseif ($photo1['size'] > MAX_FILE_SIZE) {
            $errors[] = 'Fichier trop volumineux pour la photo recto (max 5MB).';
        } elseif (!is_uploaded_file($photo1['tmp_name'])) {
            $errors[] = 'Le fichier recto n\'a pas été reçu correctement.';
        } else {
            $photo1_filename = uniqid() . '_' . sanitize_filename(basename($photo1['name']));
            $photo1_path = 'uploads/' . $photo1_filename;
            if (!move_uploaded_file($photo1['tmp_name'], UPLOAD_DIR . $photo1_filename)) {
                $last_error = error_get_last();
                $errors[] = 'Erreur lors de l\'upload de la photo recto.' . (isset($last_error['message']) ? ' ' . $last_error['message'] : '');
            }
        }
    }

    if (empty($errors)) {
        $item = [
            'id' => generate_uuid(),
            'user_id' => $user['id'],
            'nom' => $nom,
            'prenom' => $prenom,
            'date_naissance' => $date_naissance,
            'lieu_naissance' => $lieu_naissance,
            'categorie' => $categorie,
            'telephone' => (string) ($user['telephone'] ?? ''),
            'description' => $description,
            'photo1' => $photo1_path,
            'photo2' => null
        ];

        add_lost_item($item);
        if (function_exists('session_write_close')) {
            session_write_close();
        }
        header('Location: ' . samapiece_absolute_url('dashboard.php#mes-declarations'), true, 302);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <?php require __DIR__ . '/includes/head_favicon.php'; ?>
    <style>
        :root {
            --accent: #00b7ff;
            --accent-2: #0ea5e9;
            --green: #128c7e;
            --green-dark: #0e6b62;
            --green-soft: #e8f5f3;
            --dark: #0f172a;
            --muted: #425466;
            --bg: #f9fbff;
            --surface: #ffffff;
            --line: rgba(148, 163, 184, 0.35);
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg);
            color: var(--dark);
            line-height: 1.6;
            padding-bottom: env(safe-area-inset-bottom, 0px);
        }

        .container {
            max-width: min(920px, 100%);
            margin: 0 auto;
            padding: 0 clamp(14px, 4vw, 24px);
        }

        main {
            padding: 24px 0 0;
            width: 100%;
            min-width: 0;
        }

        .declare-section {
            position: relative;
            margin-bottom: 32px;
            padding: 0;
            border-radius: 22px;
            overflow: hidden;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 252, 255, 1) 38%, rgba(236, 253, 245, 0.45) 100%);
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.9) inset,
                0 20px 50px rgba(15, 23, 42, 0.07),
                0 4px 20px rgba(0, 183, 255, 0.05);
            width: 100%;
            min-width: 0;
        }

        .declare-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green) 0%, var(--accent-2) 50%, var(--accent) 100%);
            opacity: 0.95;
        }

        .declare-section__bg {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            border-radius: inherit;
        }

        .declare-section__bg::after {
            content: "";
            position: absolute;
            width: min(380px, 85vw);
            height: min(380px, 85vw);
            right: -15%;
            top: -20%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 183, 255, 0.1) 0%, transparent 68%);
        }

        .declare-section__bg::before {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            left: -10%;
            bottom: 15%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(18, 140, 126, 0.09) 0%, transparent 70%);
        }

        .declare-section__inner {
            position: relative;
            z-index: 1;
            padding: clamp(22px, 4vw, 38px) clamp(18px, 4vw, 40px) clamp(28px, 4.5vw, 44px);
        }

        .declare-section__head {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: clamp(14px, 3vw, 22px);
            align-items: center;
            margin-bottom: clamp(22px, 3.5vw, 30px);
            padding-bottom: clamp(18px, 3vw, 24px);
            border-bottom: 1px solid var(--line);
        }

        @media (max-width: 540px) {
            .declare-section__head {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }
        }

        .declare-section__icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: clamp(56px, 12vw, 72px);
            height: clamp(56px, 12vw, 72px);
            border-radius: 20px;
            background: linear-gradient(145deg, #fff 0%, #f0f9ff 100%);
            border: 1px solid rgba(0, 183, 255, 0.22);
            box-shadow:
                0 10px 28px rgba(0, 183, 255, 0.12),
                0 0 0 1px rgba(255, 255, 255, 0.8) inset;
            font-size: clamp(1.6rem, 4vw, 2rem);
            line-height: 1;
        }

        .declare-section__head h2 {
            margin: 0;
            min-width: 0;
            font-size: clamp(1.35rem, 3.2vw, 1.85rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            color: var(--dark);
            line-height: 1.15;
        }

        .declare-errors {
            margin-bottom: clamp(18px, 3vw, 24px);
            padding: 14px 18px;
            border-radius: 14px;
            background: linear-gradient(135deg, #fef2f2 0%, #fff1f2 100%);
            border: 1px solid rgba(248, 113, 113, 0.35);
            color: #b91c1c;
            font-size: 0.92rem;
        }

        .declare-errors ul {
            margin: 0;
            padding-left: 1.15em;
        }

        .declare-errors li {
            margin: 0.25em 0;
        }

        .declare-form {
            display: flex;
            flex-direction: column;
            gap: clamp(22px, 3.5vw, 28px);
        }

        .declare-panel {
            padding: clamp(16px, 3vw, 22px);
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(226, 232, 240, 0.85);
            box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
        }

        .declare-panel h3 {
            margin: 0 0 6px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--accent-2);
        }

        .declare-panel__lead {
            margin: 0 0 16px;
            font-size: 0.88rem;
            color: var(--muted);
            line-height: 1.45;
            max-width: 52ch;
        }

        .declare-fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: clamp(14px, 2.5vw, 20px);
        }

        @media (max-width: 640px) {
            .declare-fields {
                grid-template-columns: 1fr;
            }
        }

        .declare-section .form-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .declare-section .form-group.full-width {
            grid-column: 1 / -1;
        }

        .declare-section .form-group label,
        .upload-label {
            margin-bottom: 7px;
            font-weight: 600;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            color: var(--dark);
        }

        .declare-section .form-group input,
        .declare-section .form-group select,
        .declare-section .form-group textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 12px;
            background: var(--surface);
            font-size: 16px;
            color: var(--dark);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .declare-section .form-group input:hover,
        .declare-section .form-group select:hover,
        .declare-section .form-group textarea:hover {
            border-color: rgba(0, 183, 255, 0.35);
        }

        .declare-section .form-group input:focus,
        .declare-section .form-group select:focus,
        .declare-section .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 183, 255, 0.18);
        }

        .declare-section .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .upload-label {
            display: block;
            margin-bottom: 10px;
        }

        .upload-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: clamp(18px, 4vw, 26px) 20px;
            border-radius: 16px;
            text-align: center;
            min-height: 172px;
            overflow: hidden;
            isolation: isolate;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(240, 249, 255, 0.95) 100%);
            border: 1px dashed rgba(0, 183, 255, 0.35);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.9) inset,
                0 12px 36px rgba(0, 183, 255, 0.08);
            transition: border-color 0.25s ease, box-shadow 0.25s ease;
        }

        .upload-card:hover {
            border-color: rgba(0, 183, 255, 0.55);
            border-style: solid;
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.95) inset,
                0 16px 44px rgba(0, 183, 255, 0.12);
        }

        .upload-card:focus-within {
            border-color: var(--accent);
            border-style: solid;
            box-shadow: 0 0 0 3px rgba(0, 183, 255, 0.2);
        }

        .upload-card__native {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 4;
            font-size: 0;
        }

        .upload-card__glow {
            position: absolute;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 183, 255, 0.18) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -52%);
            pointer-events: none;
            z-index: 0;
        }

        .upload-card__icon {
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
            width: 52px;
            height: 52px;
            border-radius: 16px;
            background: linear-gradient(145deg, rgba(0, 183, 255, 0.14), rgba(14, 165, 233, 0.08));
            border: 1px solid rgba(0, 183, 255, 0.28);
            font-size: 1.4rem;
            line-height: 1;
        }

        .upload-card__title {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: var(--dark);
        }

        .upload-card__hint {
            position: relative;
            z-index: 1;
            margin: 0;
            color: var(--muted);
            font-size: 0.84rem;
            line-height: 1.4;
            max-width: 32ch;
        }

        .upload-card__cta {
            position: relative;
            z-index: 1;
            margin-top: 4px;
            padding: 9px 18px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #fff;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            border: none;
            pointer-events: none;
            box-shadow: 0 4px 14px rgba(0, 183, 255, 0.3);
        }

        @media (max-width: 640px) {
            .upload-card {
                min-height: 148px;
                padding: 14px 12px;
                gap: 8px;
            }
            .upload-card__icon {
                width: 44px;
                height: 44px;
                font-size: 1.2rem;
            }
            .upload-card__title {
                font-size: 0.85rem;
            }
            .upload-card__hint {
                font-size: 0.78rem;
            }
            .upload-card__glow {
                width: 100px;
                height: 100px;
            }
        }

        .declare-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: flex-start;
            padding-top: 4px;
        }

        .submit-btn {
            min-height: 52px;
            padding: 15px 28px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.25s ease, filter 0.2s ease;
            box-shadow:
                0 4px 14px rgba(0, 183, 255, 0.35),
                0 1px 0 rgba(255, 255, 255, 0.25) inset;
        }

        .submit-btn:hover {
            filter: brightness(1.05);
            transform: translateY(-2px);
            box-shadow:
                0 12px 28px rgba(14, 165, 233, 0.38),
                0 1px 0 rgba(255, 255, 255, 0.25) inset;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .declare-actions__hint {
            font-size: 0.82rem;
            color: var(--muted);
            max-width: 36ch;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main>
        <div class="container">
            <section class="declare-section" aria-labelledby="declare-heading">
                <div class="declare-section__bg" aria-hidden="true"></div>
                <div class="declare-section__inner">
                    <header class="declare-section__head">
                        <div class="declare-section__icon" aria-hidden="true">📋</div>
                        <h2 id="declare-heading">Déclarer un objet perdu</h2>
                    </header>
                    <?php if (!empty($errors)): ?>
                        <div class="declare-errors" role="alert">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data" class="declare-form">
                        <div class="declare-panel">
                            <h3>Étape 1 — Document</h3>
                            <p class="declare-panel__lead">Ajoutez une photo lisible du recto. Elle sert à identifier le document auprès des personnes qui pourraient l’avoir retrouvé.</p>
                            <div class="form-group full-width">
                                <label class="upload-label" for="photo1">Photo du document (recto) *</label>
                                <div class="upload-card">
                                    <input type="file" id="photo1" name="photo1" accept="image/*" required class="upload-card__native" aria-describedby="photo1-hint">
                                    <span class="upload-card__glow" aria-hidden="true"></span>
                                    <span class="upload-card__icon" aria-hidden="true">📷</span>
                                    <strong class="upload-card__title">Recto du document</strong>
                                    <p class="upload-card__hint" id="photo1-hint">Photo nette, bien cadrée — tout le document visible.</p>
                                    <span class="upload-card__cta">Choisir un fichier</span>
                                </div>
                            </div>
                        </div>

                        <div class="declare-panel">
                            <h3>Étape 2 — Titulaire</h3>
                            <p class="declare-panel__lead">Les informations doivent correspondre à celles figurant sur le document.</p>
                            <div class="declare-fields">
                                <div class="form-group">
                                    <label for="nom">Nom *</label>
                                    <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>" autocomplete="family-name">
                                </div>
                                <div class="form-group">
                                    <label for="prenom">Prénom *</label>
                                    <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>" autocomplete="given-name">
                                </div>
                                <div class="form-group">
                                    <label for="date_naissance">Date de naissance *</label>
                                    <input type="date" id="date_naissance" name="date_naissance" required value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="lieu_naissance">Lieu de naissance *</label>
                                    <input type="text" id="lieu_naissance" name="lieu_naissance" required value="<?php echo htmlspecialchars($_POST['lieu_naissance'] ?? ''); ?>" placeholder="Ville, pays" autocomplete="off">
                                </div>
                                <div class="form-group full-width">
                                    <label for="categorie">Catégorie *</label>
                                    <select id="categorie" name="categorie" required>
                                        <option value="">Sélectionnez une catégorie</option>
                                        <?php
                                        $post_cat = $_POST['categorie'] ?? '';
                                        foreach (lost_item_category_options() as $cat_val => $cat_label):
                                            ?>
                                        <option value="<?php echo htmlspecialchars($cat_val, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $post_cat === $cat_val ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat_label, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="declare-panel">
                            <h3>Étape 3 — Détails</h3>
                            <p class="declare-panel__lead">Ajoutez une précision utile pour les trouveurs (couleur, marque, etc.).</p>
                            <div class="form-group full-width">
                                <label for="description">Description (optionnel)</label>
                                <textarea id="description" name="description" placeholder="Ex. couleur du portefeuille, état du document…"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <div class="declare-actions">
                            <button type="submit" class="submit-btn">Enregistrer la déclaration</button>
                            <span class="declare-actions__hint">Votre déclaration sera visible par les utilisateurs qui recherchent un document correspondant.</span>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>