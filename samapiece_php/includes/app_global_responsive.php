<?php
/**
 * Règles responsive de base pour toutes les pages (inclus via site_nav.php).
 */
if (!empty($GLOBALS['_app_global_responsive_printed'])) {
    return;
}
$GLOBALS['_app_global_responsive_printed'] = true;
?>
<style id="app-global-responsive">
/* Base — toutes les pages */
*, *::before, *::after { box-sizing: border-box; }
html {
    -webkit-text-size-adjust: 100%;
    text-size-adjust: 100%;
    min-height: 100%;
    min-height: 100dvh;
}
img, svg, video {
    max-width: 100%;
    height: auto;
}
body {
    overflow-x: hidden;
    min-height: 100%;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
}
/* Fond plein écran : l’élément fixed ne doit pas réserver de hauteur dans la colonne flex */
body > .app-page-backdrop {
    flex: none;
    height: 0;
    margin: 0;
    padding: 0;
    overflow: visible;
}
.site-nav-bar {
    flex-shrink: 0;
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
/* Ligne indicatif + numéro : une seule rangée (grille, pas de wrap par largeur min) */
.form-row-phone {
    display: grid;
    grid-template-columns: minmax(0, 34%) minmax(0, 1fr);
    gap: 10px;
    align-items: end;
    margin-bottom: 20px;
}
.form-row-phone .form-group {
    margin-bottom: 0;
    min-width: 0;
}
@media (max-width: 360px) {
    .form-row-phone {
        grid-template-columns: 1fr;
    }
}
.phone-country-select {
    width: 100%;
    max-width: 100%;
    padding: 10px 8px;
    border: 1px solid #e5edf6;
    border-radius: 6px;
    font-size: 14px;
    background: #fff;
}
/* Fond plein écran : grille de points + masses vertes — toutes les pages via site_nav */
.app-page-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    width: 100vw;
    /* Forcer la surface de peinture (évite height:0 du flex parent sur certains moteurs) */
    min-height: 100vh !important;
    min-height: 100dvh !important;
    height: auto;
    z-index: 0;
    pointer-events: none;
    /* Points : rayons entiers (1px) pour compatibilité navigateurs */
    background:
        radial-gradient(ellipse 38% 48% at 18% 45%, rgba(34, 197, 94, 0.16) 0%, transparent 70%),
        radial-gradient(ellipse 32% 55% at 48% 52%, rgba(22, 163, 74, 0.14) 0%, transparent 68%),
        radial-gradient(ellipse 40% 50% at 82% 42%, rgba(34, 197, 94, 0.14) 0%, transparent 72%),
        radial-gradient(circle, rgba(21, 128, 61, 0.32) 1px, transparent 1px),
        radial-gradient(circle, rgba(34, 197, 94, 0.18) 1px, transparent 1px);
    background-size:
        auto,
        auto,
        auto,
        18px 18px,
        18px 18px;
    background-position:
        0 0,
        0 0,
        0 0,
        0 0,
        9px 9px;
}
main {
    position: relative;
    z-index: 1;
    flex: 1 0 auto;
    width: 100%;
    min-width: 0;
    /* Espace avant le pied de page — cohérent sur toutes les pages avec site_nav */
    padding-bottom: clamp(20px, 3vw, 32px);
}
/* Tableaux larges (dashboard, etc.) */
.table-responsive-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
@media (max-width: 640px) {
    .table-responsive-wrap table {
        min-width: 520px;
    }
}

/* Téléphone : interface plus compacte (toutes les pages avec site_nav) */
@media (max-width: 480px) {
    html {
        font-size: 94%;
    }
    main {
        flex: 1 0 auto !important;
        padding-top: 20px !important;
        padding-bottom: clamp(18px, 5vw, 28px) !important;
    }
    .container {
        padding-left: max(12px, env(safe-area-inset-left)) !important;
        padding-right: max(12px, env(safe-area-inset-right)) !important;
    }
    /* Formulaires pleine largeur type login / inscription */
    .login-form,
    .register-form,
    .box {
        padding: 16px 12px !important;
        border-radius: 10px !important;
    }
    .login-form h2,
    .register-form h2,
    .box h2 {
        font-size: 1.12rem !important;
        margin-bottom: 10px !important;
    }
    .search-section {
        padding: 18px 12px !important;
        margin-bottom: 24px !important;
    }
    .search-section h2 {
        font-size: 1.1rem !important;
    }
    .results-section {
        padding: 14px 12px !important;
    }
    .results-header h2 {
        font-size: 1.05rem !important;
    }
    .results-count {
        font-size: 0.85rem !important;
    }
    .reminder-panel {
        padding: 12px 10px !important;
    }
    .reminder-panel h3 {
        font-size: 1rem !important;
    }
    .reminder-intro {
        font-size: 0.82rem !important;
        margin-bottom: 10px !important;
    }
    /* Boutons : texte un peu plus petit, moins de hauteur */
    .btn,
    .btn-primary,
    .search-btn,
    .reminder-submit,
    .tab-button {
        font-size: 0.88rem !important;
        padding: 10px 12px !important;
        border-radius: 8px !important;
    }
    .button-group {
        gap: 8px !important;
        margin-top: 14px !important;
    }
    .tabs a,
    .tabs span,
    .mode-tabs button {
        padding: 8px 6px !important;
        font-size: 0.78rem !important;
    }
    .form-group {
        margin-bottom: 14px !important;
    }
    .form-group label {
        font-size: 0.86rem !important;
        margin-bottom: 4px !important;
    }
    /* 16px min sur les champs : évite le zoom automatique iOS */
    .form-group input,
    .form-group select,
    .form-group textarea,
    .login-form input,
    .register-form input,
    .register-form select,
    .reminder-form input,
    .reminder-form select,
    .search-form input,
    .search-form select {
        padding: 10px 10px !important;
        font-size: 16px !important;
    }
    .phone-country-select {
        padding: 8px 6px !important;
        font-size: 15px !important;
    }
    .item-card {
        padding: 14px 12px !important;
        margin-bottom: 12px !important;
    }
    .item-title {
        font-size: 1.02rem !important;
    }
    .item-meta,
    .item-description {
        font-size: 0.86rem !important;
    }
    .message,
    .reminder-flash {
        padding: 10px 12px !important;
        font-size: 0.86rem !important;
    }
    .link-btn {
        font-size: 0.82rem !important;
    }
    .no-results {
        padding: 8px 8px 4px !important;
    }
    .dashboard-tabs .tab-button {
        padding: 8px 10px !important;
        font-size: 0.78rem !important;
    }
    .welcome-section {
        padding: 8px 12px !important;
        border-radius: 8px !important;
        margin-bottom: 12px !important;
    }
    .welcome-section h2 {
        font-size: 1rem !important;
    }
    .welcome-section .stats h3 {
        font-size: 1.15rem !important;
    }
    .welcome-section .stats {
        padding-left: 10px !important;
    }
    .profile-card,
    .summary-card {
        padding: 14px 12px !important;
    }
    .profile-card h3,
    .summary-card h3 {
        font-size: 1rem !important;
    }
    .profile-item {
        font-size: 0.88rem !important;
        margin-bottom: 10px !important;
    }
    .actions {
        flex-direction: column !important;
        gap: 8px !important;
    }
    .actions .btn {
        width: 100%;
    }
    .profile-declare-callout {
        padding: 12px 12px !important;
        margin-bottom: 14px !important;
    }
    .profile-declare-callout p {
        font-size: 0.84rem !important;
    }
    .home-hero__title {
        font-size: clamp(1.35rem, 6vw, 1.75rem) !important;
    }
    .home-hero__lead {
        font-size: 0.92rem !important;
    }
    .home-hero__cta .btn,
    .home-hero__cta a {
        font-size: 0.88rem !important;
        padding: 10px 14px !important;
    }
    .home-foot {
        font-size: 0.85rem !important;
    }
}

/* Très petits téléphones */
@media (max-width: 360px) {
    html {
        font-size: 90%;
    }
    .site-nav-logo {
        max-width: 100px !important;
        height: 30px !important;
    }
}
</style>
