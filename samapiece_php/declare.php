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
    if (empty($categorie)) $errors[] = 'La catégorie est requise.';

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
            --dark: #0f172a;
            --muted: #425466;
            --bg: #f9fbff;
            --surface: #ffffff;
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
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        main {
            padding: 28px 0 0;
        }

        .declare-section {
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(248,252,255,0.92));
            padding: clamp(20px, 5vw, 40px);
            border-radius: clamp(18px, 4vw, 28px);
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.9);
            backdrop-filter: blur(18px);
            width: 100%;
            min-width: 0;
        }

        .declare-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input, .form-group select, .form-group textarea {
            padding: 16px;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 18px;
            background: rgba(255,255,255,0.9);
            font-size: 16px;
            color: var(--dark);
            transition: all 0.25s ease;
            box-shadow: inset 0 1px 4px rgba(15,23,42,0.08);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(0,183,255,0.12);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .upload-label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .upload-card {
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 22px 20px;
            border-radius: 18px;
            text-align: center;
            min-height: 168px;
            overflow: hidden;
            isolation: isolate;
            background:
                linear-gradient(135deg, rgba(255,255,255,0.95) 0%, rgba(240, 249, 255, 0.9) 100%);
            border: 1px solid rgba(0, 183, 255, 0.22);
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.8) inset,
                0 10px 40px rgba(0, 183, 255, 0.08),
                0 2px 12px rgba(15, 23, 42, 0.04);
            transition: box-shadow 0.25s ease, border-color 0.25s ease, transform 0.2s ease;
        }

        .upload-card:hover {
            border-color: rgba(0, 183, 255, 0.45);
            box-shadow:
                0 0 0 1px rgba(255,255,255,0.9) inset,
                0 14px 48px rgba(0, 183, 255, 0.12),
                0 4px 16px rgba(14, 165, 233, 0.1);
        }

        .upload-card:focus-within {
            border-color: var(--accent);
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
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 183, 255, 0.2) 0%, transparent 70%);
            top: 50%;
            left: 50%;
            transform: translate(-50%, -55%);
            pointer-events: none;
            z-index: 0;
        }

        .upload-card__icon {
            position: relative;
            z-index: 1;
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(145deg, rgba(0, 183, 255, 0.15), rgba(14, 165, 233, 0.08));
            border: 1px solid rgba(0, 183, 255, 0.25);
            font-size: 1.35rem;
            line-height: 1;
        }

        .upload-card__title {
            position: relative;
            z-index: 1;
            margin: 0;
            font-size: 0.92rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: var(--dark);
        }

        .upload-card__hint {
            position: relative;
            z-index: 1;
            margin: 0;
            color: var(--muted);
            font-size: 0.82rem;
            line-height: 1.35;
            max-width: 28ch;
        }

        .upload-card__cta {
            position: relative;
            z-index: 1;
            margin-top: 4px;
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--accent-2);
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(0, 183, 255, 0.28);
            pointer-events: none;
        }

        @media (max-width: 640px) {
            .container {
                padding: 0 14px;
            }
            main {
                padding: 20px 0 0;
            }
            .declare-section {
                padding: 18px 14px;
                border-radius: 20px;
            }
            .upload-card {
                min-height: 132px;
                padding: 12px 10px;
                gap: 6px;
                border-radius: 16px;
            }
            .upload-card__icon {
                width: 40px;
                height: 40px;
                border-radius: 12px;
                font-size: 1.15rem;
            }
            .upload-card__title {
                font-size: 0.8rem;
            }
            .upload-card__hint {
                font-size: 0.72rem;
            }
            .upload-card__cta {
                font-size: 0.65rem;
                padding: 6px 12px;
            }
            .upload-card__glow {
                width: 90px;
                height: 90px;
            }
        }

        .submit-btn {
            padding: 12px 24px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            grid-column: 1 / -1;
            justify-self: start;
        }

        .submit-btn:hover {
            background: var(--accent-2);
            transform: translateY(-1px);
        }

        .errors {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            grid-column: 1 / -1;
        }

        @media (max-width: 768px) {
            .declare-form {
                grid-template-columns: 1fr;
                gap: 18px;
            }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main>
        <div class="container">
            <section class="declare-section">
                <h2>Déclarer un objet perdu</h2>
                <?php if (!empty($errors)): ?>
                    <div class="errors">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" class="declare-form">
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

                    <div class="form-group">
                        <label for="nom">Nom *</label>
                        <input type="text" id="nom" name="nom" required value="<?php echo htmlspecialchars($_POST['nom'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom *</label>
                        <input type="text" id="prenom" name="prenom" required value="<?php echo htmlspecialchars($_POST['prenom'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_naissance">Date de naissance *</label>
                        <input type="date" id="date_naissance" name="date_naissance" required value="<?php echo htmlspecialchars($_POST['date_naissance'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lieu_naissance">Lieu de naissance *</label>
                        <input type="text" id="lieu_naissance" name="lieu_naissance" required value="<?php echo htmlspecialchars($_POST['lieu_naissance'] ?? ''); ?>" placeholder="Ville, Pays">
                    </div>
                    <div class="form-group">
                        <label for="categorie">Catégorie *</label>
                        <select id="categorie" name="categorie" required>
                            <option value="">Sélectionnez une catégorie</option>
                            <option value="carte_identite" <?php echo ($_POST['categorie'] ?? '') === 'carte_identite' ? 'selected' : ''; ?>>Carte d'identité</option>
                            <option value="passeport" <?php echo ($_POST['categorie'] ?? '') === 'passeport' ? 'selected' : ''; ?>>Passeport</option>
                            <option value="permis_conduire" <?php echo ($_POST['categorie'] ?? '') === 'permis_conduire' ? 'selected' : ''; ?>>Permis de conduire</option>
                            <option value="carte_vitale" <?php echo ($_POST['categorie'] ?? '') === 'carte_vitale' ? 'selected' : ''; ?>>Carte Vitale</option>
                            <option value="autre" <?php echo ($_POST['categorie'] ?? '') === 'autre' ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Description (optionnel)</label>
                        <textarea id="description" name="description" placeholder="Ajoutez des détails supplémentaires..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                    <button type="submit" class="submit-btn">Déclarer</button>
                </form>
            </section>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>