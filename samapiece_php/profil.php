<?php
/**
 * Raccourci vers l’onglet profil du tableau de bord (après connexion).
 */
require_once __DIR__ . '/config.php';
header('Location: ' . samapiece_absolute_url(AUTH_POST_LOGIN_URL), true, 302);
exit;
