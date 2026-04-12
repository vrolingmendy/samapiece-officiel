<?php
require_once 'functions.php';
$page_title = 'Rechercher - Samapiece';

$recovery_flash = null;
if (!empty($_SESSION['recovery_flash'])) {
    $recovery_flash = $_SESSION['recovery_flash'];
    unset($_SESSION['recovery_flash']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_recovery') {
    if (!is_logged_in()) {
        $_SESSION['recovery_flash'] = ['ok' => false, 'message' => 'Connectez-vous pour demander la récupération d’un document.'];
        header('Location: ' . samapiece_absolute_url('login.php'));
        exit;
    }
    $criteria = [
        'nom' => trim($_POST['recovery_nom'] ?? ''),
        'prenom' => trim($_POST['recovery_prenom'] ?? ''),
        'date_naissance' => trim($_POST['recovery_date_naissance'] ?? ''),
        'lieu_naissance' => trim($_POST['recovery_lieu_naissance'] ?? ''),
        'categorie' => trim($_POST['recovery_categorie'] ?? ''),
    ];
    $lost_id = trim($_POST['lost_item_id'] ?? '');
    list($ok, $msg) = request_recovery_for_lost_item($lost_id, $_SESSION['user_id'], $criteria);
    $_SESSION['recovery_flash'] = ['ok' => $ok, 'message' => $msg];
    $redirect_q = array_filter([
        'nom' => $criteria['nom'],
        'prenom' => $criteria['prenom'],
        'date_naissance' => $criteria['date_naissance'],
        'lieu_naissance' => $criteria['lieu_naissance'],
        'categorie' => $criteria['categorie'],
    ], function ($v) {
        return $v !== null && $v !== '';
    });
    header('Location: ' . samapiece_absolute_url('dashboard.php#mes-demandes-recuperation'));
    exit;
}

$reminder_flash = null;
if (!empty($_SESSION['search_reminder_flash'])) {
    $reminder_flash = $_SESSION['search_reminder_flash'];
    unset($_SESSION['search_reminder_flash']);
}

$reminder_email_input = '';
$search_executed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_search_reminder') {
    $data = [
        'notify_email' => SecurityManager::sanitize_input($_POST['notify_email'] ?? ''),
        'nom' => trim($_POST['reminder_nom'] ?? ''),
        'prenom' => trim($_POST['reminder_prenom'] ?? ''),
        'date_naissance' => trim($_POST['reminder_date_naissance'] ?? ''),
        'lieu_naissance' => trim($_POST['reminder_lieu_naissance'] ?? ''),
        'categorie' => trim($_POST['reminder_categorie'] ?? ''),
        'user_id' => is_logged_in() ? $_SESSION['user_id'] : null,
    ];
    list($ok, $msg) = add_search_reminder($data);
    if ($ok) {
        $_SESSION['search_reminder_flash'] = ['ok' => true, 'message' => $msg];
        $redirect_q = array_filter([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'date_naissance' => $data['date_naissance'],
            'lieu_naissance' => $data['lieu_naissance'],
            'categorie' => $data['categorie'],
        ], function ($v) {
            return $v !== null && $v !== '';
        });
        header('Location: search.php?' . http_build_query($redirect_q));
        exit;
    }
    $reminder_flash = ['ok' => false, 'message' => $msg];
    $reminder_email_input = $data['notify_email'];
    $nom = $data['nom'];
    $prenom = $data['prenom'];
    $date_naissance = $data['date_naissance'];
    $lieu_naissance = $data['lieu_naissance'];
    $categorie = $data['categorie'];
    $search_results = filter_lost_items_by_search_criteria(get_all_lost_items(), $data);
    $search_executed = true;
}

// Gestion de la recherche (GET)
if (!$search_executed && (isset($_GET['nom']) || isset($_GET['prenom']) || isset($_GET['date_naissance']) || isset($_GET['lieu_naissance']) || isset($_GET['categorie']))) {
    $nom = trim($_GET['nom'] ?? '');
    $prenom = trim($_GET['prenom'] ?? '');
    $date_naissance = trim($_GET['date_naissance'] ?? '');
    $lieu_naissance = trim($_GET['lieu_naissance'] ?? '');
    $categorie = trim($_GET['categorie'] ?? '');
    $search_results = filter_lost_items_by_search_criteria(get_all_lost_items(), [
        'nom' => $nom,
        'prenom' => $prenom,
        'date_naissance' => $date_naissance,
        'lieu_naissance' => $lieu_naissance,
        'categorie' => $categorie,
    ]);
    $search_executed = true;
}

if (!$search_executed) {
    $search_results = [];
    $nom = '';
    $prenom = '';
    $date_naissance = '';
    $lieu_naissance = '';
    $categorie = '';
}

$default_reminder_email = $reminder_email_input;
if ($default_reminder_email === '' && is_logged_in()) {
    $u = get_user_by_id($_SESSION['user_id']);
    if ($u && !empty($u['email'])) {
        $default_reminder_email = $u['email'];
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
            padding-bottom: max(0px, env(safe-area-inset-bottom, 0px));
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 clamp(16px, 4vw, 24px);
            width: 100%;
        }

        main {
            padding: 24px 0 0;
            width: 100%;
            min-width: 0;
        }

        .search-section {
            background: var(--surface);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 40px;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 16px;
        }

        .search-btn {
            padding: 12px 24px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: var(--accent-2);
            transform: translateY(-1px);
        }

        .results-section {
            background: var(--surface);
            padding: clamp(20px, 4vw, 40px);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .results-header {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            justify-content: space-between;
            gap: 12px 20px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .results-header h2 {
            margin: 0;
            font-size: clamp(1.25rem, 2.5vw, 1.5rem);
        }

        .results-count {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
            font-weight: 600;
        }

        .item-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .item-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .item-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--accent);
        }

        .item-description {
            color: var(--muted);
            margin-bottom: 12px;
        }

        .item-meta {
            font-size: 0.9rem;
            color: var(--muted);
        }

        .no-results {
            text-align: center;
            padding: 10px 12px 4px;
            color: var(--muted);
        }

        .no-results p {
            margin: 0;
            font-size: 0.98rem;
        }

        .reminder-flash {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        .reminder-flash.ok {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .reminder-flash.err {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .reminder-panel {
            margin-top: 24px;
            padding: clamp(18px, 3vw, 24px);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 48%);
            text-align: left;
            max-width: 720px;
        }
        .reminder-panel h3 {
            font-size: 1.15rem;
            color: var(--dark);
            margin-bottom: 8px;
        }
        .reminder-panel .reminder-intro {
            font-size: 0.88rem;
            color: var(--muted);
            margin-bottom: 14px;
            line-height: 1.45;
        }
        .reminder-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            align-items: end;
        }
        .reminder-form .form-group-full {
            grid-column: 1 / -1;
        }
        .reminder-form label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 0.88rem;
            color: var(--dark);
        }
        .reminder-form input,
        .reminder-form select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 15px;
        }
        .reminder-submit-wrap {
            grid-column: 1 / -1;
            margin-top: 8px;
        }
        .reminder-submit {
            padding: 14px 24px;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .reminder-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(14, 165, 233, 0.35);
        }

        .results-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .item-recovery {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e5edf6;
        }

        .item-recovery-status {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
        }

        .item-recovery-status.is-pending {
            color: #c2410c;
        }

        .recovery-login-hint {
            font-size: 0.88rem;
            margin: 0;
        }

        .recovery-login-hint a {
            color: var(--accent-2);
            font-weight: 600;
        }

        .btn-recovery {
            padding: 10px 18px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #fff;
            font-weight: 600;
            font-size: 0.92rem;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.2s;
        }

        .btn-recovery:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(0, 183, 255, 0.25);
        }

        .btn-recovery:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }

        @media (min-width: 900px) {
            .results-section.has-reminder {
                display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(280px, 380px);
                gap: 28px 32px;
                align-items: start;
            }
            .results-section.has-reminder .results-block {
                min-width: 0;
            }
            .results-section.has-reminder .reminder-panel {
                margin-top: 0;
                position: sticky;
                top: 20px;
            }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main>
        <div class="container">
            <section class="search-section">
                <h2>Rechercher un objet perdu</h2>
                <form method="GET" class="search-form">
                    <div class="form-group">
                        <label for="nom">Nom</label>
                        <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($nom); ?>" placeholder="Nom de famille">
                    </div>
                    <div class="form-group">
                        <label for="prenom">Prénom</label>
                        <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($prenom); ?>" placeholder="Prénom">
                    </div>
                    <div class="form-group">
                        <label for="date_naissance">Date de naissance</label>
                        <input type="date" id="date_naissance" name="date_naissance" value="<?php echo htmlspecialchars($date_naissance); ?>">
                    </div>
                    <div class="form-group">
                        <label for="lieu_naissance">Lieu de naissance</label>
                        <input type="text" id="lieu_naissance" name="lieu_naissance" value="<?php echo htmlspecialchars($lieu_naissance); ?>" placeholder="Ville, Pays">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="search-btn">Rechercher</button>
                    </div>
                </form>
            </section>

            <?php if ($search_executed):
                ensure_lost_item_recovery_schema();
                $show_reminder_form = empty($search_results) && !($reminder_flash && !empty($reminder_flash['ok']));
                $results_section_class = 'results-section' . ($show_reminder_form ? ' has-reminder' : '');
                ?>
            <section class="<?php echo htmlspecialchars($results_section_class, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($recovery_flash): ?>
                    <div class="reminder-flash <?php echo !empty($recovery_flash['ok']) ? 'ok' : 'err'; ?>" role="alert" style="grid-column:1/-1;">
                        <?php echo htmlspecialchars($recovery_flash['message']); ?>
                    </div>
                <?php endif; ?>
                <?php if ($reminder_flash): ?>
                    <div class="reminder-flash <?php echo $reminder_flash['ok'] ? 'ok' : 'err'; ?>" role="alert" style="grid-column:1/-1;">
                        <?php echo htmlspecialchars($reminder_flash['message']); ?>
                    </div>
                <?php endif; ?>
                <header class="results-header" style="grid-column:1/-1;">
                    <h2>Résultats de recherche</h2>
                    <p class="results-count"><?php echo (int) count($search_results); ?> résultat<?php echo count($search_results) !== 1 ? 's' : ''; ?> trouvé<?php echo count($search_results) !== 1 ? 's' : ''; ?></p>
                </header>
                <?php if (empty($search_results)): ?>
                    <div class="results-block">
                        <div class="no-results">
                            <p>Aucun résultat trouvé pour votre recherche.</p>
                        </div>
                    <?php if ($reminder_flash && !empty($reminder_flash['ok'])): ?>
                        <p class="reminder-intro" style="margin-top: 12px; text-align: center;">Nous vous écrirons si un document correspond à votre alerte.</p>
                    <?php endif; ?>
                    </div>
                    <?php if ($show_reminder_form): ?>
                    <aside class="reminder-panel" aria-labelledby="reminder-heading">
                        <h3 id="reminder-heading">Me rappeler si un document correspond</h3>
                        <p class="reminder-intro">E-mail si une déclaration correspond aux critères ci-dessous (comme la recherche).</p>
                        <form method="post" class="reminder-form" action="<?php echo htmlspecialchars(samapiece_url('search.php'), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="create_search_reminder">
                            <div class="form-group-full">
                                <label for="notify_email">E-mail pour recevoir l’alerte *</label>
                                <input type="email" id="notify_email" name="notify_email" required autocomplete="email" value="<?php echo htmlspecialchars($default_reminder_email); ?>" placeholder="vous@exemple.com">
                            </div>
                            <div class="form-group">
                                <label for="reminder_nom">Nom</label>
                                <input type="text" id="reminder_nom" name="reminder_nom" value="<?php echo htmlspecialchars($nom); ?>" placeholder="Nom de famille">
                            </div>
                            <div class="form-group">
                                <label for="reminder_prenom">Prénom</label>
                                <input type="text" id="reminder_prenom" name="reminder_prenom" value="<?php echo htmlspecialchars($prenom); ?>" placeholder="Prénom">
                            </div>
                            <div class="form-group">
                                <label for="reminder_date_naissance">Date de naissance</label>
                                <input type="date" id="reminder_date_naissance" name="reminder_date_naissance" value="<?php echo htmlspecialchars($date_naissance); ?>">
                            </div>
                            <div class="form-group">
                                <label for="reminder_lieu_naissance">Lieu de naissance</label>
                                <input type="text" id="reminder_lieu_naissance" name="reminder_lieu_naissance" value="<?php echo htmlspecialchars($lieu_naissance); ?>" placeholder="Ville, pays">
                            </div>
                            <div class="form-group">
                                <label for="reminder_categorie">Catégorie de document</label>
                                <select id="reminder_categorie" name="reminder_categorie">
                                    <option value="">Toutes les catégories</option>
                                    <option value="carte_identite" <?php echo $categorie === 'carte_identite' ? 'selected' : ''; ?>>Carte d'identité</option>
                                    <option value="passeport" <?php echo $categorie === 'passeport' ? 'selected' : ''; ?>>Passeport</option>
                                    <option value="permis_conduire" <?php echo $categorie === 'permis_conduire' ? 'selected' : ''; ?>>Permis de conduire</option>
                                    <option value="carte_vitale" <?php echo $categorie === 'carte_vitale' ? 'selected' : ''; ?>>Carte Vitale</option>
                                    <option value="autre" <?php echo $categorie === 'autre' ? 'selected' : ''; ?>>Autre</option>
                                </select>
                            </div>
                            <div class="reminder-submit-wrap">
                                <button type="submit" class="reminder-submit">Enregistrer mon alerte</button>
                            </div>
                        </form>
                    </aside>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="results-block results-list" style="grid-column:1/-1;">
                    <?php foreach ($search_results as $item):
                        $rst = lost_item_recovery_status($item);
                        $is_owner = is_logged_in() && (string) ($item['user_id'] ?? '') === (string) ($_SESSION['user_id'] ?? '');
                        $can_request_recovery = is_logged_in() && !$is_owner && $rst === 'en_attente';
                        ?>
                        <div class="item-card">
                            <div class="item-title"><?php echo htmlspecialchars(($item['prenom'] ?? '') . ' ' . ($item['nom'] ?? '')); ?></div>
                            <div class="item-description">
                                Né(e) le <?php echo htmlspecialchars($item['date_naissance'] ?? ''); ?> à <?php echo htmlspecialchars($item['lieu_naissance'] ?? ''); ?>
                            </div>
                            <div class="item-meta">
                                Catégorie: <?php echo htmlspecialchars($item['categorie'] ?? 'Non spécifiée'); ?> |
                                Déclaré le: <?php echo htmlspecialchars($item['date_declared'] ?? 'Date inconnue'); ?>
                            </div>
                            <div class="item-recovery">
                                <?php if ($rst === 'demande_recuperation'): ?>
                                    <p class="item-recovery-status is-pending">Demande de récupération en cours — le déclarant a été informé.</p>
                                <?php elseif ($rst === 'recupere'): ?>
                                    <p class="item-recovery-status">Document marqué comme récupéré.</p>
                                <?php elseif ($is_owner): ?>
                                    <p class="item-recovery-status">C’est votre déclaration.</p>
                                <?php elseif ($can_request_recovery): ?>
                                    <form method="post" action="<?php echo htmlspecialchars(samapiece_url('search.php'), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="request_recovery">
                                        <input type="hidden" name="lost_item_id" value="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="recovery_nom" value="<?php echo htmlspecialchars($nom, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="recovery_prenom" value="<?php echo htmlspecialchars($prenom, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="recovery_date_naissance" value="<?php echo htmlspecialchars($date_naissance, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="recovery_lieu_naissance" value="<?php echo htmlspecialchars($lieu_naissance, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="recovery_categorie" value="<?php echo htmlspecialchars($categorie, ENT_QUOTES, 'UTF-8'); ?>">
                                        <button type="submit" class="btn-recovery">Demander la récupération</button>
                                    </form>
                                <?php else: ?>
                                    <p class="recovery-login-hint"><a href="<?php echo htmlspecialchars(samapiece_absolute_url('login.php'), ENT_QUOTES, 'UTF-8'); ?>">Connectez-vous</a> pour signaler que ce document est le vôtre et demander sa récupération.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
            <?php endif; ?>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>