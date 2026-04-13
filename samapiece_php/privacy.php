<?php
require_once __DIR__ . '/functions.php';
$page_title = 'Politique de confidentialité - Samapiece';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Politique de confidentialité et traitement des données personnelles — <?php echo htmlspecialchars(APP_NAME); ?>.">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php require __DIR__ . '/includes/head_favicon.php'; ?>
    <style>
        :root {
            --ink: #0a0a0a;
            --muted: #6b7280;
            --line: #e5e7eb;
            --green: #128c7e;
            --green-dark: #0e6b62;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #fafafa;
            color: var(--ink);
            line-height: 1.65;
        }
        .privacy-page {
            max-width: 720px;
            margin: 0 auto;
            padding: clamp(24px, 4vw, 48px) clamp(16px, 3vw, 24px) clamp(40px, 6vw, 64px);
        }
        .privacy-page h1 {
            font-size: clamp(1.5rem, 3vw, 1.85rem);
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
            color: var(--ink);
        }
        .privacy-page .privacy-updated {
            font-size: 0.88rem;
            color: var(--muted);
            margin-bottom: clamp(24px, 4vw, 32px);
        }
        .privacy-page h2 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 28px;
            margin-bottom: 12px;
            color: var(--ink);
        }
        .privacy-page p, .privacy-page li {
            font-size: 0.95rem;
            color: #374151;
            margin-bottom: 12px;
        }
        .privacy-page ul {
            padding-left: 1.25rem;
            margin-bottom: 12px;
        }
        .privacy-page a {
            color: var(--green-dark);
            font-weight: 600;
        }
        .privacy-page a:hover {
            text-decoration: underline;
        }
        .privacy-intro {
            padding: 16px 18px;
            border-radius: 12px;
            background: #e8f5f3;
            border: 1px solid rgba(18, 140, 126, 0.2);
            margin-bottom: 24px;
            font-size: 0.95rem;
            color: #166534;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>

    <main id="contenu-principal">
        <article class="privacy-page">
            <h1>Politique de confidentialité</h1>
            <p class="privacy-updated">Dernière mise à jour : avril 2026</p>
            <p class="privacy-intro">
                La présente politique décrit comment <?php echo htmlspecialchars(APP_NAME); ?> traite les données personnelles dans le cadre du service de recherche et de déclaration de documents officiels perdus ou trouvés. En créant un compte ou en vous connectant, vous confirmez avoir pris connaissance de ce document.
            </p>

            <h2>1. Responsable du traitement</h2>
            <p>
                L’application <?php echo htmlspecialchars(APP_NAME); ?> est éditée par <?php echo htmlspecialchars(APP_COMPANY_NAME); ?>
                (<a href="<?php echo htmlspecialchars(APP_COMPANY_SITE_URL, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">goo-bridge.com</a>).
                Les données sont traitées dans le cadre de ce service, conformément à la réglementation applicable en matière de protection des données (notamment le RGPD lorsque celui-ci s’applique).
            </p>

            <h2>2. Données collectées</h2>
            <p>Selon votre utilisation du site, peuvent être collectées notamment :</p>
            <ul>
                <li>identifiants de compte (adresse e-mail, numéro de téléphone, nom et prénom lorsque vous les fournissez) ;</li>
                <li>informations relatives aux documents déclarés ou recherchés (catégorie, informations permettant la correspondance avec une recherche, dans les limites prévues par les formulaires) ;</li>
                <li>données de connexion et de sécurité (logs techniques, tentatives de connexion, dans la mesure nécessaire à la sécurité du service) ;</li>
                <li>cookies ou équivalents strictement nécessaires au fonctionnement du site ou à la langue d’affichage lorsque vous utilisez des outils de traduction.</li>
            </ul>

            <h2>3. Finalités</h2>
            <p>Les données sont utilisées pour : permettre l’inscription et l’authentification ; mettre en relation des déclarations et des recherches ; assurer la remise des documents dans des conditions contrôlées (par exemple vérifications ou codes) ; améliorer le service ; prévenir les abus et assurer la sécurité ; respecter les obligations légales.</p>

            <h2>4. Base légale</h2>
            <p>Le traitement repose notamment sur l’exécution du service que vous demandez, l’intérêt légitime de sécuriser la plateforme, et, le cas échéant, votre consentement pour certaines fonctionnalités (par exemple cookies non strictement nécessaires, si proposés).</p>

            <h2>5. Durée de conservation</h2>
            <p>Les données sont conservées pendant la durée nécessaire aux finalités décrites, puis archivées ou supprimées selon les délais légaux et les besoins opérationnels (compte inactif, litiges, obligations comptables ou légales le cas échéant).</p>

            <h2>6. Destinataires</h2>
            <p>Les informations strictement nécessaires peuvent être transmises à des prestataires techniques (hébergement, envoi d’e-mails, traduction du site) soumis à des obligations de confidentialité et de sécurité. Aucune vente de données personnelles à des fins commerciales n’est effectuée.</p>

            <h2>7. Vos droits</h2>
            <p>Vous pouvez demander l’accès, la rectification, l’effacement ou la limitation du traitement de vos données, ainsi que la portabilité lorsque la loi le prévoit, et retirer votre consentement lorsque le traitement en dépend. Vous pouvez aussi introduire une réclamation auprès de l’autorité de protection des données compétente.</p>
            <p>Pour exercer vos droits, utilisez les moyens de contact mis à disposition par le service (par exemple via votre espace « Mes informations » lorsque disponible).</p>

            <h2>8. Sécurité</h2>
            <p>Des mesures techniques et organisationnelles appropriées sont mises en œuvre pour protéger les données contre l’accès non autorisé, la perte ou l’altération, dans la mesure du raisonnable compte tenu de la nature du service.</p>

            <h2>9. Modifications</h2>
            <p>Cette politique peut être mise à jour ; la date de mise à jour est indiquée en tête de page. En cas de changement substantiel, une information sur le site pourra vous être donnée.</p>

            <h2>10. Contact</h2>
            <p>
                Pour toute question relative à cette politique ou à vos données : <a href="mailto:<?php echo htmlspecialchars(APP_CONTACT_EMAIL, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(APP_CONTACT_EMAIL); ?></a>
                · page <a href="<?php echo htmlspecialchars(samapiece_help_url(), ENT_QUOTES, 'UTF-8'); ?>">Aide</a>.
            </p>
        </article>
    </main>
    <?php
    require __DIR__ . '/includes/site_footer.php';
    ?>
</body>
</html>
