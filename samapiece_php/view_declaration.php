<?php
require_once 'functions.php';
require_login();

$user = get_user_by_id($_SESSION['user_id']);
if (!$user) {
    header('Location: logout.php');
    exit;
}

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    header('Location: dashboard.php#mes-declarations');
    exit;
}

ensure_lost_item_recovery_schema();
$pdo = get_db_connection();
$stmt = $pdo->prepare("SELECT * FROM lost_items WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $user['id']]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: dashboard.php#mes-declarations');
    exit;
}

$document['date_naissance'] = samapiece_birth_date_decrypt($document['date_naissance'] ?? null);

$recovery_st = lost_item_recovery_status($document);

$page_title = 'Détails de la déclaration - Samapiece';
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
            --bg: #f2f7ff;
            --surface: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: radial-gradient(circle at top left, rgba(0,183,255,0.18), transparent 30%),
                        radial-gradient(circle at bottom right, rgba(14,165,233,0.08), transparent 35%),
                        var(--bg);
            color: var(--dark);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        .container {
            max-width: 720px;
            margin: 0 auto;
            padding: 16px;
        }

        .detail-panel {
            background: rgba(255,255,255,0.98);
            border: 1px solid rgba(255,255,255,0.75);
            border-radius: 20px;
            box-shadow: 0 18px 40px rgba(15,23,42,0.07);
            overflow: hidden;
        }

        .status-pill {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.88rem;
        }

        .status-pill--wait {
            background: #f1f5f9;
            color: #475569;
        }

        .status-pill--request {
            background: #fff7ed;
            color: #c2410c;
        }

        .status-pill--done {
            background: #f0fdf4;
            color: #15803d;
        }

        .hero-image {
            position: relative;
            min-height: 260px;
            overflow: hidden;
            background: linear-gradient(180deg, rgba(0,183,255,0.12), rgba(14,165,233,0.03));
        }

        .hero-image img {
            width: 100%;
            height: 260px;
            object-fit: cover;
            filter: saturate(1.02) brightness(1.02);
            transition: transform 0.4s ease;
        }

        .hero-image:hover img {
            transform: scale(1.03);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, transparent 50%, rgba(15,23,42,0.18) 100%);
        }

        .detail-content {
            padding: 24px 24px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .detail-main {
            display: grid;
            gap: 18px;
        }

        .detail-main h2 {
            font-size: clamp(1.75rem, 3vw, 2.4rem);
        }

        .detail-box {
            background: #f8fbff;
            border: 1px solid rgba(0,183,255,0.12);
            border-radius: 18px;
            padding: 18px;
        }

        .detail-box h3 {
            margin-bottom: 16px;
            color: var(--accent);
        }

        .detail-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .detail-item {
            background: white;
            border-radius: 18px;
            padding: 18px;
            border: 1px solid rgba(15,23,42,0.06);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.8);
        }

        .detail-item strong {
            display: block;
            margin-bottom: 10px;
            color: var(--muted);
            font-weight: 600;
        }

        .detail-item span {
            color: var(--dark);
            font-size: 1rem;
            line-height: 1.5;
        }

        .sidebar {
            display: grid;
            gap: 20px;
        }

        .sidebar-card {
            background: white;
            border-radius: 22px;
            padding: 24px;
            border: 1px solid rgba(15,23,42,0.08);
            box-shadow: 0 18px 40px rgba(0,0,0,0.04);
        }

        .sidebar-card h3 {
            margin-bottom: 16px;
        }

        .sidebar-card p {
            color: var(--muted);
            line-height: 1.7;
        }

        @media (max-width: 920px) {
            .detail-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 12px 18px 24px;
            }

            .detail-panel {
                max-width: min(100%, 400px);
                margin-left: auto;
                margin-right: auto;
                border-radius: 14px;
                box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
            }

            .hero-image {
                min-height: 140px;
            }

            .hero-image img {
                height: 140px;
            }

            .hero-image:hover img {
                transform: none;
            }

            .detail-content {
                padding: 12px 12px 14px;
                gap: 10px;
            }

            .detail-main {
                gap: 10px;
            }

            .detail-main h2 {
                font-size: 1.2rem;
                line-height: 1.25;
                word-break: break-word;
            }

            .detail-box {
                padding: 10px 12px;
                border-radius: 12px;
            }

            .detail-box h3 {
                margin-bottom: 8px;
                font-size: 0.85rem;
            }

            .detail-box p {
                font-size: 0.88rem;
                line-height: 1.45;
            }

            .detail-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .detail-item {
                padding: 8px 10px;
                border-radius: 10px;
            }

            .detail-item strong {
                margin-bottom: 3px;
                font-size: 0.68rem;
                letter-spacing: 0.02em;
            }

            .detail-item span {
                font-size: 0.88rem;
                line-height: 1.35;
            }

            .status-pill {
                padding: 4px 8px;
                font-size: 0.78rem;
            }
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main class="container" id="contenu-principal">
        <article class="detail-panel">
            <div class="hero-image">
                <?php if (!empty($document['photo1']) && file_exists($document['photo1'])): ?>
                    <img src="<?php echo htmlspecialchars($document['photo1']); ?>" alt="Photo du document">
                <?php else: ?>
                    <img src="https://via.placeholder.com/1200x420/0ea5e9/ffffff?text=Document+non+disponible" alt="Image non disponible">
                <?php endif; ?>
                <div class="hero-overlay"></div>
            </div>

            <div class="detail-content">
                <div class="detail-main">
                    <?php
                    $view_cat_raw = $document['categorie'] ?? '';
                    $view_cat_heading = $view_cat_raw !== '' ? lost_item_categorie_label($view_cat_raw) : 'Document déclaré';
                    ?>
                    <h2><?php echo htmlspecialchars($view_cat_heading); ?></h2>
                    <div class="detail-box">
                        <h3>Informations principales</h3>
                        <div class="detail-row">
                            <div class="detail-item">
                                <strong>Statut</strong>
                                <span class="status-pill status-pill--<?php echo $recovery_st === 'demande_recuperation' ? 'request' : ($recovery_st === 'recupere' ? 'done' : 'wait'); ?>">
                                    <?php echo htmlspecialchars(lost_item_recovery_status_label($recovery_st)); ?>
                                </span>
                            </div>
                            <div class="detail-item">
                                <strong>Nom</strong>
                                <span><?php echo htmlspecialchars($document['nom'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Prénom</strong>
                                <span><?php echo htmlspecialchars($document['prenom'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Date de naissance</strong>
                                <span><?php echo !empty($document['date_naissance']) ? date('d/m/Y', strtotime($document['date_naissance'])) : '-'; ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Lieu de naissance</strong>
                                <span><?php echo htmlspecialchars($document['lieu_naissance'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Téléphone</strong>
                                <span><?php echo htmlspecialchars($document['telephone'] ?? '-'); ?></span>
                            </div>
                            <div class="detail-item">
                                <strong>Date de déclaration</strong>
                                <span><?php echo !empty($document['date_declared']) ? date('d/m/Y H:i', strtotime($document['date_declared'])) : '-'; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="detail-box">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($document['description'] ?: 'Aucune description fournie.')); ?></p>
                    </div>

                    <?php if ($recovery_st === 'demande_recuperation'): ?>
                        <div class="detail-box">
                            <h3>Remise du document</h3>
                            <p style="color: var(--muted); font-size: 0.95rem;">Une personne a demandé la récupération. Remettez le document physiquement, puis confirmez la remise depuis le tableau de bord : saisissez le code à 8 caractères ou scannez le QR présenté par le demandeur (section « Confirmer la remise » sur la carte).</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
