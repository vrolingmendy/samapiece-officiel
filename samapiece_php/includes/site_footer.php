<?php
/**
 * Pied de page commun (liens + réseaux + politique de confidentialité).
 * Prérequis : functions.php (APP_NAME, is_logged_in, samapiece_privacy_policy_url).
 */
if (empty($GLOBALS['_site_footer_styles_printed'])) {
    $GLOBALS['_site_footer_styles_printed'] = true;
    ?>
<style id="site-footer-shared">
.site-footer {
    /* Séparation nette du contenu principal + marge bas de page (lisibilité / safe-area) */
    flex-shrink: 0;
    margin-top: clamp(32px, 5vw, 56px);
    margin-bottom: 0;
    position: relative;
    border-top: 1px solid rgba(34, 197, 94, 0.15);
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 40%, #f0fdf4 100%);
    padding: clamp(12px, 2vw, 18px) 0 max(env(safe-area-inset-bottom, 0px), clamp(20px, 3vw, 36px));
    overflow: hidden;
}
.site-footer::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #22c55e 0%, #16a34a 42%, #0ea5e9 100%);
    opacity: 0.95;
}
.site-footer__inner {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr);
    gap: clamp(10px, 2vw, 20px);
    align-items: start;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    padding-left: clamp(16px, 4vw, 24px);
    padding-right: clamp(16px, 4vw, 24px);
    box-sizing: border-box;
    width: 100%;
}
.site-footer__brandcol {
    padding-left: 0;
    min-width: 0;
}
.site-footer__brand {
    font-weight: 700;
    font-size: 1.05rem;
    letter-spacing: -0.02em;
    color: #0a0a0a;
    margin-bottom: 4px;
    background: linear-gradient(120deg, #0a0a0a 0%, #16a34a 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}
.site-footer__tagline {
    font-size: 0.8rem;
    color: #6b7280;
    line-height: 1.45;
    max-width: 38ch;
}
.site-footer__company {
    font-size: 0.72rem;
    color: #94a3b8;
    margin-top: 4px;
    line-height: 1.35;
    max-width: 42ch;
}
.site-footer__company a {
    color: #64748b;
    font-weight: 600;
    text-decoration: none;
    border-bottom: 1px solid rgba(100, 116, 139, 0.35);
}
.site-footer__company a:hover {
    color: #15803d;
    border-bottom-color: rgba(34, 197, 94, 0.45);
}
.site-footer__nav {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 12px;
    justify-content: flex-start;
    align-content: flex-start;
    padding-left: 0;
    padding-right: 0;
    min-width: 0;
    row-gap: 8px;
}
.site-footer__nav a {
    color: #374151;
    font-weight: 600;
    font-size: 0.8rem;
    text-decoration: none;
    padding: 2px 0;
    border-bottom: 2px solid transparent;
    transition: color 0.2s, border-color 0.2s;
}
.site-footer__nav a:hover {
    color: #15803d;
    border-bottom-color: rgba(34, 197, 94, 0.45);
}
.site-footer__socialblock {
    width: 100%;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    margin-top: 4px;
    margin-bottom: 0;
    padding: 4px clamp(16px, 4vw, 24px);
    box-sizing: border-box;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.75);
    border: 1px solid rgba(226, 232, 240, 0.9);
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.03);
    text-align: center;
}
.site-footer__social-title {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #64748b;
    margin: 0 0 4px;
    line-height: 1.2;
}
.site-footer__social-icons {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.site-footer__social-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}
.site-footer__social-btn:hover {
    transform: translateY(-2px) scale(1.04);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.14);
}
.site-footer__social-btn:focus-visible {
    outline: 2px solid #22c55e;
    outline-offset: 3px;
}
.site-footer__social-btn svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
}
.site-footer__social-btn--tiktok { background: #000000; }
.site-footer__social-btn--tiktok svg { fill: #fff; }
.site-footer__social-btn--instagram {
    background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
}
.site-footer__social-btn--instagram svg { fill: none; }
.site-footer__social-btn--facebook { background: #1877f2; }
.site-footer__social-btn--facebook svg { fill: #fff; }
.site-footer__bottom {
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
    margin-top: 6px;
    padding: 6px clamp(16px, 4vw, 24px) 0;
    box-sizing: border-box;
    border-top: 1px solid rgba(226, 232, 240, 0.85);
    text-align: center;
    font-size: 0.68rem;
    color: #94a3b8;
    line-height: 1.35;
    width: 100%;
}
@media (min-width: 901px) {
    .site-footer__inner {
        gap: clamp(12px, 2.5vw, 24px);
    }
    .site-footer__tagline {
        max-width: 42ch;
    }
}
@media (max-width: 900px) {
    .site-footer__inner {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .site-footer__nav {
        gap: 6px 10px;
    }
    .site-footer__socialblock {
        margin-top: 4px;
    }
}
@media (max-width: 480px) {
    .site-footer {
        margin-top: clamp(28px, 6vw, 44px);
        padding-top: 10px;
        padding-bottom: max(env(safe-area-inset-bottom, 0px), clamp(18px, 4vw, 28px));
    }
    .site-footer__nav a {
        font-size: 0.76rem;
    }
}
</style>
<?php
}
$privacy_href = htmlspecialchars(samapiece_privacy_policy_url(), ENT_QUOTES, 'UTF-8');
$help_href = htmlspecialchars(samapiece_help_url(), ENT_QUOTES, 'UTF-8');
$company_site = htmlspecialchars(APP_COMPANY_SITE_URL, ENT_QUOTES, 'UTF-8');
$company_name_esc = htmlspecialchars(APP_COMPANY_NAME, ENT_QUOTES, 'UTF-8');
?>
            <footer class="site-footer">
                <div class="site-footer__inner">
                    <div class="site-footer__brandcol">
                        <p class="site-footer__brand"><?php echo htmlspecialchars(APP_NAME); ?></p>
                        <p class="site-footer__tagline">Rapprocher les documents perdus de leurs propriétaires, avec transparence et contrôle.</p>
                        <p class="site-footer__company">
                            <?php echo htmlspecialchars(APP_NAME); ?> · Une application
                            <a href="<?php echo $company_site; ?>" target="_blank" rel="noopener noreferrer"><?php echo $company_name_esc; ?></a>
                            · <a href="<?php echo $company_site; ?>" target="_blank" rel="noopener noreferrer">goo-bridge.com</a>
                        </p>
                    </div>
                    <nav class="site-footer__nav" aria-label="Liens pied de page">
                        <a href="<?php echo htmlspecialchars(samapiece_url('index.php'), ENT_QUOTES, 'UTF-8'); ?>">Accueil</a>
                        <a href="<?php echo htmlspecialchars(samapiece_url('search.php'), ENT_QUOTES, 'UTF-8'); ?>">Rechercher</a>
                        <a href="<?php echo htmlspecialchars(samapiece_url('declare.php'), ENT_QUOTES, 'UTF-8'); ?>">Déclarer</a>
                        <a href="<?php echo $help_href; ?>">Aide</a>
                        <a href="<?php echo $privacy_href; ?>">Politique de confidentialité</a>
                        <?php if (!is_logged_in()): ?>
                            <a href="<?php echo htmlspecialchars(samapiece_url('login.php'), ENT_QUOTES, 'UTF-8'); ?>">Connexion</a>
                            <a href="<?php echo htmlspecialchars(samapiece_url('register.php'), ENT_QUOTES, 'UTF-8'); ?>">Inscription</a>
                        <?php endif; ?>
                    </nav>
                </div>
                <div class="site-footer__socialblock">
                    <p class="site-footer__social-title">Suivez-nous</p>
                    <div class="site-footer__social-icons" aria-label="Réseaux sociaux">
                        <a class="site-footer__social-btn site-footer__social-btn--tiktok" href="https://www.tiktok.com/@samapiece" target="_blank" rel="noopener noreferrer" title="TikTok — Samapiece">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/></svg>
                            <span class="sr-only">TikTok</span>
                        </a>
                        <a class="site-footer__social-btn site-footer__social-btn--instagram" href="https://www.instagram.com/samapiece.officiel/" target="_blank" rel="noopener noreferrer" title="Instagram — Samapiece">
                            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                            <span class="sr-only">Instagram</span>
                        </a>
                        <a class="site-footer__social-btn site-footer__social-btn--facebook" href="https://www.facebook.com/share/17TZTQfC9z/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer" title="Facebook — Sama Piece">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#fff" d="M9.101 23.691v-7.98H6.127v-3.167h2.974V9.22c0-2.618 1.354-4.021 4.106-4.021 1.156 0 1.921.086 2.235.124v2.6h-1.534c-1.203 0-1.438.572-1.438 1.412v1.864h5.086l-.662 3.167h-4.424v7.98H9.101z"/></svg>
                            <span class="sr-only">Facebook</span>
                        </a>
                    </div>
                </div>
                <p class="site-footer__bottom">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?> · Tous droits réservés</p>
            </footer>
