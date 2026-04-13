<?php
require_once __DIR__ . '/functions.php';
$page_title = 'Aide & FAQ - Samapiece';
$privacy_url = samapiece_privacy_policy_url();
$contact_email = APP_CONTACT_EMAIL;
$contact_mailto = 'mailto:' . rawurlencode($contact_email) . '?subject=' . rawurlencode('Question sur Samapiece');
$company_url = APP_COMPANY_SITE_URL;
$company_name = APP_COMPANY_NAME;

$link = static function (string $path): string {
    return htmlspecialchars(samapiece_url($path), ENT_QUOTES, 'UTF-8');
};
$u_search = $link('search.php');
$u_declare = $link('declare.php');
$u_login = $link('login.php');
$u_register = $link('register.php');
$u_dashboard = $link('dashboard.php');
$u_account = $link('account.php');
$u_forgot = $link('forgot_password.php');
$u_index = $link('index.php');
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
            --green: #128c7e;
            --green-dark: #0e6b62;
            --green-soft: #e8f5f3;
            --card: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 50%, #ecf8f6 100%);
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
            background: linear-gradient(90deg, var(--green), #5cbaae, #0ea5e9);
        }
        .help-hero::after {
            content: "";
            position: absolute;
            top: -40%;
            right: -15%;
            width: 45%;
            aspect-ratio: 1;
            background: radial-gradient(circle, rgba(18, 140, 126, 0.12) 0%, transparent 70%);
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
            border-bottom: 1px solid rgba(18, 140, 126, 0.35);
        }
        .help-hero .help-company a:hover {
            border-bottom-color: var(--green-dark);
        }
        .help-contact {
            border-radius: 16px;
            padding: clamp(20px, 3vw, 26px);
            background: linear-gradient(145deg, var(--green-soft) 0%, #ecf8f6 100%);
            border: 1px solid rgba(18, 140, 126, 0.25);
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
            box-shadow: 0 8px 24px rgba(18, 140, 126, 0.35);
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
            border: 1px solid rgba(18, 140, 126, 0.35);
            box-shadow: 0 4px 14px rgba(18, 140, 126, 0.12);
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .help-contact a.mail:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(18, 140, 126, 0.2);
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
        .help-faq-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .help-faq-category {
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: var(--ink);
            margin: clamp(22px, 3vw, 28px) 0 4px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--line);
        }
        .help-faq-group:first-of-type .help-faq-category {
            margin-top: 0;
        }
        .help-faq .faq-body ul {
            margin: 10px 0 0;
            padding-left: 1.2em;
        }
        .help-faq .faq-body li {
            margin: 0.35em 0;
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

                <div class="help-faq-group" aria-labelledby="faq-cat-1">
                    <h2 class="help-faq-category" id="faq-cat-1">Découvrir <?php echo htmlspecialchars(APP_NAME); ?></h2>
                    <details>
                        <summary>Qu’est-ce que <?php echo htmlspecialchars(APP_NAME); ?> ?</summary>
                        <div class="faq-body">
                            <?php echo htmlspecialchars(APP_NAME); ?> met en relation des personnes qui ont <strong>trouvé</strong> un document officiel (carte d’identité, passeport, etc.) et des personnes qui le <strong>cherchent</strong>, sur la base d’informations que vous saisissez vous-même (nom, prénom, date et lieu de naissance, type de document). L’objectif est de faciliter la restitution tout en gardant le contrôle sur ce que vous partagez.
                        </div>
                    </details>
                    <details>
                        <summary>Je suis trouveur ou j’ai perdu un document : par où commencer ?</summary>
                        <div class="faq-body">
                            <ul>
                                <li><strong>Vous avez trouvé</strong> un document : déclarez-le depuis la page <a href="<?php echo $u_declare; ?>">Déclarer un objet perdu</a> (compte requis), avec une photo nette du recto et les informations du titulaire.</li>
                                <li><strong>Vous cherchez</strong> le vôtre : utilisez <a href="<?php echo $u_search; ?>">Rechercher</a> avec les informations figurant sur le document, puis suivez les indications (résultats ou alerte e-mail).</li>
                            </ul>
                            La page d’<a href="<?php echo $u_index; ?>">accueil</a> résume les deux parcours.
                        </div>
                    </details>
                </div>

                <div class="help-faq-group" aria-labelledby="faq-cat-2">
                    <h2 class="help-faq-category" id="faq-cat-2">Compte, inscription et connexion</h2>
                    <details>
                        <summary>Comment créer un compte ?</summary>
                        <div class="faq-body">
                            Rendez-vous sur <a href="<?php echo $u_register; ?>">Créer un compte</a>. Vous pouvez vous inscrire <strong>avec une adresse e-mail</strong> (réception d’un code à 6 chiffres pour activer le compte) ou <strong>avec un numéro de téléphone et un mot de passe</strong>, selon le mode proposé. Vous devez accepter la <a href="<?php echo htmlspecialchars($privacy_url, ENT_QUOTES, 'UTF-8'); ?>">politique de confidentialité</a>.
                        </div>
                    </details>
                    <details>
                        <summary>Je ne reçois pas le code par e-mail (inscription ou connexion)</summary>
                        <div class="faq-body">
                            Vérifiez les courriers indésirables / spam, l’orthographe de votre adresse, et attendez une minute avant de redemander un code. L’envoi nécessite que le serveur d’e-mails (SMTP) soit correctement configuré sur l’installation ; en cas de blocage répété, contactez-nous à
                            <a href="<?php echo htmlspecialchars($contact_mailto, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email); ?></a>.
                        </div>
                    </details>
                    <details>
                        <summary>Comment me connecter ?</summary>
                        <div class="faq-body">
                            Sur la page <a href="<?php echo $u_login; ?>">Connexion</a> : soit avec <strong>e-mail + code</strong> (comptes « par e-mail »), soit avec <strong>téléphone + mot de passe</strong> (comptes « téléphone »), selon le type de compte créé à l’inscription.
                        </div>
                    </details>
                    <details>
                        <summary>J’ai oublié mon mot de passe (compte téléphone)</summary>
                        <div class="faq-body">
                            Utilisez la page <a href="<?php echo $u_forgot; ?>">mot de passe oublié</a> : vous devrez confirmer votre identité avec le nom, prénom, date de naissance et numéro de téléphone associés au compte, puis définir un nouveau mot de passe.
                        </div>
                    </details>
                    <details>
                        <summary>Où modifier mon profil ou mes coordonnées ?</summary>
                        <div class="faq-body">
                            Une fois connecté, ouvrez <a href="<?php echo $u_account; ?>">Mes informations</a> (également accessible depuis le menu du compte) pour mettre à jour nom, prénom, e-mail ou téléphone selon les règles de votre type de compte. Le <a href="<?php echo $u_dashboard; ?>">tableau de bord</a> regroupe vos déclarations et demandes de récupération.
                        </div>
                    </details>
                </div>

                <div class="help-faq-group" aria-labelledby="faq-cat-3">
                    <h2 class="help-faq-category" id="faq-cat-3">Recherche et alertes</h2>
                    <details>
                        <summary>Comment fonctionne la recherche ?</summary>
                        <div class="faq-body">
                            Sur <a href="<?php echo $u_search; ?>">Rechercher</a>, renseignez les champs disponibles (nom, prénom, date de naissance, lieu de naissance, catégorie de document). La plateforme compare avec les déclarations enregistrées par les trouveurs. Plus vos critères sont précis, plus le résultat est fiable.
                        </div>
                    </details>
                    <details>
                        <summary>Aucun résultat : que puis-je faire ?</summary>
                        <div class="faq-body">
                            Si aucune fiche ne correspond, vous pouvez enregistrer une <strong>alerte e-mail</strong> : vous serez prévenu si une déclaration ultérieure correspond à vos critères (selon les options affichées sur la page de résultats). Vous pouvez aussi élargir ou ajuster votre recherche.
                        </div>
                    </details>
                </div>

                <div class="help-faq-group" aria-labelledby="faq-cat-4">
                    <h2 class="help-faq-category" id="faq-cat-4">Déclarer un document trouvé</h2>
                    <details>
                        <summary>Que dois-je fournir pour une déclaration ?</summary>
                        <div class="faq-body">
                            Une <strong>photo nette du recto</strong> du document (obligatoire), le <strong>nom et prénom</strong> du titulaire, la <strong>date et le lieu de naissance</strong>, la <strong>catégorie</strong> (carte d’identité, passeport, permis, Carte Vitale, autre) et éventuellement une <strong>description</strong>. La déclaration est accessible depuis <a href="<?php echo $u_declare; ?>">Déclarer un objet perdu</a> (connexion obligatoire).
                        </div>
                    </details>
                    <details>
                        <summary>Où voir mes déclarations après envoi ?</summary>
                        <div class="faq-body">
                            Connectez-vous et ouvrez le <a href="<?php echo $u_dashboard; ?>">tableau de bord</a> : la section dédiée liste vos déclarations et l’état des éventuelles demandes de récupération.
                        </div>
                    </details>
                </div>

                <div class="help-faq-group" aria-labelledby="faq-cat-5">
                    <h2 class="help-faq-category" id="faq-cat-5">Récupérer un document (demandeur)</h2>
                    <details>
                        <summary>Comment demander la récupération d’un document trouvé ?</summary>
                        <div class="faq-body">
                            Après une <a href="<?php echo $u_search; ?>">recherche</a>, si un résultat correspond à votre document, connectez-vous et utilisez l’action prévue pour <strong>demander la récupération</strong>. Le déclarant est informé ; un <strong>code de remise</strong> et un <strong>lien / QR code</strong> vous permettent de prouver votre demande lors d’un échange en personne.
                        </div>
                    </details>
                    <details>
                        <summary>Comment se passe la remise en main propre ?</summary>
                        <div class="faq-body">
                            La remise se fait <strong>physiquement</strong>. Présentez le code ou le QR indiqué sur votre demande ; le déclarant vérifie et confirme la remise depuis son tableau de bord. Ne communiquez jamais vos codes à des tiers et méfiez-vous des arnaques : <?php echo htmlspecialchars(APP_NAME); ?> ne vous demandera pas de payer pour « débloquer » un document par message.
                        </div>
                    </details>
                    <details>
                        <summary>Je suis déclarant : une personne demande à récupérer son document</summary>
                        <div class="faq-body">
                            Vous recevez une notification si une adresse e-mail est associée à votre compte. Depuis le <a href="<?php echo $u_dashboard; ?>">tableau de bord</a>, suivez les étapes pour consulter la demande, préparer la remise et confirmer après avoir vérifié le code ou le QR présenté par le demandeur.
                        </div>
                    </details>
                </div>

                <div class="help-faq-group" aria-labelledby="faq-cat-6">
                    <h2 class="help-faq-category" id="faq-cat-6">Données, confidentialité et signalements</h2>
                    <details>
                        <summary>Mes données sont-elles protégées ?</summary>
                        <div class="faq-body">
                            Nous appliquons des mesures adaptées (connexion sécurisée, traitement des données sensibles comme la date de naissance avec des précautions renforcées côté serveur, hébergement conforme aux bonnes pratiques). Le détail des traitements et de vos droits figure dans la
                            <a href="<?php echo htmlspecialchars($privacy_url, ENT_QUOTES, 'UTF-8'); ?>">politique de confidentialité</a>.
                        </div>
                    </details>
                    <details>
                        <summary>Où trouver la politique de confidentialité ?</summary>
                        <div class="faq-body">
                            Page dédiée : <a href="<?php echo htmlspecialchars($privacy_url, ENT_QUOTES, 'UTF-8'); ?>">Politique de confidentialité</a>, également accessible depuis le pied de page du site et lors de l’inscription ou de la connexion lorsque c’est requis.
                        </div>
                    </details>
                    <details>
                        <summary>Puis-je demander la suppression de mon compte ou exercer mes droits ?</summary>
                        <div class="faq-body">
                            Oui. Écrivez-nous à
                            <a href="<?php echo htmlspecialchars($contact_mailto, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email); ?></a>
                            en indiquant l’adresse e-mail liée au compte (ou les éléments permettant de vous identifier). Nous traiterons votre demande dans les délais prévus par la réglementation applicable.
                        </div>
                    </details>
                    <details>
                        <summary>Comment signaler un abus ou un comportement inapproprié ?</summary>
                        <div class="faq-body">
                            Contactez-nous à
                            <a href="<?php echo htmlspecialchars($contact_mailto, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contact_email); ?></a>
                            avec un maximum de précision (dates, captures d’écran si utiles, description factuelle). Chaque signalement est examiné avec sérieux et dans le respect de la confidentialité.
                        </div>
                    </details>
                </div>

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
