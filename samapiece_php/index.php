<?php
require_once 'functions.php';
$page_title = 'Accueil - Samapiece';
$has_hero_image = app_home_hero_available();
$hero_image_url = APP_HOME_HERO_URL;

$stats_total_declared = 0;
$stats_recovered = 0;
try {
    ensure_lost_item_recovery_schema();
    $pdo = get_db_connection();
    $stats_total_declared = (int) $pdo->query('SELECT COUNT(*) FROM lost_items')->fetchColumn();
    $stats_recovered = (int) $pdo->query("SELECT COUNT(*) FROM lost_items WHERE recovery_status = 'recupere'")->fetchColumn();
} catch (Throwable $e) {
    /* affichage 0 si indisponible */
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Samapiece — retrouver ou déclarer un document officiel perdu.">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php require __DIR__ . '/includes/head_favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --green: #128c7e;
            --green-dark: #0e6b62;
            --green-soft: #e8f5f3;
            --ink: #0a0a0a;
            --muted: #6b7280;
            --line: #e5e5e5;
            --surface: #ffffff;
            --bg: #fafafa;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        html, body {
            font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--ink);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        .skip-link {
            position: absolute;
            left: -9999px;
            top: 0;
            z-index: 200;
            padding: 12px 16px;
            background: var(--ink);
            color: #fff;
        }
        .skip-link:focus {
            left: 12px;
            top: 12px;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 clamp(16px, 4vw, 32px);
        }

        .home-hero {
            display: grid;
            grid-template-columns: 1fr 1.05fr;
            gap: clamp(28px, 5vw, 56px);
            align-items: center;
            padding: clamp(28px, 6vw, 72px) 0 clamp(24px, 4vw, 40px);
            min-height: min(88vh, 900px);
        }

        .home-hero__title {
            font-size: clamp(2rem, 5vw, 3.25rem);
            font-weight: 700;
            line-height: 1.12;
            letter-spacing: -0.03em;
            color: var(--ink);
            margin-bottom: clamp(16px, 3vw, 22px);
        }
        .home-hero__title .accent {
            background: linear-gradient(120deg, var(--green) 0%, var(--green-dark) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .home-hero__lead {
            font-size: clamp(0.95rem, 2vw, 1.1rem);
            color: var(--muted);
            max-width: 36ch;
            margin-bottom: clamp(22px, 4vw, 32px);
        }
        .home-hero__cta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 14px;
            margin-bottom: clamp(22px, 4vw, 28px);
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
            box-shadow: 0 10px 28px rgba(18, 140, 126, 0.35);
        }
        .btn-pill--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(18, 140, 126, 0.4);
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
        .home-hero__checks {
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: clamp(16px, 3vw, 20px) clamp(18px, 3vw, 22px);
            max-width: 400px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.04);
        }
        .home-hero__checks ul {
            list-style: none;
            display: grid;
            gap: 12px;
        }
        .home-hero__checks li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.9rem;
            color: #374151;
        }
        .home-hero__checks .check {
            flex-shrink: 0;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--green-soft);
            color: var(--green-dark);
            display: grid;
            place-items: center;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 1px;
        }

        .home-hero__visual {
            position: relative;
            z-index: 0;
        }
        .home-hero__visual::before,
        .home-hero__visual::after {
            content: "";
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 0;
            filter: blur(52px);
            opacity: 0.85;
        }
        .home-hero__visual::before {
            width: min(200px, 48vw);
            height: min(200px, 48vw);
            top: -8%;
            right: -2%;
            background: radial-gradient(circle, rgba(18, 140, 126, 0.55) 0%, transparent 68%);
            animation: hero-orb-a 16s ease-in-out infinite;
        }
        .home-hero__visual::after {
            width: min(160px, 42vw);
            height: min(160px, 42vw);
            bottom: -12%;
            left: -4%;
            background: radial-gradient(circle, rgba(34, 211, 238, 0.45) 0%, transparent 70%);
            animation: hero-orb-b 20s ease-in-out infinite;
        }
        @keyframes hero-orb-a {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.75; }
            50% { transform: translate(-6%, 8%) scale(1.08); opacity: 1; }
        }
        @keyframes hero-orb-b {
            0%, 100% { transform: translate(0, 0) scale(1); opacity: 0.65; }
            50% { transform: translate(10%, -6%) scale(1.12); opacity: 0.95; }
        }

        .home-hero__frame {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: clamp(10px, 2vw, 18px);
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow:
                0 24px 48px rgba(0, 0, 0, 0.06),
                0 0 0 1px rgba(255, 255, 255, 0.6) inset;
            overflow: hidden;
            animation: hero-frame-glow 5s ease-in-out infinite;
        }
        @keyframes hero-frame-glow {
            0%, 100% {
                box-shadow:
                    0 24px 48px rgba(0, 0, 0, 0.06),
                    0 0 0 1px rgba(255, 255, 255, 0.6) inset,
                    0 0 28px rgba(18, 140, 126, 0.12);
            }
            50% {
                box-shadow:
                    0 28px 52px rgba(0, 0, 0, 0.07),
                    0 0 0 1px rgba(255, 255, 255, 0.65) inset,
                    0 0 40px rgba(18, 140, 126, 0.22),
                    0 0 80px rgba(34, 211, 238, 0.08);
            }
        }
        /* Coins type HUD — au-dessus du média */
        .home-hero__frame::before {
            content: "";
            position: absolute;
            top: clamp(8px, 1.5vw, 14px);
            left: clamp(8px, 1.5vw, 14px);
            width: 32px;
            height: 32px;
            border-left: 2px solid var(--green);
            border-top: 2px solid var(--green);
            border-radius: 6px 0 0 0;
            pointer-events: none;
            z-index: 4;
            opacity: 0.9;
            box-shadow: 0 0 12px rgba(18, 140, 126, 0.35);
            animation: hero-corner-flicker 4s ease-in-out infinite;
        }
        .home-hero__frame::after {
            content: "";
            position: absolute;
            bottom: clamp(8px, 1.5vw, 14px);
            right: clamp(8px, 1.5vw, 14px);
            width: 32px;
            height: 32px;
            border-right: 2px solid var(--green);
            border-bottom: 2px solid var(--green);
            border-radius: 0 0 6px 0;
            pointer-events: none;
            z-index: 4;
            opacity: 0.9;
            box-shadow: 0 0 12px rgba(34, 211, 238, 0.3);
            animation: hero-corner-flicker 4s ease-in-out infinite 0.5s;
        }
        @keyframes hero-corner-flicker {
            0%, 100% { opacity: 0.75; filter: brightness(1); }
            50% { opacity: 1; filter: brightness(1.15); }
        }

        .home-hero__frame-shimmer {
            position: absolute;
            inset: clamp(10px, 2vw, 18px);
            border-radius: 16px;
            pointer-events: none;
            z-index: 2;
            overflow: hidden;
            background: linear-gradient(
                105deg,
                transparent 0%,
                transparent 42%,
                rgba(255, 255, 255, 0.55) 50%,
                transparent 58%,
                transparent 100%
            );
            background-size: 220% 100%;
            background-position: 0% 50%;
            animation: hero-shimmer 7s ease-in-out infinite;
            opacity: 0.22;
            mix-blend-mode: soft-light;
        }
        @keyframes hero-shimmer {
            0%, 15% { background-position: 0% 50%; opacity: 0.12; }
            45%, 55% { background-position: 100% 50%; opacity: 0.28; }
            85%, 100% { background-position: 0% 50%; opacity: 0.12; }
        }

        .home-hero__frame img,
        .home-hero__placeholder {
            position: relative;
            z-index: 1;
        }
        .home-hero__frame img {
            display: block;
            width: 100%;
            height: auto;
            max-height: min(52vh, 520px);
            border-radius: 16px;
            object-fit: contain;
            object-position: center;
        }
        .home-hero__placeholder {
            aspect-ratio: 4/3;
            border-radius: 16px;
            background: linear-gradient(145deg, var(--green-soft) 0%, #ecf8f6 50%, #e8f5f3 100%);
            display: grid;
            place-items: center;
            color: var(--green-dark);
            font-weight: 600;
            font-size: 0.9rem;
            text-align: center;
            padding: 24px;
        }

        @media (prefers-reduced-motion: reduce) {
            .home-hero__visual::before,
            .home-hero__visual::after {
                animation: none;
                opacity: 0.5;
            }
            .home-hero__frame {
                animation: none;
            }
            .home-hero__frame::before,
            .home-hero__frame::after {
                animation: none;
            }
            .home-hero__frame-shimmer {
                animation: none;
                opacity: 0.08;
            }
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .home-trust {
            padding: clamp(28px, 5vw, 48px) 0 clamp(8px, 2vw, 16px);
        }
        .home-trust__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: clamp(16px, 3vw, 24px);
        }
        .home-trust__card {
            background: linear-gradient(165deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: clamp(20px, 3.5vw, 28px);
            box-shadow: 0 8px 32px rgba(15, 23, 42, 0.05);
            display: grid;
            gap: 12px;
            min-height: 0;
        }
        .home-trust__icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--green-soft) 0%, #ecf8f6 100%);
            color: var(--green-dark);
            display: grid;
            place-items: center;
        }
        .home-trust__icon svg {
            width: 22px;
            height: 22px;
        }
        .home-trust__card h3 {
            font-size: clamp(1.05rem, 2vw, 1.2rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--ink);
        }
        .home-trust__card p {
            font-size: 0.92rem;
            color: var(--muted);
            line-height: 1.65;
        }

        .home-stats {
            position: relative;
            padding: clamp(28px, 5vw, 48px) 0 clamp(36px, 6vw, 64px);
        }
        .home-stats::before {
            content: "";
            position: absolute;
            left: 50%;
            top: 0;
            transform: translateX(-50%);
            width: min(120px, 40%);
            height: 4px;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, var(--green), var(--green-dark), transparent);
            opacity: 0.85;
        }
        .home-stats__heading {
            font-size: clamp(1.2rem, 2.8vw, 1.45rem);
            font-weight: 700;
            text-align: center;
            letter-spacing: -0.02em;
            margin-bottom: clamp(22px, 4vw, 32px);
            color: var(--ink);
            padding-top: 8px;
        }
        .home-stats__grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: clamp(14px, 3vw, 22px);
            width: 100%;
            max-width: min(760px, 100%);
            margin: 0 auto;
            align-items: stretch;
        }
        .home-stats__tile {
            position: relative;
            overflow: hidden;
            border-radius: 22px;
            padding: clamp(20px, 4vw, 30px);
            text-align: center;
            min-height: 0;
        }
        .home-stats__tile > * {
            position: relative;
            z-index: 1;
        }
        /* Tuile 1 — blanche, bordure et ombre douces */
        .home-stats__tile--light {
            background: linear-gradient(165deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow:
                0 4px 6px -1px rgba(15, 23, 42, 0.06),
                0 18px 48px rgba(18, 140, 126, 0.08),
                inset 0 1px 0 rgba(255, 255, 255, 0.9);
        }
        .home-stats__tile--light::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green), #7ccfc4, var(--green-dark));
            border-radius: 22px 22px 0 0;
        }
        .home-stats__tile--light::after {
            content: "";
            position: absolute;
            bottom: -30%;
            right: -25%;
            width: 55%;
            aspect-ratio: 1;
            background: radial-gradient(circle, rgba(18, 140, 126, 0.09) 0%, transparent 68%);
            pointer-events: none;
        }
        .home-stats__tile--light .home-stats__value {
            font-size: clamp(2rem, 5.5vw, 2.85rem);
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.04em;
            line-height: 1.08;
            margin-bottom: 10px;
            color: #0f172a;
            -webkit-text-fill-color: #0f172a;
            background: none;
        }
        .home-stats__tile--light .home-stats__label {
            font-size: 0.86rem;
            font-weight: 600;
            color: #64748b;
            line-height: 1.45;
            max-width: 18ch;
            margin: 0 auto;
        }
        /* Tuile 2 — verte */
        .home-stats__tile--green {
            background: linear-gradient(145deg, #128c7e 0%, #107c71 42%, #0e6b62 100%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow:
                0 12px 32px rgba(18, 140, 126, 0.35),
                0 2px 0 rgba(255, 255, 255, 0.15) inset;
        }
        .home-stats__tile--green::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -30%;
            width: 70%;
            aspect-ratio: 1;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.22) 0%, transparent 65%);
            pointer-events: none;
        }
        .home-stats__tile--green::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 40%;
            background: linear-gradient(0deg, rgba(0, 0, 0, 0.08) 0%, transparent 100%);
            pointer-events: none;
            border-radius: 0 0 22px 22px;
        }
        .home-stats__tile--green .home-stats__value {
            font-size: clamp(2rem, 5.5vw, 2.85rem);
            font-weight: 800;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.04em;
            line-height: 1.08;
            margin-bottom: 10px;
            color: #fff;
            text-shadow: 0 2px 16px rgba(0, 0, 0, 0.12);
            -webkit-text-fill-color: #fff;
            background: none;
        }
        .home-stats__tile--green .home-stats__label {
            font-size: 0.86rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.92);
            line-height: 1.45;
            max-width: 18ch;
            margin: 0 auto;
        }

        /* Bureau / tablette large — lecture confortable, image et stats proportionnées */
        @media (min-width: 901px) {
            .home-hero {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1.06fr);
                gap: clamp(40px, 5vw, 72px);
                align-items: center;
                min-height: min(82vh, 860px);
                padding: clamp(32px, 5vw, 64px) 0 clamp(28px, 4vw, 48px);
            }
            .home-hero__copy {
                max-width: 36rem;
                min-width: 0;
            }
            .home-hero__title {
                font-size: clamp(2.6rem, 3.2vw, 3.5rem);
            }
            .home-hero__lead {
                max-width: 44ch;
                font-size: clamp(1.02rem, 1.1vw, 1.12rem);
            }
            .home-hero__checks {
                max-width: 28rem;
            }
            .home-hero__visual {
                max-width: min(100%, 640px);
                margin-left: auto;
                width: 100%;
            }
            .home-hero__frame img {
                max-height: min(62vh, 600px);
            }
            .home-trust__grid {
                gap: clamp(22px, 2.5vw, 32px);
            }
            .home-trust__card p {
                font-size: 0.95rem;
            }
            .home-stats__grid {
                max-width: min(880px, 100%);
                gap: clamp(20px, 2.5vw, 28px);
            }
            .home-stats__tile--light .home-stats__label,
            .home-stats__tile--green .home-stats__label {
                max-width: 22ch;
                font-size: 0.9rem;
            }
        }

        @media (min-width: 1280px) {
            .home-hero {
                gap: clamp(48px, 4vw, 80px);
            }
        }

        @media (max-width: 900px) {
            .home-hero {
                grid-template-columns: 1fr;
                min-height: unset;
            }
            .home-hero__visual {
                order: -1;
                width: 100%;
                max-width: 560px;
                margin: 0 auto;
            }
            .home-hero__lead {
                max-width: none;
            }
            .home-hero__checks {
                max-width: none;
            }
            .home-hero__frame img {
                max-height: min(42vh, 380px);
            }
            .home-trust__grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 480px) {
            .btn-pill {
                width: 100%;
            }
            .home-hero__frame img {
                max-height: min(38vh, 320px);
            }
            .home-stats__grid {
                gap: 10px;
            }
            .home-stats__tile {
                padding: 16px 10px;
            }
            .home-stats__value {
                font-size: clamp(1.45rem, 7vw, 2rem);
            }
            .home-stats__label {
                font-size: 0.72rem;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#contenu-principal">Aller au contenu</a>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main id="contenu-principal">
        <div class="container">
            <section class="home-hero" aria-labelledby="home-title">
                <div class="home-hero__copy">
                    <h1 id="home-title" class="home-hero__title">
                        Retrouvez votre document <span class="accent">perdu</span>
                    </h1>
                    <p class="home-hero__lead">Une plateforme simple pour chercher un document officiel ou en signaler un que vous avez trouvé.</p>
                    <div class="home-hero__cta">
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
                    <div class="home-hero__checks">
                        <ul>
                            <li><span class="check" aria-hidden="true">✓</span> Recherche par vos propres informations</li>
                            <li><span class="check" aria-hidden="true">✓</span> Déclaration sécurisée avec compte</li>
                            <li><span class="check" aria-hidden="true">✓</span> Alerte possible si aucun résultat</li>
                        </ul>
                    </div>
                </div>
                <div class="home-hero__visual">
                    <div class="home-hero__frame">
                        <span class="home-hero__frame-shimmer" aria-hidden="true"></span>
                        <?php if ($has_hero_image): ?>
                            <img src="<?php echo htmlspecialchars($hero_image_url); ?>"
                                 width="1200"
                                 height="800"
                                 alt="Illustration Samapiece"
                                 loading="eager"
                                 decoding="async"
                                 sizes="(max-width: 900px) 100vw, min(640px, 50vw)">
                        <?php else: ?>
                            <div class="home-hero__placeholder">Image indisponible (img/acceuil.png)</div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="home-trust" aria-labelledby="home-trust-title">
                <h2 id="home-trust-title" class="sr-only">Pourquoi Samapiece</h2>
                <div class="home-trust__grid">
                    <article class="home-trust__card">
                        <div class="home-trust__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <h3>Viabilité &amp; engagement</h3>
                        <p>Samapiece relie les personnes qui ont perdu un document et celles qui en ont retrouvé un. Chaque déclaration enrichit la base : plus de fiches, plus de chances de retrouver une pièce d’identité, un passeport ou un titre officiel.</p>
                    </article>
                    <article class="home-trust__card">
                        <div class="home-trust__icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <h3>Sécurité des données</h3>
                        <p>Connexion par compte, déclarations associées à votre profil et remise en mains propres avec code de vérification lorsqu’un document est réclamé. Vos informations servent la recherche tout en limitant l’exposition inutile.</p>
                    </article>
                </div>
            </section>

            <section class="home-stats" aria-labelledby="home-stats-title">
                <h2 id="home-stats-title" class="home-stats__heading">La plateforme en un coup d’œil</h2>
                <div class="home-stats__grid">
                    <div class="home-stats__tile home-stats__tile--light">
                        <p class="home-stats__value"><?php echo number_format($stats_total_declared, 0, ',', ' '); ?></p>
                        <p class="home-stats__label">Documents déclarés sur Samapiece</p>
                    </div>
                    <div class="home-stats__tile home-stats__tile--green">
                        <p class="home-stats__value"><?php echo number_format($stats_recovered, 0, ',', ' '); ?></p>
                        <p class="home-stats__label">Remises après recherche sur la plateforme</p>
                    </div>
                </div>
            </section>

        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
