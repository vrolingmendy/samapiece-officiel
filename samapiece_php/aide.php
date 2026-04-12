<?php
require_once __DIR__ . '/functions.php';
$page_title = 'Aide & FAQ - Samapiece';
$privacy_url = samapiece_privacy_policy_url();
$contact_email = APP_CONTACT_EMAIL;
$contact_mailto = 'mailto:' . rawurlencode($contact_email) . '?subject=' . rawurlencode('Question sur Samapiece');
$company_url = APP_COMPANY_SITE_URL;
$company_name = APP_COMPANY_NAME;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Aide, questions fréquentes et contact — <?php echo htmlspecialchars(APP_NAME); ?>.">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php require __DIR__ . '/includes/head_favicon.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --green: #22c55e;
            --green-dark: #16a34a;
            --green-soft: #f0fdf4;
            --card: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 50%, #ecfdf5 100%);
            color: var(--ink);
            line-height: 1.6;
            min-height: 100vh;
        }
        .help-wrap {
            max-width: 800px;
            margin: 0 auto;
            padding: clamp(20px, 4vw, 40px) clamp(16px, 3vw, 24px) clamp(48px, 8vw, 72px);
        }
        .help-hero {
            position: relative;
            border-radius: 20px;
            padding: clamp(24px, 4vw, 36px) clamp(22px, 3vw, 32px);
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid var(--line);
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(255, 255, 255, 0.8) inset;
            margin-bottom: clamp(28px, 5vw, 40px);
            overflow: hidden;
        }
        .help-hero::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green), #34d399, #0ea5e9);
        }
        .help-hero::after {
            content: "";
            position: absolute;
            top: -40%;
            right: -15%;
            width: 45%;
            aspect-ratio: 1;
            background: radial-gradient(circle, rgba(34, 197, 94, 0.12) 0%, transparent 70%);
            pointer-events: none;
        }
        .help-hero-inner { position: relative; z-index: 1; }
        .help-kicker {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--green-dark);
            margin-bottom: 10px;
        }
        .help-hero h1 {
            font-size: clamp(1.45rem, 3.2vw, 1.85rem);
            font-weight: 700;
            letter-spacing: -0.03em;
            line-height: 1.2;
            margin-bottom: 12px;
            color: var(--ink);
        }
        .help-hero p {
            font-size: 0.95rem;
            color: var(--muted);
            max-width: 52ch;
        }
        .help-hero .help-company {
            margin-top: 16px;
            font-size: 0.88rem;
            color: #475569;
        }
        .help-hero .help-company a {
            color: var(--green-dark);
            font-weight: 600;
            text-decoration: none;
            border-bottom: 1px solid rgba(22, 163, 74, 0.35);
        }
        .help-hero .help-company a:hover {
            border-bottom-color: var(--green-dark);
        }
        .help-contact {
            border-radius: 16px;
            padding: clamp(20px, 3vw, 26px);
            background: linear-gradient(145deg, var(--green-soft) 0%, #ecfdf5 100%);
            border: 1px solid rgba(34, 197, 94, 0.25);
            margin-bottom: clamp(28px, 4vw, 36px);
            display: grid;
            gap: 12px;
            align-items: start;
        }
        @media (min-width: 560px) {
            .help-contact {
                grid-template-columns: auto 1fr;
                align-items: center;
            }
        }
        .help-contact-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--green) 0%, var(--green-dark) 100%);
            color: #fff;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            box-shadow: 0 8px 24px rgba(22, 163, 74, 0.35);
        }
        .help-contact-icon svg { width: 24px; height: 24px; }
        .help-contact h2 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .help-contact p {
            font-size: 0.9rem;
            color: #475569;
            margin-bottom: 10px;
        }
        .help-contact a.mail {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--green-dark);
            text-decoration: none;
            padding: 10px 16px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid rgba(34, 197, 94, 0.35);
            box-shadow: 0 4px 14px rgba(34, 197, 94, 0.12);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .help-contact a.mail:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(34, 197, 94, 0.2);
        }
        .help-section-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--muted);
            margin-bottom: 14px;
        }
        .help-faq {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .help-faq details {
            border-radius: 14px;
            background: var(--card);
            border: 1px solid var(--line);
            box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04);
            overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .help-faq details[open] {
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
        }
        .help-faq summary {
            list-style: none;
            cursor: pointer;
            padding: 16px 18px;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--ink);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            -webkit-user-select: none;
            user-select: none;
        }
        .help-faq summary::-webkit-details-marker { display: none; }
        .help-faq summary::after {
            content: "";
            width: 10px;
            height: 10px;
            border-right: 2px solid var(--muted);
            border-bottom: 2px solid var(--muted);
            transform: rotate(45deg);
            flex-shrink: 0;
            margin-top: -4px;
            transition: transform 0.2s;
        }
        .help-faq details[open] summary::after {
            transform: rotate(-135deg);
            margin-top: 4px;
        }
        .help-faq .faq-body {
            padding: 0 18px 18px;
            font-size: 0.92rem;
            color: #475569;
            line-height: 1.65;
        }
        .help-faq .faq-body a {
            color: var(--green-dark);
            font-weight: 600;
        }
        .help-footer-note {
            margin-top: clamp(32px, 5vw, 44px);
            padding-top: 20px;
            border-top: 1px solid var(--line);
            font-size: 0.85rem;
            color: var(--muted);
            text-align: center;
        }
        .help-footer-note a {
            color: var(--green-dark);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main id="contenu-principal">
        <div class="help-wrap">
            <header class="help-hero">
                <div class="help-hero-inner">
                    <p class="help-kicker">Centre d’aide</p>
                    <h1>Besoin d’aide sur <?php echo htmlspecialchars(APP_NAME); ?> ?</h1>
                    <p>Retrouvez les réponses aux questions les plus fréquentes et les moyens pour nous contacter.</p>
                    <p class="help-company">
                        <?php echo htmlspecialchars(APP_NAME); ?> est une application de l’entreprise
                        <a href="<?php echo htmlspecialchars($company_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($company_name); ?></a>
                        · <a href="<?php echo htmlspecialchars($company_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">goo-bridge.com</a>
                    </p>
                </div>
            </header>

            <section class="help-contact" aria-labelledby="help-contact-title">
                <div class="help-contact-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                </div>
                <div>
                    <h2 id="help-contact-title">Nous écrire</h2>
                    <p>Pour toute question sur le service, une difficulté technique ou une demande liée à votre compte, écrivez-nous : nous vous répondrons dans les meilleurs délais.</p>
                    <a class="mail" href="<?php echo htmlspecialchars($contact_mailto, ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($contact_email); ?>
                    </a>
                </div>
            </section>

            <p class="help-section-title" id="faq-title">Questions fréquentes</p>
            <div class="help-faq" role="region" aria-labelledby="faq-title">
                <details>
                    <summary>Comment signaler un utilisateur ou un comportement inapproprié ?</summary>
                    <div class="faq-body">
                        Si vous constatez un abus, une arnaque ou un comportement contraire aux règles du service, contactez-nous à
                        <a href="<?php echo htmlspecialchars($contact_mailto, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email); ?></a>
                        en décrivant la situation (dates, éléments visibles sur la plateforme, captures d’écran si utiles). Nous examinons chaque signalement dans le respect de la confidentialité.
                    </div>
                </details>
                <details>
                    <summary>Mes données sont-elles sécurisées ?</summary>
                    <div class="faq-body">
                        Nous mettons en œuvre des mesures techniques et organisationnelles pour protéger vos informations (connexion sécurisée, bonnes pratiques d’hébergement et de traitement). Le détail figure dans notre
                        <a href="<?php echo htmlspecialchars($privacy_url, ENT_QUOTES, 'UTF-8'); ?>">politique de confidentialité</a>.
                    </div>
                </details>
                <details>
                    <summary>Puis-je demander la suppression de mon compte ?</summary>
                    <div class="faq-body">
                        Oui. Vous pouvez demander la suppression de votre compte ou l’exercice de vos droits sur vos données en nous écrivant à
                        <a href="<?php echo htmlspecialchars($contact_mailto, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email); ?></a>.
                        Indiquez l’adresse e-mail associée à votre compte ; nous traiterons votre demande conformément à la réglementation applicable.
                    </div>
                </details>
                <details>
                    <summary>Où trouver la politique de confidentialité ?</summary>
                    <div class="faq-body">
                        La politique complète est disponible sur la page
                        <a href="<?php echo htmlspecialchars($privacy_url, ENT_QUOTES, 'UTF-8'); ?>">Politique de confidentialité</a>.
                        Vous pouvez aussi y accéder depuis le pied de page du site ou le menu de votre profil lorsque vous êtes connecté.
                    </div>
                </details>
            </div>

            <p class="help-footer-note">
                Édité par <strong><?php echo htmlspecialchars($company_name); ?></strong> ·
                <a href="<?php echo htmlspecialchars($company_url, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">goo-bridge.com</a>
            </p>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
