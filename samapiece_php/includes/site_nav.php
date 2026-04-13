<?php
/**
 * Barre unique : marque + liens + auth (remplace l’ancien <header> bleu).
 * Prérequis : functions.php chargé (APP_NAME, is_logged_in).
 */
require __DIR__ . '/app_global_responsive.php';

/** Langue affichée sur le bouton drapeau (invité) — alignée sur le cookie googtrans / GTranslate */
$site_nav_gt_lang = 'fr';
$site_nav_allowed_langs = ['fr', 'en', 'pt', 'es', 'ar'];
if (!empty($_COOKIE['googtrans'])) {
    $raw = urldecode((string) $_COOKIE['googtrans']);
    $parts = array_values(array_filter(explode('/', trim($raw, '/'))));
    if ($parts !== []) {
        $cand = end($parts);
        if (in_array($cand, $site_nav_allowed_langs, true)) {
            $site_nav_gt_lang = $cand;
        }
    }
}
$site_nav_flag_url = 'https://cdn.gtranslate.net/flags/svg/' . $site_nav_gt_lang . '.svg';

if (empty($GLOBALS['_site_nav_styles_printed'])) {
    $GLOBALS['_site_nav_styles_printed'] = true;
    ?>
<style id="site-nav-shared">
.site-nav-bar {
    background: #ffffff;
    border-bottom: 1px solid #e8e8e8;
    padding: clamp(10px, 2.5vw, 14px) 0;
    padding-top: max(clamp(10px, 2.5vw, 14px), env(safe-area-inset-top, 0px));
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 1px 0 rgba(15, 23, 42, 0.05);
    width: 100%;
    max-width: 100vw;
    box-sizing: border-box;
}
.site-nav-inner {
    max-width: 1120px;
    margin: 0 auto;
    padding-left: max(clamp(12px, 3.5vw, 22px), env(safe-area-inset-left, 0px));
    padding-right: max(clamp(12px, 3.5vw, 22px), env(safe-area-inset-right, 0px));
    padding-top: 0;
    padding-bottom: 0;
    display: grid;
    grid-template-columns: minmax(0, auto) minmax(0, 1fr) minmax(0, auto);
    align-items: center;
    gap: clamp(8px, 2vw, 20px) clamp(10px, 2.5vw, 20px);
    min-height: 0;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
    overflow: visible;
}
.site-nav-brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: clamp(1rem, 2.8vw, 1.2rem);
    letter-spacing: -0.02em;
    color: #0a0a0a;
    text-decoration: none;
    min-width: 0;
}
.site-nav-brand:hover {
    color: #128c7e;
}
.site-nav-brand:hover .site-nav-logo {
    opacity: 0.92;
}
.site-nav-logo {
    height: clamp(34px, 7vw, 40px);
    width: auto;
    max-width: 140px;
    object-fit: contain;
    object-position: left center;
    display: block;
    flex-shrink: 0;
}
.site-nav-brand-text {
    line-height: 1.15;
    white-space: nowrap;
}
.site-nav-middle {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 4px 6px;
    justify-content: center;
    min-width: 0;
    overflow-x: auto;
    overflow-y: visible;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-x: contain;
    touch-action: pan-x pan-y;
    scrollbar-width: thin;
    padding: 4px 0;
}
.site-nav-middle::-webkit-scrollbar {
    height: 4px;
}
.site-nav-middle::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}
.site-nav-middle a {
    color: #1f2937;
    text-decoration: none;
    font-weight: 600;
    font-size: clamp(0.82rem, 2.2vw, 0.9rem);
    padding: 8px clamp(10px, 2vw, 14px);
    border-radius: 999px;
    transition: background 0.2s, color 0.2s;
}
.site-nav-middle a:hover {
    background: #e8f5f3;
    color: #0e6b62;
}
.site-nav-auth {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 8px;
    justify-content: flex-end;
    flex-shrink: 0;
    min-width: 0;
}
.site-nav-btn {
    padding: 8px clamp(12px, 3vw, 16px);
    border-radius: 999px;
    text-decoration: none;
    font-weight: 600;
    font-size: clamp(0.8rem, 2vw, 0.875rem);
    border: 1px solid transparent;
    transition: background 0.2s, border-color 0.2s, color 0.2s, transform 0.15s;
    font-family: inherit;
    cursor: pointer;
    white-space: nowrap;
}
.site-nav-btn--outline {
    border-color: #e5e5e5;
    background: #fff;
    color: #0a0a0a;
}
.site-nav-btn--outline:hover {
    border-color: #d4d4d4;
    background: #fafafa;
}
.site-nav-btn--solid {
    background: linear-gradient(135deg, #128c7e 0%, #0e6b62 100%);
    color: #fff !important;
    border-color: transparent;
}
.site-nav-btn--solid:hover {
    filter: brightness(1.05);
    transform: translateY(-1px);
}
.site-nav-user-menu {
    position: relative;
    list-style: none;
    z-index: 220;
}
.site-nav-user-menu > summary {
    list-style: none;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.site-nav-user-menu > summary::-webkit-details-marker {
    display: none;
}
.site-nav-user-menu > summary::marker {
    content: none;
}
.site-nav-user-menu > summary::after {
    content: "";
    width: 0;
    height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 5px solid #525252;
    margin-top: 2px;
    transition: transform 0.2s;
}
.site-nav-user-menu[open] > summary::after {
    transform: rotate(180deg);
}
.site-nav-user-menu-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    min-width: 220px;
    padding: 6px 0;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
    z-index: 300;
    overflow: visible;
}
.site-nav-user-menu-panel a {
    display: block;
    padding: 10px 16px;
    color: #1f2937;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.875rem;
    transition: background 0.15s;
}
.site-nav-user-menu-panel a:hover {
    background: #f8fafc;
}
.site-nav-user-menu-panel a.site-nav-user-menu-danger {
    border-top: 1px solid #f1f5f9;
    margin-top: 4px;
    padding-top: 12px;
    color: #b91c1c;
}
.site-nav-user-menu-panel a.site-nav-user-menu-danger:hover {
    background: #fef2f2;
}
.site-nav-lang-dropdown {
    position: relative;
    list-style: none;
    z-index: 220;
}
.site-nav-lang-dropdown > summary {
    list-style: none;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 8px;
    min-width: 2.25rem;
}
.site-nav-lang-flag-img {
    display: block;
    width: 22px;
    height: 22px;
    object-fit: cover;
    border-radius: 3px;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.08);
}
.site-nav-lang-dropdown > summary::-webkit-details-marker {
    display: none;
}
.site-nav-lang-dropdown > summary::marker {
    content: none;
}
.site-nav-lang-dropdown-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    min-width: 132px;
    padding: 8px 10px;
    background: #fff;
    border: 1px solid #e8e8e8;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
    z-index: 300;
    overflow: visible;
}
/* Invité : un clic = uniquement les langues cibles (pas la langue actuelle), drapeaux visibles tout de suite */
.site-nav-lang-dropdown-panel .site-nav-gtranslate .gt_switcher .gt_selected {
    display: none !important;
}
.site-nav-lang-dropdown-panel .site-nav-gtranslate .gt_switcher .gt_option {
    position: static !important;
    display: flex !important;
    flex-wrap: wrap;
    gap: 6px;
    justify-content: center;
    align-items: center;
    width: auto !important;
    min-width: 0 !important;
    max-width: none !important;
    height: auto !important;
    max-height: none !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: visible !important;
    border: none !important;
    box-sizing: border-box !important;
    transition: none !important;
}
.site-nav-lang-dropdown-panel .site-nav-gtranslate .gt_switcher .gt_option a.gt_current {
    display: none !important;
}
.site-nav-gtranslate {
    line-height: 0;
}
/* GTranslate : uniquement drapeaux, sans texte ; le sous-menu ne pousse pas la hauteur du header */
.site-nav-gtranslate .gt_switcher {
    position: relative !important;
    width: auto !important;
    max-width: 100% !important;
    z-index: 1;
    overflow: visible !important;
}
.site-nav-gtranslate .gt_switcher .gt_selected a::after {
    display: none !important;
}
.site-nav-gtranslate .gt_switcher .gt_selected a {
    font-size: 0 !important;
    line-height: 0 !important;
    color: transparent !important;
    width: auto !important;
    min-width: 0 !important;
    max-width: none !important;
    padding: 4px 6px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
}
.site-nav-gtranslate .gt_switcher .gt_selected a img {
    margin: 0 !important;
    flex-shrink: 0;
}
.site-nav-gtranslate .gt_switcher .gt_option {
    position: absolute !important;
    left: 0 !important;
    top: 100% !important;
    right: auto !important;
    width: auto !important;
    min-width: 0 !important;
    margin-top: 4px !important;
    z-index: 500 !important;
}
.site-nav-gtranslate .gt_switcher .gt_option a {
    font-size: 0 !important;
    line-height: 0 !important;
    color: transparent !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 6px 8px !important;
    min-height: 36px;
}
.site-nav-gtranslate .gt_switcher .gt_option a img {
    margin: 0 !important;
}
/* Tablette / petit écran : défilement horizontal sur les liens centraux ; logo + actions restent visibles */
@media (max-width: 900px) {
    .site-nav-bar {
        padding: 6px 0;
        padding-top: max(6px, env(safe-area-inset-top, 0px));
    }
    .site-nav-inner {
        display: flex;
        flex-direction: row;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: flex-start;
        gap: clamp(4px, 1.5vw, 10px);
        overflow: visible;
        padding-left: max(10px, env(safe-area-inset-left, 0px));
        padding-right: max(10px, env(safe-area-inset-right, 0px));
    }
    .site-nav-brand {
        flex: 0 0 auto;
        gap: 6px;
        max-width: 42%;
    }
    .site-nav-logo {
        height: clamp(28px, 7vw, 32px);
        max-width: 100px;
    }
    .site-nav-brand-text {
        font-size: clamp(0.88rem, 2.4vw, 0.95rem);
    }
    .site-nav-middle {
        flex: 1 1 0;
        min-width: 0;
        justify-content: flex-start;
        overflow-x: auto;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        overscroll-behavior-x: contain;
        padding: 0;
        mask-image: none;
        touch-action: pan-x pan-y;
    }
    .site-nav-middle::-webkit-scrollbar {
        height: 3px;
    }
    .site-nav-middle::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }
    .site-nav-middle a {
        flex: 0 0 auto;
        padding: 6px clamp(7px, 1.8vw, 12px);
        font-size: clamp(0.7rem, 2.4vw, 0.82rem);
    }
    .site-nav-auth {
        flex: 0 0 auto;
        flex-wrap: nowrap;
        gap: clamp(3px, 1.2vw, 8px);
        position: relative;
        z-index: 230;
        min-width: 0;
    }
    .site-nav-btn {
        padding: 6px clamp(8px, 2.2vw, 14px);
        font-size: clamp(0.68rem, 2.2vw, 0.8rem);
    }
}
/* Téléphones : tout tient sur une ligne (liens défilent si besoin) */
@media (max-width: 520px) {
    .site-nav-inner {
        gap: 4px 6px;
        padding-left: max(8px, env(safe-area-inset-left, 0px));
        padding-right: max(8px, env(safe-area-inset-right, 0px));
    }
    .site-nav-brand {
        max-width: 38%;
    }
    .site-nav-logo {
        max-width: 88px;
        height: 30px;
    }
    .site-nav-middle a {
        padding: 5px 6px;
        font-size: 0.68rem;
    }
    .site-nav-btn {
        padding: 5px 8px;
        font-size: 0.68rem;
    }
    .site-nav-lang-dropdown > summary {
        padding: 4px 6px;
        min-width: 2rem;
    }
    .site-nav-lang-flag-img {
        width: 20px;
        height: 20px;
    }
}
@media (max-width: 380px) {
    .site-nav-inner {
        gap: 3px 4px;
        padding-left: max(6px, env(safe-area-inset-left, 0px));
        padding-right: max(6px, env(safe-area-inset-right, 0px));
    }
    .site-nav-brand {
        max-width: 34%;
    }
    .site-nav-logo {
        max-width: 72px;
        height: 28px;
    }
    .site-nav-middle a {
        padding: 5px 5px;
        font-size: 0.62rem;
        letter-spacing: -0.02em;
    }
    .site-nav-btn {
        padding: 5px 6px;
        font-size: 0.62rem;
    }
    .site-nav-user-menu > summary.site-nav-btn {
        padding: 5px 8px;
        font-size: 0.62rem;
    }
}
/* Téléphone : masquer le nom à côté du logo et le bouton « Créer un compte » */
@media (max-width: 640px) {
    .site-nav-brand-text {
        display: none;
    }
    .site-nav-brand:not(:has(.site-nav-logo)) .site-nav-brand-text {
        display: inline;
    }
    .site-nav-auth a[href="register.php"] {
        display: none;
    }
}
/* Bureau : même largeur max que les pages conteneur (dashboard, recherche, etc.) */
@media (min-width: 1024px) {
    .site-nav-inner {
        max-width: 1200px;
        padding-left: max(24px, env(safe-area-inset-left, 0px));
        padding-right: max(24px, env(safe-area-inset-right, 0px));
    }
    .site-nav-middle {
        gap: 6px 10px;
    }
    .site-nav-middle a {
        font-size: 0.9rem;
        padding: 9px 16px;
    }
    .site-nav-btn {
        font-size: 0.875rem;
        padding: 9px 18px;
    }
}
</style>
<?php } ?>
<div class="app-page-backdrop" aria-hidden="true"></div>
<nav class="site-nav-bar" aria-label="Navigation principale">
    <div class="site-nav-inner">
        <a href="index.php" class="site-nav-brand" aria-label="<?php echo htmlspecialchars(APP_NAME); ?> — accueil">
            <?php if (app_logo_available()): ?>
                <img src="<?php echo htmlspecialchars(APP_LOGO_URL); ?>" alt="" class="site-nav-logo" width="160" height="40" decoding="async" fetchpriority="high" aria-hidden="true">
            <?php endif; ?>
            <span class="site-nav-brand-text"><?php echo htmlspecialchars(APP_NAME); ?></span>
        </a>
        <div class="site-nav-middle">
            <a href="index.php">Accueil</a>
            <a href="search.php">Rechercher</a>
            <a href="declare.php">Déclarer</a>
        </div>
        <div class="site-nav-auth">
            <?php if (is_logged_in()): ?>
                <details class="site-nav-lang-dropdown">
                    <summary class="site-nav-btn site-nav-btn--outline" aria-label="Langue du site">
                        <img id="site-nav-lang-flag" class="site-nav-lang-flag-img" src="<?php echo htmlspecialchars($site_nav_flag_url, ENT_QUOTES, 'UTF-8'); ?>" width="22" height="22" alt="" decoding="async" aria-hidden="true">
                    </summary>
                    <div class="site-nav-lang-dropdown-panel">
                        <div class="gtranslate_wrapper site-nav-gtranslate" id="site-gtranslate-wrap" aria-label="Traduction du site"></div>
                    </div>
                </details>
                <details class="site-nav-user-menu">
                    <summary class="site-nav-btn site-nav-btn--outline">Mon profil</summary>
                    <div class="site-nav-user-menu-panel">
                        <a href="dashboard.php">Mon tableau de bord</a>
                        <a href="account.php">Mes informations</a>
                        <a href="<?php echo htmlspecialchars(samapiece_privacy_policy_url(), ENT_QUOTES, 'UTF-8'); ?>">Politique de confidentialité</a>
                        <a href="<?php echo htmlspecialchars(samapiece_help_url(), ENT_QUOTES, 'UTF-8'); ?>">Aide</a>
                        <a href="logout.php" class="site-nav-user-menu-danger">Déconnexion</a>
                    </div>
                </details>
            <?php else: ?>
                <details class="site-nav-lang-dropdown">
                    <summary class="site-nav-btn site-nav-btn--outline" aria-label="Langue du site">
                        <img id="site-nav-lang-flag" class="site-nav-lang-flag-img" src="<?php echo htmlspecialchars($site_nav_flag_url, ENT_QUOTES, 'UTF-8'); ?>" width="22" height="22" alt="" decoding="async" aria-hidden="true">
                    </summary>
                    <div class="site-nav-lang-dropdown-panel">
                        <div class="gtranslate_wrapper site-nav-gtranslate" id="site-gtranslate-wrap" aria-label="Traduction du site"></div>
                    </div>
                </details>
                <a href="login.php" class="site-nav-btn site-nav-btn--outline">Connexion</a>
                <a href="register.php" class="site-nav-btn site-nav-btn--solid">Créer un compte</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<?php
if (empty($GLOBALS['_site_nav_gtranslate_printed'])) {
    $GLOBALS['_site_nav_gtranslate_printed'] = true;
    $gtranslate_settings = [
        'default_language' => 'fr',
        /* Les 5 langues : la langue active est masquée dans la liste (classe gt_current) */
        'languages' => ['fr', 'en', 'pt', 'es', 'ar'],
        'wrapper_selector' => '#site-gtranslate-wrap',
        'native_language_names' => false,
        'flag_style' => '2d',
        'flag_size' => 20,
        'detect_browser_language' => false,
        'switcher_horizontal_position' => 'inline',
        'switcher_vertical_position' => 'top',
        'switcher_open_direction' => 'bottom',
        'custom_css' => '.site-nav-gtranslate .gt_switcher{width:auto!important;max-width:100%!important}',
    ];
    ?>
<script>
window.gtranslateSettings = <?php echo json_encode($gtranslate_settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="https://cdn.gtranslate.net/widgets/latest/dwf.js" defer></script>
<script>
(function () {
    var details = document.querySelector('.site-nav-lang-dropdown');
    var wrap = document.getElementById('site-gtranslate-wrap');
    var flagImg = document.getElementById('site-nav-lang-flag');
    if (!details || !wrap || !flagImg) {
        return;
    }
    var LANGS = ['fr', 'en', 'pt', 'es', 'ar'];
    var FLAG_BASE = 'https://cdn.gtranslate.net/flags/svg/';

    function langFromCookie() {
        var m = document.cookie.match(/(?:^|; )googtrans=([^;]*)/);
        if (!m) {
            return 'fr';
        }
        var parts = decodeURIComponent(m[1]).split('/').filter(Boolean);
        if (!parts.length) {
            return 'fr';
        }
        var cand = parts[parts.length - 1];
        return LANGS.indexOf(cand) !== -1 ? cand : 'fr';
    }

    function syncFlag() {
        var inner = wrap.querySelector('.gt_switcher .gt_selected img');
        if (inner && inner.getAttribute('src')) {
            flagImg.src = inner.src;
            return;
        }
        flagImg.src = FLAG_BASE + langFromCookie() + '.svg';
    }

    details.addEventListener('toggle', function () {
        syncFlag();
        if (!this.open) {
            return;
        }
        var opt = wrap.querySelector('.gt_option');
        if (opt) {
            opt.style.display = 'flex';
            opt.style.height = 'auto';
            opt.style.overflow = 'visible';
        }
        wrap.querySelectorAll('.gt_option img[data-gt-lazy-src]').forEach(function (img) {
            if (!img.getAttribute('src')) {
                img.setAttribute('src', img.getAttribute('data-gt-lazy-src'));
            }
        });
    });

    var mo = new MutationObserver(function () { syncFlag(); });
    mo.observe(wrap, { subtree: true, childList: true, attributes: true, attributeFilter: ['src'] });

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            syncFlag();
        }
    });
})();
</script>
<?php
}
?>
