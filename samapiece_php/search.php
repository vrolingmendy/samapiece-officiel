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
    header('Location: ' . samapiece_absolute_url('dashboard.php#mes-recuperations'));
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
            --green: #22c55e;
            --green-dark: #15803d;
            --green-soft: #ecfdf5;
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
            position: relative;
            margin-bottom: clamp(28px, 5vw, 44px);
            padding: 0;
            border-radius: 22px;
            overflow: hidden;
            background:
                linear-gradient(145deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 252, 255, 1) 38%, rgba(236, 253, 245, 0.55) 100%);
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.9) inset,
                0 20px 50px rgba(15, 23, 42, 0.07),
                0 4px 20px rgba(0, 183, 255, 0.06);
        }

        .search-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green) 0%, var(--accent-2) 50%, var(--accent) 100%);
            opacity: 0.95;
        }

        .search-section__bg {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            border-radius: inherit;
        }

        .search-section__bg::after {
            content: "";
            position: absolute;
            width: min(420px, 90vw);
            height: min(420px, 90vw);
            right: -18%;
            top: -35%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 183, 255, 0.12) 0%, transparent 68%);
        }

        .search-section__bg::before {
            content: "";
            position: absolute;
            width: 280px;
            height: 280px;
            left: -8%;
            bottom: -25%;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.1) 0%, transparent 70%);
        }

        .search-section__inner {
            position: relative;
            z-index: 1;
            padding: clamp(22px, 4vw, 38px) clamp(20px, 4vw, 40px) clamp(26px, 4.5vw, 42px);
        }

        .search-section__head {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: clamp(14px, 3vw, 22px);
            align-items: center;
            margin-bottom: clamp(18px, 3vw, 26px);
        }

        @media (max-width: 540px) {
            .search-section__head {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }
        }

        .search-section__icon {
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

        .search-section__head h2 {
            margin: 0;
            min-width: 0;
            font-size: clamp(1.35rem, 3.2vw, 1.85rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            color: var(--dark);
            line-height: 1.15;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
            gap: clamp(14px, 2.5vw, 20px);
            align-items: end;
        }

        .search-section .form-group {
            flex: 1;
            min-width: 0;
        }

        .search-section .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: 600;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            color: var(--dark);
        }

        .search-section .form-group input,
        .search-section .form-group select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 12px;
            font-size: 16px;
            background: var(--surface);
            color: var(--dark);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-section .form-group input::placeholder {
            color: rgba(66, 84, 102, 0.55);
        }

        .search-section .form-group input:hover,
        .search-section .form-group select:hover {
            border-color: rgba(0, 183, 255, 0.35);
        }

        .search-section .form-group input:focus,
        .search-section .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 183, 255, 0.18);
        }

        .search-form .form-group--submit {
            display: flex;
            align-items: flex-end;
        }

        @media (max-width: 639px) {
            .search-form .form-group--submit {
                grid-column: 1 / -1;
            }
        }

        .search-btn {
            width: 100%;
            min-height: 48px;
            padding: 14px 22px;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-2) 100%);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.02em;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.25s ease, filter 0.2s ease;
            box-shadow:
                0 4px 14px rgba(0, 183, 255, 0.35),
                0 1px 0 rgba(255, 255, 255, 0.25) inset;
        }

        .search-btn:hover {
            filter: brightness(1.05);
            transform: translateY(-2px);
            box-shadow:
                0 12px 28px rgba(14, 165, 233, 0.38),
                0 1px 0 rgba(255, 255, 255, 0.25) inset;
        }

        .search-btn:active {
            transform: translateY(0);
        }

        .results-section {
            position: relative;
            padding: clamp(22px, 4vw, 42px);
            border-radius: 20px;
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            overflow-wrap: anywhere;
            word-break: break-word;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.97) 0%, rgba(248, 252, 255, 0.99) 45%, rgba(240, 253, 244, 0.35) 100%);
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.85) inset,
                0 20px 50px rgba(15, 23, 42, 0.07),
                0 4px 16px rgba(14, 165, 233, 0.06);
        }

        .results-section::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 20px 20px 0 0;
            background: linear-gradient(90deg, var(--green) 0%, var(--accent-2) 50%, var(--accent) 100%);
            opacity: 0.92;
        }

        .results-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px 20px;
            margin-bottom: clamp(18px, 3vw, 26px);
            padding-bottom: clamp(16px, 2.5vw, 22px);
            border-bottom: 1px solid var(--line);
        }

        .results-header__title {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .results-header h2 {
            margin: 0;
            font-size: clamp(1.35rem, 2.8vw, 1.75rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--dark);
            line-height: 1.2;
        }

        .results-header__subtitle {
            margin: 0;
            font-size: 0.88rem;
            color: var(--muted);
            font-weight: 500;
        }

        .results-count {
            margin: 0;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--green-dark);
            background: linear-gradient(145deg, var(--green-soft) 0%, #d1fae5 100%);
            border: 1px solid rgba(34, 197, 94, 0.28);
            box-shadow: 0 2px 10px rgba(34, 197, 94, 0.12);
        }

        .results-count__num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.75rem;
            height: 1.75rem;
            padding: 0 6px;
            border-radius: 10px;
            background: #fff;
            color: var(--green-dark);
            font-variant-numeric: tabular-nums;
        }

        .results-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .item-card {
            position: relative;
            display: flex;
            flex-wrap: wrap;
            align-items: stretch;
            gap: clamp(16px, 3vw, 24px);
            padding: clamp(18px, 3vw, 24px);
            border-radius: 18px;
            transition: transform 0.22s ease, box-shadow 0.22s ease;
            background: linear-gradient(165deg, #ffffff 0%, #f8fafc 55%, #f0fdf4 130%);
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .item-card::before {
            content: "";
            position: absolute;
            left: 0;
            top: 12%;
            bottom: 12%;
            width: 4px;
            border-radius: 0 4px 4px 0;
            background: linear-gradient(180deg, var(--accent) 0%, var(--green) 100%);
            opacity: 0.85;
        }

        .item-card:hover {
            transform: translateY(-3px);
            box-shadow:
                0 16px 40px rgba(15, 23, 42, 0.09),
                0 6px 20px rgba(14, 165, 233, 0.08);
        }

        .item-card__main {
            flex: 1 1 260px;
            min-width: 0;
            padding-left: 8px;
        }

        .item-card__thumb {
            flex: 0 0 176px;
            width: 176px;
            max-width: min(100%, 240px);
            border-radius: 14px;
            overflow: hidden;
            align-self: center;
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(160deg, #f8fafc 0%, #f0f9ff 40%, #ecfdf5 100%);
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.9) inset,
                0 10px 28px rgba(15, 23, 42, 0.08);
        }

        .item-card__thumb-inner {
            width: 100%;
            height: 100%;
            min-height: 120px;
            max-height: 152px;
            overflow: hidden;
            border-radius: 12px;
        }

        .item-card__thumb img {
            width: 100%;
            height: 100%;
            min-height: 120px;
            max-height: 152px;
            object-fit: cover;
            display: block;
            transition: transform 0.35s ease;
        }

        .item-card:hover .item-card__thumb img {
            transform: scale(1.04);
        }

        .item-card__thumb-placeholder {
            font-size: 2.5rem;
            opacity: 0.4;
            user-select: none;
            filter: grayscale(0.2);
        }

        @media (max-width: 640px) {
            .item-card {
                flex-direction: column;
            }
            .item-card::before {
                top: 0;
                bottom: auto;
                left: 12px;
                right: 12px;
                width: auto;
                height: 4px;
                border-radius: 4px 4px 0 0;
            }
            .item-card__main {
                padding-left: 0;
            }
            .item-card__thumb {
                flex: none;
                width: 100%;
                max-width: none;
                order: -1;
                min-height: 160px;
            }
            .item-card__thumb-inner {
                max-height: 220px;
                min-height: 160px;
            }
            .item-card__thumb img {
                max-height: 220px;
            }
        }

        .item-title {
            font-size: clamp(1.15rem, 2.2vw, 1.35rem);
            font-weight: 800;
            margin: 0 0 10px;
            letter-spacing: -0.02em;
            background: linear-gradient(115deg, var(--dark) 0%, #0c4a6e 45%, var(--accent-2) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            color: var(--dark);
        }

        @supports not (-webkit-background-clip: text) {
            .item-title {
                background: none;
                -webkit-text-fill-color: unset;
                color: var(--dark);
            }
        }

        .item-description {
            color: var(--muted);
            margin: 0 0 14px;
            font-size: 0.95rem;
            line-height: 1.5;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(248, 250, 252, 0.85);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        .item-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px 10px;
            margin-bottom: 16px;
        }

        .item-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .item-chip--cat {
            color: #0c4a6e;
            background: linear-gradient(135deg, #e0f2fe 0%, #f0f9ff 100%);
            border: 1px solid rgba(14, 165, 233, 0.25);
        }

        .item-chip--date {
            color: #475569;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
        }

        .item-chip__icon {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .no-results {
            text-align: center;
            padding: 28px 20px 20px;
            color: var(--muted);
            border-radius: 14px;
            background: linear-gradient(180deg, rgba(248, 250, 252, 0.9) 0%, rgba(241, 245, 249, 0.5) 100%);
            border: 1px dashed rgba(148, 163, 184, 0.45);
        }

        .no-results p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.55;
        }

        /* ——— Layout « 0 résultat + alerte e-mail » ——— */
        .results-reminder-split {
            display: grid;
            grid-template-columns: 1fr;
            gap: clamp(20px, 4vw, 32px);
            align-items: stretch;
            width: 100%;
            min-width: 0;
        }

        @media (min-width: 960px) {
            .results-reminder-split {
                grid-template-columns: minmax(0, 1.15fr) minmax(300px, 420px);
                gap: clamp(24px, 3vw, 40px);
                align-items: start;
            }
        }

        .results-block--empty {
            min-width: 0;
        }

        .empty-state {
            position: relative;
            text-align: center;
            padding: clamp(28px, 5vw, 44px) clamp(20px, 4vw, 36px);
            border-radius: 22px;
            overflow: hidden;
            background:
                radial-gradient(ellipse 80% 60% at 50% -10%, rgba(14, 165, 233, 0.12) 0%, transparent 55%),
                radial-gradient(ellipse 70% 50% at 100% 80%, rgba(34, 197, 94, 0.1) 0%, transparent 50%),
                linear-gradient(165deg, #ffffff 0%, #f8fafc 40%, #f0f9ff 100%);
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.9) inset,
                0 18px 48px rgba(15, 23, 42, 0.07);
        }

        .empty-state__glow {
            position: absolute;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.2) 0%, transparent 70%);
            top: -60px;
            left: 50%;
            transform: translateX(-50%);
            pointer-events: none;
            filter: blur(2px);
        }

        .empty-state__icon-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: clamp(72px, 14vw, 96px);
            height: clamp(72px, 14vw, 96px);
            margin: 0 auto 18px;
            border-radius: 22px;
            background: linear-gradient(145deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid rgba(14, 165, 233, 0.25);
            box-shadow: 0 10px 28px rgba(14, 165, 233, 0.15);
            font-size: clamp(2rem, 5vw, 2.75rem);
            line-height: 1;
        }

        .empty-state__badge {
            display: inline-block;
            margin-bottom: 14px;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #64748b;
            background: rgba(241, 245, 249, 0.95);
            border: 1px solid #e2e8f0;
        }

        .empty-state__title {
            margin: 0 0 12px;
            font-size: clamp(1.25rem, 3vw, 1.55rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--dark);
            line-height: 1.25;
        }

        .empty-state__lead {
            margin: 0 auto;
            max-width: 42ch;
            font-size: clamp(0.92rem, 2vw, 1.02rem);
            line-height: 1.6;
            color: var(--muted);
        }

        .empty-state__hint {
            margin: 20px 0 0;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.88rem;
            color: #0369a1;
            background: linear-gradient(135deg, rgba(224, 242, 254, 0.9) 0%, rgba(240, 249, 255, 0.7) 100%);
            border: 1px solid rgba(14, 165, 233, 0.22);
        }

        .empty-state__hint--solo {
            color: #065f46;
            background: linear-gradient(135deg, rgba(209, 250, 229, 0.95) 0%, rgba(236, 253, 245, 0.8) 100%);
            border-color: rgba(16, 185, 129, 0.35);
        }

        .reminder-panel--featured {
            position: relative;
            margin-top: 0;
            padding: 0;
            max-width: none;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 22px;
            overflow: hidden;
            background:
                linear-gradient(135deg, rgba(255, 255, 255, 0.97) 0%, rgba(248, 252, 255, 0.99) 45%, rgba(240, 253, 244, 0.35) 100%);
            color: var(--dark);
            box-shadow:
                0 1px 0 rgba(255, 255, 255, 0.85) inset,
                0 12px 40px rgba(15, 23, 42, 0.06);
        }

        @media (min-width: 960px) {
            .reminder-panel--featured {
                position: sticky;
                top: max(88px, env(safe-area-inset-top, 0px));
            }
        }

        .reminder-panel--featured::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-radius: 22px 22px 0 0;
            background: linear-gradient(90deg, var(--green) 0%, var(--accent-2) 50%, var(--accent) 100%);
            opacity: 0.92;
        }

        .reminder-panel__inner {
            position: relative;
            z-index: 1;
            padding: clamp(22px, 4vw, 30px);
        }

        .reminder-panel__kicker {
            display: inline-block;
            margin-bottom: 10px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 0.65rem;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--accent-2);
            background: rgba(0, 183, 255, 0.1);
            border: 1px solid rgba(0, 183, 255, 0.22);
        }

        .reminder-panel--featured h3 {
            margin: 0 0 18px;
            font-size: clamp(1.2rem, 2.5vw, 1.45rem);
            font-weight: 800;
            letter-spacing: -0.02em;
            color: var(--dark);
            line-height: 1.25;
        }

        .reminder-panel--featured .reminder-form label {
            color: var(--dark);
            font-weight: 500;
        }

        .reminder-panel--featured .reminder-form input,
        .reminder-panel--featured .reminder-form select {
            background: var(--surface);
            border: 1px solid #e2e8f0;
            color: var(--dark);
            border-radius: 10px;
        }

        .reminder-panel--featured .reminder-form input::placeholder {
            color: rgba(66, 84, 102, 0.75);
        }

        .reminder-panel--featured .reminder-form input:focus,
        .reminder-panel--featured .reminder-form select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(0, 183, 255, 0.2);
        }

        .reminder-panel--featured .reminder-submit {
            width: 100%;
            padding: 15px 22px;
            border-radius: 12px;
            background: var(--accent);
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 10px 28px rgba(0, 183, 255, 0.28);
        }

        .reminder-panel--featured .reminder-submit:hover {
            background: var(--accent-2);
            transform: translateY(-1px);
            box-shadow: 0 12px 32px rgba(14, 165, 233, 0.32);
        }

        .results-section.has-reminder {
            padding-bottom: clamp(28px, 5vw, 48px);
        }

        @media (max-width: 959px) {
            /* Formulaire d’alerte en premier sur mobile (action prioritaire) */
            .results-reminder-split .reminder-panel--featured {
                order: 1;
            }
            .results-reminder-split .results-block--empty {
                order: 2;
            }
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

        .reminder-panel:not(.reminder-panel--featured) {
            margin-top: 24px;
            padding: clamp(18px, 3vw, 24px);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: linear-gradient(180deg, #f8fafc 0%, #fff 48%);
            text-align: left;
            max-width: 720px;
        }
        .reminder-panel:not(.reminder-panel--featured) h3 {
            font-size: 1.15rem;
            color: var(--dark);
            margin-bottom: 8px;
        }
        .reminder-panel:not(.reminder-panel--featured) .reminder-intro {
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

        .item-recovery {
            margin-top: 4px;
            padding-top: 14px;
            border-top: 1px dashed rgba(148, 163, 184, 0.45);
        }

        .item-recovery-status {
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--muted);
            margin: 0 0 8px;
            padding: 10px 12px;
            border-radius: 10px;
            background: rgba(248, 250, 252, 0.9);
            border: 1px solid rgba(226, 232, 240, 0.9);
        }

        .item-recovery-status.is-pending {
            color: #9a3412;
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            border-color: rgba(251, 146, 60, 0.35);
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

    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main>
        <div class="container">
            <section class="search-section" aria-labelledby="search-heading">
                <div class="search-section__bg" aria-hidden="true"></div>
                <div class="search-section__inner">
                    <header class="search-section__head">
                        <div class="search-section__icon" aria-hidden="true">🔎</div>
                        <h2 id="search-heading">Rechercher un objet perdu</h2>
                    </header>
                    <form method="GET" class="search-form" role="search">
                        <div class="form-group">
                            <label for="nom">Nom</label>
                            <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($nom); ?>" placeholder="Nom de famille" autocomplete="family-name">
                        </div>
                        <div class="form-group">
                            <label for="prenom">Prénom</label>
                            <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($prenom); ?>" placeholder="Prénom" autocomplete="given-name">
                        </div>
                        <div class="form-group">
                            <label for="date_naissance">Date de naissance</label>
                            <input type="date" id="date_naissance" name="date_naissance" value="<?php echo htmlspecialchars($date_naissance); ?>">
                        </div>
                        <div class="form-group">
                            <label for="lieu_naissance">Lieu de naissance</label>
                            <input type="text" id="lieu_naissance" name="lieu_naissance" value="<?php echo htmlspecialchars($lieu_naissance); ?>" placeholder="Ville, pays" autocomplete="off">
                        </div>
                        <div class="form-group form-group--submit">
                            <button type="submit" class="search-btn">Lancer la recherche</button>
                        </div>
                    </form>
                </div>
            </section>

            <?php if ($search_executed):
                ensure_lost_item_recovery_schema();
                $show_reminder_form = empty($search_results) && !($reminder_flash && !empty($reminder_flash['ok']));
                $results_section_class = 'results-section' . ($show_reminder_form ? ' has-reminder' : '');
                ?>
            <section class="<?php echo htmlspecialchars($results_section_class, ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($recovery_flash): ?>
                    <div class="reminder-flash <?php echo !empty($recovery_flash['ok']) ? 'ok' : 'err'; ?>" role="alert">
                        <?php echo htmlspecialchars($recovery_flash['message']); ?>
                    </div>
                <?php endif; ?>
                <?php if ($reminder_flash): ?>
                    <div class="reminder-flash <?php echo $reminder_flash['ok'] ? 'ok' : 'err'; ?>" role="alert">
                        <?php echo htmlspecialchars($reminder_flash['message']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($search_results)): ?>
                <header class="results-header">
                    <div class="results-header__title">
                        <h2>Résultats de recherche</h2>
                        <p class="results-header__subtitle">Fiches correspondant à vos critères</p>
                    </div>
                    <p class="results-count" role="status">
                        <span class="results-count__num"><?php echo (int) count($search_results); ?></span>
                        résultat<?php echo count($search_results) !== 1 ? 's' : ''; ?> trouvé<?php echo count($search_results) !== 1 ? 's' : ''; ?>
                    </p>
                </header>
                <?php endif; ?>
                <?php if (empty($search_results)): ?>
                    <div class="results-reminder-split">
                    <div class="results-block results-block--empty">
                        <div class="empty-state">
                            <div class="empty-state__glow" aria-hidden="true"></div>
                            <div class="empty-state__icon-wrap" aria-hidden="true"><span>🔍</span></div>
                            <p class="empty-state__badge">Aucune fiche pour cette recherche</p>
                            <h3 class="empty-state__title">Pas de correspondance pour l’instant</h3>
                            <p class="empty-state__lead">Vos critères sont pris en compte. Activez une alerte e-mail pour être prévenu dès qu’un document correspondra à cette recherche.</p>
                            <?php if ($show_reminder_form): ?>
                                <p class="empty-state__hint">Le panneau d’alerte reprend votre recherche — vous pouvez ajuster les champs avant d’enregistrer.</p>
                            <?php elseif ($reminder_flash && !empty($reminder_flash['ok'])): ?>
                                <p class="empty-state__hint empty-state__hint--solo">Votre alerte est enregistrée. Nous vous écrirons dès qu’une fiche correspondra — vous pouvez aussi lancer une nouvelle recherche ci-dessus.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($show_reminder_form): ?>
                    <aside class="reminder-panel reminder-panel--featured" aria-labelledby="reminder-heading">
                        <div class="reminder-panel__inner">
                            <span class="reminder-panel__kicker">Alerte e-mail</span>
                            <h3 id="reminder-heading">Me prévenir si une fiche correspond</h3>
                            <form method="post" class="reminder-form" action="<?php echo htmlspecialchars(samapiece_url('search.php'), ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="action" value="create_search_reminder">
                            <input type="hidden" name="reminder_categorie" value="<?php echo htmlspecialchars($categorie); ?>">
                            <div class="form-group-full">
                                <label for="notify_email">E-mail pour l’alerte *</label>
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
                            <div class="reminder-submit-wrap">
                                <button type="submit" class="reminder-submit">Enregistrer mon alerte</button>
                            </div>
                        </form>
                        </div>
                    </aside>
                    <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="results-block results-list" style="grid-column:1/-1;">
                    <?php foreach ($search_results as $item):
                        $rst = lost_item_recovery_status($item);
                        $is_owner = is_logged_in() && (string) ($item['user_id'] ?? '') === (string) ($_SESSION['user_id'] ?? '');
                        $can_request_recovery = is_logged_in() && !$is_owner && $rst === 'en_attente';
                        ?>
                        <?php
                        $item_photo = $item['photo1'] ?? '';
                        $item_photo_ok = $item_photo !== '' && is_file(__DIR__ . '/' . $item_photo);
                        $item_cat = $item['categorie'] ?? '';
                        $item_cat_display = $item_cat !== '' ? lost_item_categorie_label($item_cat) : 'Non spécifiée';
                        $item_dd_raw = $item['date_declared'] ?? '';
                        $item_dd_disp = '—';
                        if ($item_dd_raw !== '') {
                            $item_dd_ts = strtotime($item_dd_raw);
                            if ($item_dd_ts !== false) {
                                $item_dd_disp = date('d/m/Y', $item_dd_ts) . ' · ' . date('H:i', $item_dd_ts);
                            }
                        }
                        ?>
                        <div class="item-card">
                            <div class="item-card__main">
                                <div class="item-title"><?php echo htmlspecialchars(($item['prenom'] ?? '') . ' ' . ($item['nom'] ?? '')); ?></div>
                                <div class="item-description">
                                    Né(e) le <?php echo htmlspecialchars($item['date_naissance'] ?? ''); ?> à <?php echo htmlspecialchars($item['lieu_naissance'] ?? ''); ?>
                                </div>
                                <div class="item-meta">
                                    <span class="item-chip item-chip--cat"><span class="item-chip__icon" aria-hidden="true">📋</span><?php echo htmlspecialchars($item_cat_display); ?></span>
                                    <span class="item-chip item-chip--date"><span class="item-chip__icon" aria-hidden="true">📅</span>Déclaré le <?php echo htmlspecialchars($item_dd_disp); ?></span>
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
                            <div class="item-card__thumb" aria-hidden="<?php echo $item_photo_ok ? 'false' : 'true'; ?>">
                                <?php if ($item_photo_ok): ?>
                                    <div class="item-card__thumb-inner">
                                        <img src="<?php echo htmlspecialchars($item_photo); ?>" alt="Photo du document (recto)" loading="lazy" decoding="async" width="176" height="132">
                                    </div>
                                <?php else: ?>
                                    <span class="item-card__thumb-placeholder" title="Photo non disponible">📄</span>
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