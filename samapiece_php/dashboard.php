<?php
require_once 'functions.php';
require_login();

$user = get_user_by_id($_SESSION['user_id']);
if (!$user) {
    header('Location: logout.php');
    exit;
}

ensure_lost_item_recovery_schema();
$pdo = get_db_connection();
$stmt = $pdo->prepare(
    'SELECT li.*, req.nom AS requester_nom, req.prenom AS requester_prenom, req.telephone AS requester_telephone '
    . 'FROM lost_items li '
    . 'LEFT JOIN users req ON req.id = li.recovery_requester_id '
    . 'WHERE li.user_id = ? ORDER BY li.date_declared DESC'
);
$stmt->execute([$user['id']]);
$user_documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt_demandes = $pdo->prepare("SELECT COUNT(*) FROM lost_items WHERE user_id = ? AND recovery_status = 'demande_recuperation'");
$stmt_demandes->execute([$user['id']]);
$nb_demandes_recuperation = (int) $stmt_demandes->fetchColumn();

foreach ($user_documents as $idx => $row) {
    if (lost_item_recovery_status($row) === 'demande_recuperation' && empty($row['recovery_handover_code'])) {
        list($ok_h, $hc) = lost_item_ensure_handover_code($row['id']);
        if ($ok_h && $hc !== null) {
            $user_documents[$idx]['recovery_handover_code'] = $hc;
        }
    }
}

$stmt_my_req = $pdo->prepare(
    'SELECT li.*, u.prenom AS declarer_prenom, u.nom AS declarer_nom, u.telephone AS declarer_telephone '
    . 'FROM lost_items li LEFT JOIN users u ON u.id = li.user_id WHERE li.recovery_requester_id = ? ORDER BY li.recovery_requested_at DESC'
);
$stmt_my_req->execute([$user['id']]);
$recovery_requests = $stmt_my_req->fetchAll(PDO::FETCH_ASSOC);

$handover_flash = null;
if (!empty($_SESSION['handover_flash'])) {
    $handover_flash = $_SESSION['handover_flash'];
    unset($_SESSION['handover_flash']);
}

$recovery_request_flash = null;
if (!empty($_SESSION['recovery_flash'])) {
    $recovery_request_flash = $_SESSION['recovery_flash'];
    unset($_SESSION['recovery_flash']);
}

$page_title = 'Mon Dashboard - Samapiece';
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
            --green: #22c55e;
            --green-dark: #16a34a;
            --ink: #0a0a0a;
            --line: #e5e5e5;
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
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        main {
            padding: 32px 0 0;
        }

        .welcome-section {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            color: white;
            padding: clamp(12px, 2.5vw, 16px) clamp(14px, 3vw, 22px);
            border-radius: clamp(12px, 2vw, 16px);
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: clamp(10px, 2vw, 16px) clamp(16px, 3vw, 24px);
            width: 100%;
            min-width: 0;
            box-shadow: 0 12px 40px rgba(14, 165, 233, 0.22);
        }

        .welcome-section::before {
            content: "";
            position: absolute;
            top: -40%;
            right: -15%;
            width: min(55%, 280px);
            aspect-ratio: 1;
            background: radial-gradient(circle, rgba(255,255,255,0.22) 0%, transparent 70%);
            pointer-events: none;
        }

        .welcome-section > * {
            position: relative;
            z-index: 1;
            min-width: 0;
        }

        .welcome-intro {
            flex: 1 1 auto;
            min-width: 0;
        }

        @media (min-width: 521px) {
            .welcome-intro {
                flex: 1 1 200px;
            }
        }

        .welcome-stats {
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: clamp(10px, 2vw, 18px) clamp(14px, 2.5vw, 22px);
            margin-left: auto;
            flex: 1 1 auto;
            justify-content: flex-end;
            min-width: 0;
        }

        .welcome-stats .stats {
            margin-left: 0;
            flex: 0 1 auto;
            min-width: min(120px, 28vw);
        }

        .welcome-stats .stats + .stats {
            border-left: 1px solid rgba(255,255,255,0.35);
            padding-left: clamp(10px, 2vw, 16px);
        }

        .welcome-section .welcome-label {
            font-size: 0.72rem;
            opacity: 0.92;
            margin: 0 0 2px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .welcome-section h2 {
            margin: 0;
            font-size: clamp(1rem, 3.5vw, 1.15rem);
            font-weight: 700;
            line-height: 1.25;
            word-break: break-word;
        }

        .stats {
            text-align: right;
            min-width: 120px;
        }

        .stats h3 {
            margin: 0;
            font-size: clamp(1.15rem, 4vw, 1.4rem);
            font-weight: 800;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .stats .welcome-label {
            text-align: right;
        }

        @media (max-width: 520px) {
            main {
                padding: 16px 0 0;
            }

            .welcome-section {
                flex-direction: column;
                align-items: stretch;
                padding: 8px 10px;
                gap: 8px;
                margin-bottom: 12px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(14, 165, 233, 0.18);
            }

            .welcome-section::before {
                width: min(70%, 200px);
                top: -35%;
                right: -20%;
                opacity: 0.85;
            }

            .welcome-intro {
                flex: 0 0 auto;
            }

            .welcome-section .welcome-label {
                font-size: 0.58rem;
                margin-bottom: 1px;
                letter-spacing: 0.06em;
            }

            .welcome-section h2 {
                font-size: 0.95rem;
                line-height: 1.2;
            }

            .welcome-stats {
                margin-left: 0;
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 6px;
                align-items: stretch;
            }

            .welcome-stats .stats {
                text-align: center;
                min-width: 0;
                padding: 6px 6px;
                background: rgba(255,255,255,0.12);
                border-radius: 8px;
                border: 1px solid rgba(255,255,255,0.2);
            }

            .welcome-stats .stats + .stats {
                border-left: none;
                padding-left: 6px;
            }

            .welcome-stats .stats .welcome-label {
                text-align: center;
                font-size: 0.55rem;
                line-height: 1.2;
                margin-bottom: 2px;
            }

            .welcome-stats .stats h3 {
                font-size: 1.05rem;
                line-height: 1.15;
            }
        }

        .dashboard-cta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 14px;
            margin-bottom: 28px;
            justify-content: center;
        }

        .btn-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 28px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s, border-color 0.2s;
        }

        .btn-pill--primary {
            background: linear-gradient(135deg, var(--green) 0%, var(--green-dark) 100%);
            color: #fff;
            border: none;
            box-shadow: 0 10px 28px rgba(34, 197, 94, 0.35);
        }

        .btn-pill--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(34, 197, 94, 0.4);
        }

        .btn-pill--primary svg {
            flex-shrink: 0;
        }

        .btn-pill--secondary {
            background: var(--surface);
            color: var(--ink);
            border: 1px solid var(--line);
        }

        .btn-pill--secondary:hover {
            border-color: #d4d4d4;
            background: #fff;
            transform: translateY(-2px);
        }

        @media (max-width: 480px) {
            .btn-pill {
                width: 100%;
            }
        }

        .dashboard-section-tabs {
            scroll-margin-top: 88px;
        }

        .dashboard-tab-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
            align-items: center;
        }

        .dashboard-tab-btn {
            flex: 1;
            min-width: 0;
            padding: 12px 18px;
            border-radius: 999px;
            border: 2px solid #e5edf6;
            background: var(--surface);
            color: var(--dark);
            font-weight: 700;
            font-size: clamp(0.85rem, 2.5vw, 1rem);
            cursor: pointer;
            font-family: inherit;
            transition: background 0.2s, border-color 0.2s, color 0.2s, box-shadow 0.2s;
        }

        .dashboard-tab-btn:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }

        .dashboard-tab-btn.is-active {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            border-color: transparent;
            color: #fff;
            box-shadow: 0 8px 24px rgba(0, 183, 255, 0.25);
        }

        .dashboard-tab-panel {
            outline: none;
        }

        .dashboard-tab-panel[hidden] {
            display: none !important;
        }

        .declaration-card__media--static {
            cursor: default;
            text-decoration: none;
            color: inherit;
        }

        .declaration-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        @media (max-width: 640px) {
            .declaration-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                align-items: start;
            }
            .declaration-card {
                border-radius: 12px;
                box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
                align-self: start;
                height: auto;
            }
            .declaration-card:hover {
                transform: none;
            }
            .declaration-card__media {
                aspect-ratio: 3 / 2;
                padding: 6px 8px 8px;
                box-sizing: border-box;
            }

            .declaration-card__media img {
                border-radius: 8px;
            }

            .declaration-card__placeholder {
                font-size: 1.75rem;
                border-radius: 8px;
            }
            .declaration-card__badge {
                font-size: 0.58rem;
                padding: 4px 6px;
                top: 6px;
                left: 6px;
                max-width: calc(100% - 12px);
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .declaration-card__body {
                padding: 8px 8px 10px;
                gap: 4px;
            }
            .declaration-card__name {
                font-size: 0.72rem;
                font-weight: 700;
                line-height: 1.25;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            .declaration-card__status {
                font-size: 0.62rem;
            }
            .declaration-card__meta {
                font-size: 0.62rem;
            }
            .declaration-card__link {
                padding: 8px 6px;
                font-size: 0.68rem;
                border-radius: 8px;
            }
            .declaration-card__handover {
                margin-top: 4px;
            }
            .declaration-card__handover summary {
                font-size: 0.65rem;
                padding: 6px 4px;
            }
            .handover-form__row {
                flex-direction: column;
            }
            .handover-form__row input[type="text"] {
                min-width: 0;
            }
        }

        .flash-handover {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.95rem;
        }

        .flash-handover.ok {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .flash-handover.err {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .declaration-card__handover {
            margin-top: 6px;
            border-top: 1px solid #eef2f7;
            padding-top: 8px;
        }

        .declaration-card__handover summary {
            cursor: pointer;
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--accent-2);
            list-style: none;
        }

        .declaration-card__handover summary::-webkit-details-marker {
            display: none;
        }

        .handover-form {
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .handover-form__row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .handover-form input[type="text"] {
            flex: 1;
            min-width: 100px;
            padding: 8px 10px;
            border: 1px solid #d0d8e4;
            border-radius: 8px;
            font-size: 0.95rem;
            letter-spacing: 0.12em;
            font-family: ui-monospace, monospace;
        }

        .btn-handover-submit {
            padding: 8px 14px;
            border-radius: 8px;
            border: none;
            background: var(--green-dark);
            color: #fff;
            font-weight: 600;
            font-size: 0.85rem;
            cursor: pointer;
        }

        .btn-handover-scan {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid #e5edf6;
            background: #fff;
            font-weight: 600;
            font-size: 0.82rem;
            cursor: pointer;
        }

        .handover-modal {
            position: fixed;
            inset: 0;
            z-index: 300;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background: rgba(15, 23, 42, 0.45);
        }

        .handover-modal[hidden] {
            display: none;
        }

        .handover-modal__box {
            background: #fff;
            border-radius: 16px;
            padding: 16px;
            max-width: 400px;
            width: 100%;
        }

        .handover-modal__box h3 {
            margin: 0 0 12px;
            font-size: 1.1rem;
        }

        #handover-qr-reader {
            min-height: 200px;
        }

        .btn-handover-close {
            margin-top: 12px;
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #e5edf6;
            background: #f8fafc;
            font-weight: 600;
            cursor: pointer;
        }

        .declaration-card__qrbox {
            margin-top: 8px;
            border-top: 1px solid #eef2f7;
            padding-top: 8px;
        }

        .declaration-card__qrbox summary.declaration-card__link {
            list-style: none;
            cursor: pointer;
        }

        .declaration-card__qrbox summary.declaration-card__link::-webkit-details-marker {
            display: none;
        }

        .declaration-card__qrbox-inner {
            margin-top: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 12px;
            text-align: center;
        }

        .declaration-card__qrbox-inner img {
            width: 180px;
            height: 180px;
            max-width: 100%;
            border-radius: 10px;
            border: 1px solid #e5edf6;
        }

        .declaration-card__qrbox-code {
            margin-top: 12px;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.25em;
            font-family: ui-monospace, monospace;
            color: var(--dark);
        }

        @media (max-width: 640px) {
            .declaration-card__qrbox-inner img {
                width: 140px;
                height: 140px;
            }
            .declaration-card__qrbox-code {
                font-size: 1rem;
                letter-spacing: 0.15em;
            }
        }

        .declaration-card__declarer {
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.35;
            margin: 0;
        }

        .declaration-card__declarer strong {
            color: var(--dark);
            font-weight: 600;
        }

        .declaration-card__declarer a {
            color: var(--accent-2);
            text-decoration: none;
            font-weight: 600;
        }

        .declaration-card__declarer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .declaration-card__declarer {
                font-size: 0.65rem;
            }
        }

        .declaration-card__requester {
            font-size: 0.82rem;
            color: var(--muted);
            line-height: 1.4;
            margin: 10px 0 0;
            padding: 10px 12px;
            background: linear-gradient(135deg, rgba(240, 253, 244, 0.95) 0%, rgba(236, 253, 245, 0.6) 100%);
            border: 1px solid rgba(34, 197, 94, 0.22);
            border-radius: 10px;
        }

        .declaration-card__requester strong {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--green-dark);
            margin-bottom: 6px;
        }

        .declaration-card__requester span {
            display: block;
            color: var(--dark);
            font-weight: 600;
        }

        .declaration-card__requester .declaration-card__requester-line {
            font-weight: 500;
            color: var(--muted);
            font-size: 0.8rem;
            margin-top: 4px;
        }

        @media (max-width: 640px) {
            .declaration-card__requester {
                font-size: 0.78rem;
                padding: 8px 10px;
            }
        }

        .declaration-card {
            background: var(--surface);
            border: 1px solid #e5edf6;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(15, 23, 42, 0.07);
            display: flex;
            flex-direction: column;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .declaration-card:hover {
            box-shadow: 0 14px 40px rgba(15, 23, 42, 0.1);
            transform: translateY(-2px);
        }

        .declaration-card__media {
            display: block;
            position: relative;
            aspect-ratio: 4 / 3;
            background: linear-gradient(145deg, #f0f9ff 0%, #e0f2fe 100%);
            overflow: hidden;
        }

        .declaration-card__media img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .declaration-card__placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #94a3b8;
            background: #f1f5f9;
        }

        .declaration-card__badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(15, 23, 42, 0.82);
            color: #fff;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 6px 10px;
            border-radius: 999px;
        }

        .declaration-card__body {
            padding: 18px 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }

        .declaration-card__name {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1.3;
        }

        .declaration-card__meta {
            font-size: 0.88rem;
            color: var(--muted);
        }

        .declaration-card__meta--recupere {
            margin-top: 6px;
            color: var(--green-dark);
            font-weight: 600;
        }

        .declaration-card__status {
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .declaration-card__status--wait {
            color: #64748b;
        }

        .declaration-card__status--request {
            color: #c2410c;
        }

        .declaration-card__status--done {
            color: #15803d;
        }

        .declaration-card__actions {
            margin-top: auto;
            padding-top: 4px;
        }

        .declaration-card__link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.92rem;
            text-decoration: none;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            color: #fff;
            transition: filter 0.2s, transform 0.15s;
        }

        .declaration-card__link:hover {
            filter: brightness(1.05);
            transform: translateY(-1px);
        }

        .declaration-empty {
            background: var(--surface);
            border: 1px dashed #cbd5e1;
            border-radius: 16px;
            padding: 36px 24px;
            text-align: center;
        }

        .declaration-empty h3 {
            font-size: 1.15rem;
            margin: 0 0 10px;
            color: var(--dark);
        }

        .declaration-empty p {
            color: var(--muted);
            margin: 0 0 18px;
            font-size: 0.95rem;
        }

        .declaration-empty a {
            color: var(--accent-2);
            font-weight: 600;
            text-decoration: none;
        }

        .declaration-empty a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main>
        <div class="container">
            <?php if ($handover_flash): ?>
                <div class="flash-handover <?php echo !empty($handover_flash['ok']) ? 'ok' : 'err'; ?>" role="alert">
                    <?php echo htmlspecialchars($handover_flash['message']); ?>
                </div>
            <?php endif; ?>
            <?php if ($recovery_request_flash): ?>
                <div class="flash-handover <?php echo !empty($recovery_request_flash['ok']) ? 'ok' : 'err'; ?>" role="alert">
                    <?php echo htmlspecialchars($recovery_request_flash['message']); ?>
                </div>
            <?php endif; ?>
            <section class="welcome-section" aria-label="Résumé de votre compte">
                <div class="welcome-intro">
                    <p class="welcome-label">Bienvenue</p>
                    <h2><?php echo htmlspecialchars($user['prenom'] . ' ' . $user['nom']); ?></h2>
                </div>
                <div class="welcome-stats">
                    <div class="stats">
                        <p class="welcome-label">Documents déclarés</p>
                        <h3><?php echo count($user_documents); ?></h3>
                    </div>
                    <div class="stats">
                        <p class="welcome-label">Demandes de récupération</p>
                        <h3><?php echo $nb_demandes_recuperation; ?></h3>
                    </div>
                </div>
            </section>

            <div class="dashboard-cta">
                <a href="search.php" class="btn-pill btn-pill--primary" aria-label="Recherche">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                    Recherche
                </a>
                <a href="declare.php" class="btn-pill btn-pill--secondary">
                    Déclarer
                    <span aria-hidden="true">→</span>
                </a>
            </div>

            <section class="dashboard-section-tabs" id="dashboard-listes-documents">
                <div class="dashboard-tab-bar" role="tablist" aria-label="Vos listes">
                    <button type="button" class="dashboard-tab-btn is-active" role="tab" id="tab-mes-declarations" aria-selected="true" aria-controls="mes-declarations" data-tab="mes-declarations">Mes déclarations</button>
                    <button type="button" class="dashboard-tab-btn" role="tab" id="tab-mes-demandes-recuperation" aria-selected="false" aria-controls="mes-demandes-recuperation" data-tab="mes-demandes-recuperation">Mes demandes de récupération</button>
                </div>

                <div id="mes-declarations" class="dashboard-tab-panel dashboard-declarations" role="tabpanel" aria-labelledby="tab-mes-declarations">
                <?php if (empty($user_documents)): ?>
                    <div class="declaration-empty">
                        <h3>Aucune déclaration pour l’instant</h3>
                        <p>Dès que vous déclarez un document perdu, il apparaîtra ici sous forme de carte.</p>
                        <a href="declare.php">Déclarer un document</a>
                    </div>
                <?php else: ?>
                    <div class="declaration-cards">
                        <?php foreach ($user_documents as $document):
                            $photo_rel = $document['photo1'] ?? '';
                            $photo_ok = $photo_rel !== '' && is_file(__DIR__ . '/' . $photo_rel);
                            $nom_complet = trim(($document['prenom'] ?? '') . ' ' . ($document['nom'] ?? ''));
                            if ($nom_complet === '') {
                                $nom_complet = 'Document';
                            }
                            $cat_label = $document['categorie'] ?? 'Document';
                            $cat_labels = [
                                'carte_identite' => 'Carte d’identité',
                                'passeport' => 'Passeport',
                                'permis_conduire' => 'Permis de conduire',
                                'carte_vitale' => 'Carte Vitale',
                                'autre' => 'Autre',
                            ];
                            if (isset($cat_labels[$cat_label])) {
                                $cat_label = $cat_labels[$cat_label];
                            }
                            $date_decl = !empty($document['date_declared'])
                                ? date('d/m/Y', strtotime($document['date_declared']))
                                : '—';
                            $view_url = 'view_declaration.php?id=' . urlencode($document['id']);
                            $rst = lost_item_recovery_status($document);
                            $status_class = $rst === 'demande_recuperation' ? 'request' : ($rst === 'recupere' ? 'done' : 'wait');
                            ?>
                            <article class="declaration-card">
                                <a href="<?php echo htmlspecialchars($view_url); ?>" class="declaration-card__media">
                                    <?php if ($photo_ok): ?>
                                        <img src="<?php echo htmlspecialchars($photo_rel); ?>" alt="">
                                    <?php else: ?>
                                        <div class="declaration-card__placeholder" aria-hidden="true">📄</div>
                                    <?php endif; ?>
                                    <span class="declaration-card__badge"><?php echo htmlspecialchars($cat_label); ?></span>
                                </a>
                                <div class="declaration-card__body">
                                    <p class="declaration-card__name"><?php echo htmlspecialchars($nom_complet); ?></p>
                                    <p class="declaration-card__status declaration-card__status--<?php echo htmlspecialchars($status_class); ?>">
                                        <?php echo htmlspecialchars(lost_item_recovery_status_label($rst)); ?>
                                    </p>
                                    <p class="declaration-card__meta">Déclaré le <?php echo htmlspecialchars($date_decl); ?></p>
                                    <?php
                                    $show_requester = ($rst === 'demande_recuperation' || $rst === 'recupere')
                                        && trim((string) ($document['recovery_requester_id'] ?? '')) !== '';
                                    if ($show_requester):
                                        $rq_prenom = trim((string) ($document['requester_prenom'] ?? ''));
                                        $rq_nom = trim((string) ($document['requester_nom'] ?? ''));
                                        $rq_nom_complet = trim($rq_prenom . ' ' . $rq_nom);
                                        if ($rq_nom_complet === '') {
                                            $rq_nom_complet = 'Non renseigné';
                                        }
                                        $rq_tel = trim((string) ($document['requester_telephone'] ?? ''));
                                        if ($rq_tel === '') {
                                            $rq_tel = '—';
                                        }
                                        $date_recup_txt = '';
                                        if ($rst === 'recupere' && !empty($document['recovery_handover_at'])) {
                                            $ts_r = strtotime($document['recovery_handover_at']);
                                            $date_recup_txt = date('d/m/Y', $ts_r) . ' à ' . date('H:i', $ts_r);
                                        }
                                        ?>
                                        <div class="declaration-card__requester">
                                            <strong>Demande de récupération</strong>
                                            <span><?php echo htmlspecialchars($rq_nom_complet); ?></span>
                                            <p class="declaration-card__requester-line">Téléphone : <?php echo htmlspecialchars($rq_tel); ?></p>
                                            <?php if ($date_recup_txt !== ''): ?>
                                                <p class="declaration-card__requester-line" style="color:var(--green-dark);font-weight:600;">Récupéré le <?php echo htmlspecialchars($date_recup_txt); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="declaration-card__actions">
                                        <a href="<?php echo htmlspecialchars($view_url); ?>" class="declaration-card__link">Voir le détail</a>
                                    </div>
                                    <?php if ($rst === 'demande_recuperation'):
                                        $hid = md5($document['id']);
                                        ?>
                                        <details class="declaration-card__handover">
                                            <summary>Confirmer la remise</summary>
                                            <form class="handover-form" method="post" action="confirm_handover.php" data-lost-id="<?php echo htmlspecialchars($document['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="lost_item_id" value="<?php echo htmlspecialchars($document['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <p style="font-size:0.8rem;color:var(--muted);margin:0;">Saisissez le code à 8 caractères montré par la personne ou scannez son QR.</p>
                                                <div class="handover-form__row">
                                                    <input type="text" name="code" id="handover-code-<?php echo $hid; ?>" maxlength="12" autocomplete="off" placeholder="Code" pattern="[A-Za-z0-9]{8}" title="8 caractères" required>
                                                    <button type="submit" class="btn-handover-submit">Valider</button>
                                                </div>
                                                <button type="button" class="btn-handover-scan" data-target="handover-code-<?php echo $hid; ?>">Scanner le QR</button>
                                            </form>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>

                <div id="mes-demandes-recuperation" class="dashboard-tab-panel dashboard-declarations" role="tabpanel" aria-labelledby="tab-mes-demandes-recuperation" hidden>
                <?php if (empty($recovery_requests)): ?>
                    <div class="declaration-empty">
                        <h3>Aucune demande</h3>
                        <p>Après une recherche, utilisez « Demander la récupération » sur un résultat pour retrouver le document ici.</p>
                        <a href="search.php">Rechercher un document</a>
                    </div>
                <?php else: ?>
                    <div class="declaration-cards">
                        <?php foreach ($recovery_requests as $document):
                            $photo_rel = $document['photo1'] ?? '';
                            $photo_ok = $photo_rel !== '' && is_file(__DIR__ . '/' . $photo_rel);
                            $nom_complet = trim(($document['prenom'] ?? '') . ' ' . ($document['nom'] ?? ''));
                            if ($nom_complet === '') {
                                $nom_complet = 'Document';
                            }
                            $cat_label = $document['categorie'] ?? 'Document';
                            $cat_labels = [
                                'carte_identite' => 'Carte d’identité',
                                'passeport' => 'Passeport',
                                'permis_conduire' => 'Permis de conduire',
                                'carte_vitale' => 'Carte Vitale',
                                'autre' => 'Autre',
                            ];
                            if (isset($cat_labels[$cat_label])) {
                                $cat_label = $cat_labels[$cat_label];
                            }
                            $date_decl = !empty($document['date_declared'])
                                ? date('d/m/Y', strtotime($document['date_declared']))
                                : '—';
                            $rst = lost_item_recovery_status($document);
                            $status_class = $rst === 'demande_recuperation' ? 'request' : ($rst === 'recupere' ? 'done' : 'wait');
                            $hc = '';
                            $qr_img = '';
                            $handover_url = '';
                            if ($rst === 'demande_recuperation') {
                                list($ok_h, $hc) = lost_item_ensure_handover_code($document['id']);
                                if ($ok_h && $hc) {
                                    $handover_url = recovery_handover_public_url($document['id'], $hc);
                                    $qr_img = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&ecc=M&data=' . rawurlencode($handover_url);
                                }
                            }
                            ?>
                            <article class="declaration-card">
                                <div class="declaration-card__media declaration-card__media--static">
                                    <?php if ($photo_ok): ?>
                                        <img src="<?php echo htmlspecialchars($photo_rel); ?>" alt="">
                                    <?php else: ?>
                                        <div class="declaration-card__placeholder" aria-hidden="true">📄</div>
                                    <?php endif; ?>
                                    <span class="declaration-card__badge"><?php echo htmlspecialchars($cat_label); ?></span>
                                </div>
                                <div class="declaration-card__body">
                                    <p class="declaration-card__name"><?php echo htmlspecialchars($nom_complet); ?></p>
                                    <?php
                                    $decl_nom = trim(($document['declarer_prenom'] ?? '') . ' ' . ($document['declarer_nom'] ?? ''));
                                    $decl_tel = trim((string) ($document['declarer_telephone'] ?? ''));
                                    ?>
                                    <p class="declaration-card__declarer">
                                        <strong>Déclarant :</strong>
                                        <?php echo $decl_nom !== '' ? htmlspecialchars($decl_nom) : '—'; ?>
                                        <?php if ($decl_tel !== ''): ?>
                                            <br><strong>Tél. :</strong> <a href="tel:<?php echo htmlspecialchars(preg_replace('/\s+/', '', $decl_tel)); ?>"><?php echo htmlspecialchars($decl_tel); ?></a>
                                        <?php else: ?>
                                            <br><strong>Tél. :</strong> —
                                        <?php endif; ?>
                                    </p>
                                    <p class="declaration-card__status declaration-card__status--<?php echo htmlspecialchars($status_class); ?>">
                                        <?php echo htmlspecialchars(lost_item_recovery_status_label($rst)); ?>
                                    </p>
                                    <p class="declaration-card__meta">Déclaré le <?php echo htmlspecialchars($date_decl); ?></p>
                                    <?php
                                    $date_recup_demande = '';
                                    if ($rst === 'recupere' && !empty($document['recovery_handover_at'])) {
                                        $ts_rec = strtotime($document['recovery_handover_at']);
                                        $date_recup_demande = date('d/m/Y', $ts_rec) . ' à ' . date('H:i', $ts_rec);
                                    }
                                    ?>
                                    <?php if ($date_recup_demande !== ''): ?>
                                        <p class="declaration-card__meta declaration-card__meta--recupere">Récupéré le <?php echo htmlspecialchars($date_recup_demande); ?></p>
                                    <?php endif; ?>
                                    <div class="declaration-card__actions">
                                        <?php if ($rst === 'demande_recuperation' && $hc !== '' && $qr_img !== ''): ?>
                                            <details class="declaration-card__qrbox">
                                                <summary class="declaration-card__link">Code &amp; QR de remise</summary>
                                                <div class="declaration-card__qrbox-inner">
                                                    <img src="<?php echo htmlspecialchars($qr_img); ?>" width="200" height="200" alt="QR code de remise">
                                                    <p class="declaration-card__qrbox-code"><?php echo htmlspecialchars($hc); ?></p>
                                                    <p style="font-size:0.78rem;color:var(--muted);margin:8px 0 0;">À présenter au déclarant pour qu’il confirme la remise.</p>
                                                </div>
                                            </details>
                                        <?php elseif ($rst === 'recupere'): ?>
                                            <p style="font-size:0.85rem;color:var(--muted);margin:0;">Remise confirmée par le déclarant.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            </section>
        </div>

        <div id="handover-scan-modal" class="handover-modal" hidden>
            <div class="handover-modal__box">
                <h3>Scanner le QR</h3>
                <p style="font-size:0.85rem;color:var(--muted);margin:0 0 10px;">Autorisez la caméra si demandé. Le code sera rempli automatiquement.</p>
                <div id="handover-qr-reader"></div>
                <button type="button" class="btn-handover-close" id="handover-scan-close">Fermer</button>
            </div>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
    (function () {
        function initDashboardTabs() {
            var buttons = document.querySelectorAll('.dashboard-tab-btn');
            var byTab = {
                'mes-declarations': document.getElementById('mes-declarations'),
                'mes-demandes-recuperation': document.getElementById('mes-demandes-recuperation')
            };
            function activate(tab) {
                buttons.forEach(function (btn) {
                    var on = btn.getAttribute('data-tab') === tab;
                    btn.classList.toggle('is-active', on);
                    btn.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                Object.keys(byTab).forEach(function (key) {
                    var p = byTab[key];
                    if (!p) return;
                    p.hidden = key !== tab;
                });
                if (window.history && window.history.replaceState) {
                    window.history.replaceState(null, '', '#' + tab);
                }
            }
            buttons.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    activate(btn.getAttribute('data-tab'));
                });
            });
            var h = (window.location.hash || '').replace(/^#/, '');
            if (h === 'mes-demandes-recuperation') {
                activate('mes-demandes-recuperation');
            } else {
                activate('mes-declarations');
            }
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initDashboardTabs);
        } else {
            initDashboardTabs();
        }
    })();
    </script>
    <script>
    (function () {
        var modal = document.getElementById('handover-scan-modal');
        var readerEl = document.getElementById('handover-qr-reader');
        var closeBtn = document.getElementById('handover-scan-close');
        var scanner = null;
        var activeInput = null;

        function stopScanner() {
            function finish() {
                scanner = null;
                if (readerEl) readerEl.innerHTML = '';
                if (modal) modal.hidden = true;
            }
            if (scanner && typeof scanner.clear === 'function') {
                scanner.clear().then(finish).catch(finish);
            } else {
                finish();
            }
        }

        function extractCode(text) {
            var raw = (text || '').replace(/\s/g, '');
            if (/^[A-Za-z0-9]{8}$/.test(raw)) {
                return raw;
            }
            try {
                var u = new URL(text);
                var c = u.searchParams.get('c');
                if (c) return decodeURIComponent(c);
            } catch (e) {}
            var m = text.match(/[?&]c=([^&]+)/);
            return m ? decodeURIComponent(m[1]) : null;
        }

        function startScanner() {
            if (!readerEl) return;
            readerEl.innerHTML = '';
            modal.hidden = false;
            scanner = new Html5QrcodeScanner('handover-qr-reader', { fps: 10, qrbox: { width: 220, height: 220 } }, false);
            scanner.render(
                function onSuccess(decodedText) {
                    var ex = extractCode(decodedText);
                    var code = ex || (decodedText || '').replace(/\s/g, '');
                    code = code.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 8);
                    if (activeInput && code.length === 8) {
                        activeInput.value = code;
                    }
                    stopScanner();
                },
                function onError() {}
            );
        }

        document.querySelectorAll('.btn-handover-scan').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tid = btn.getAttribute('data-target');
                activeInput = tid ? document.getElementById(tid) : null;
                if (!readerEl || typeof Html5QrcodeScanner === 'undefined') {
                    alert('Scanner indisponible. Saisissez le code manuellement.');
                    return;
                }
                if (scanner && typeof scanner.clear === 'function') {
                    scanner.clear().then(startScanner).catch(startScanner);
                } else {
                    startScanner();
                }
            });
        });

        if (closeBtn) closeBtn.addEventListener('click', stopScanner);
        if (modal) modal.addEventListener('click', function (e) {
            if (e.target === modal) stopScanner();
        });
    })();
    </script>
</body>
</html>
