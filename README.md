# Samapiece (officiel)

Application web **PHP / MySQL** pour mettre en relation des personnes ayant **perdu** un document officiel et celles qui en ont **retrouvé** un, avec suivi des demandes de récupération et remise en mains propres.

## Fonctionnalités (aperçu)

- Recherche de déclarations, déclaration de document perdu, comptes utilisateurs
- Demandes de récupération, codes / QR de remise, confirmation par le déclarant
- Tableau de bord, profil, pages légales (aide, confidentialité)

## Prérequis

- PHP 8+ avec extensions usuelles (PDO MySQL, etc.)
- MySQL / MariaDB
- Configuration SMTP optionnelle pour les e-mails (OTP, notifications)

## Installation

1. Cloner le dépôt et placer le dossier `samapiece_php/` à la racine du virtual host (ou adapter les chemins).
2. Copier `samapiece_php/config.local.example.php` vers `config.local.php` et renseigner base de données, SMTP, etc.
3. Importer le schéma SQL si besoin (`samapiece_php/install/`).
4. Droits d’écriture sur `samapiece_php/uploads/`.

## Licence / contact

Projet **Goo-Bridge** — voir les mentions dans l’application (`contact@samapiece.com`, etc.).
