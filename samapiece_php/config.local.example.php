<?php
/**
 * Option A — copier en config.local.php (non versionné) :
 *   cp config.local.example.php config.local.php
 *
 * Option B — fichier .env à la racine (voir .env.example).
 *
 * Option C — en production : variables d’environnement SAMAPIECE_SMTP_* sur l’hébergeur.
 *
 * Priorité : valeurs non vides dans config.local.php, puis variables d’environnement, puis .env.
 */
return [
    'smtp_host' => 'goo-bridge.com',
    'smtp_port' => 465,
    'smtp_encryption' => 'ssl',
    'smtp_username' => 'samapiece-noreply@goo-bridge.com',
    'smtp_password' => '', // ou votre mot de passe SMTP
    'email_from' => 'samapiece-noreply@goo-bridge.com',
    // 'smtp_ssl_verify' => '0', // décommenter si erreur SSL en local uniquement
];
