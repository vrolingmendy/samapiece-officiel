<?php
// Configuration pour Samapiece - Plateforme de récupération de documents

// Configuration de base
define('APP_NAME', 'Samapiece');
define('APP_VERSION', '1.1.0');
/** Éditeur / entreprise propriétaire de l’application */
define('APP_COMPANY_NAME', 'Goo-Bridge');
/** Site web de l’entreprise (sans slash final dans les liens) */
define('APP_COMPANY_SITE_URL', 'https://goo-bridge.com');
/** Contact utilisateurs pour questions sur le service */
define('APP_CONTACT_EMAIL', 'contact@samapiece.com');
/** URL relative après connexion réussie (tableau de bord). */
define('AUTH_POST_LOGIN_URL', 'dashboard.php');

// Logo (fichier dans /img, URL relative à la racine de l’app)
define('APP_LOGO_PATH', __DIR__ . '/img/samapiece-logo.png');
define('APP_LOGO_URL', 'img/samapiece-logo.png');

/** Palette verte (marque — même ton que le bouton « Continuer ») */
if (!defined('SAMAPIECE_GREEN')) {
    define('SAMAPIECE_GREEN', '#128c7e');
    define('SAMAPIECE_GREEN_DARK', '#0e6b62');
    define('SAMAPIECE_GREEN_MID', '#107c71');
    define('SAMAPIECE_GREEN_TEXT', '#0a5048');
    define('SAMAPIECE_GREEN_SOFT', '#e8f5f3');
    define('SAMAPIECE_GREEN_SOFT2', '#ecf8f6');
    define('SAMAPIECE_GREEN_BORDER', '#b8dfd8');
    define('SAMAPIECE_GREEN_SUCCESS_BG', '#d4efea');
}

if (!function_exists('app_logo_available')) {
    function app_logo_available() {
        return is_file(APP_LOGO_PATH);
    }
}

if (!defined('APP_HOME_HERO_PATH')) {
    define('APP_HOME_HERO_PATH', __DIR__ . '/img/acceuil.png');
    define('APP_HOME_HERO_URL', 'img/acceuil.png');
}
if (!function_exists('app_home_hero_available')) {
    function app_home_hero_available() {
        return is_file(APP_HOME_HERO_PATH);
    }
}

// Configuration de sécurité
define('SECRET_KEY', 'votre_cle_secrete_ici_changez_moi'); // À changer en production
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCK_TIME_MINUTES', 30);

// SMTP : fichier .env à la racine, variables d’environnement (hébergeur), ou config.local.php
require_once __DIR__ . '/includes/config_env.php';
samapiece_load_dotenv(__DIR__);

$samapiece_local = [];
$__local_path = __DIR__ . '/config.local.php';
if (is_file($__local_path)) {
    $loaded = require $__local_path;
    if (is_array($loaded)) {
        $samapiece_local = $loaded;
    }
}
unset($__local_path);

$L = $samapiece_local;

define('SMTP_HOST', (string) samapiece_config_pick($L, 'smtp_host', ['SAMAPIECE_SMTP_HOST'], 'goo-bridge.com'));
define('SMTP_PORT', (int) samapiece_config_pick($L, 'smtp_port', ['SAMAPIECE_SMTP_PORT'], '465'));
define('SMTP_ENCRYPTION', (string) samapiece_config_pick($L, 'smtp_encryption', ['SAMAPIECE_SMTP_ENCRYPTION'], 'ssl'));
define('SMTP_USERNAME', (string) samapiece_config_pick($L, 'smtp_username', ['SAMAPIECE_SMTP_USER', 'SAMAPIECE_SMTP_USERNAME'], 'samapiece-noreply@goo-bridge.com'));
define('SMTP_PASSWORD', (string) samapiece_config_pick($L, 'smtp_password', ['SAMAPIECE_SMTP_PASSWORD'], ''));
define('EMAIL_FROM', (string) samapiece_config_pick($L, 'email_from', ['SAMAPIECE_EMAIL_FROM'], 'samapiece-noreply@goo-bridge.com'));
// Mettre à 0 ou false en local si erreur SSL vers le serveur SMTP (déconseillé en production)
$sslPick = samapiece_config_pick($L, 'smtp_ssl_verify', ['SAMAPIECE_SMTP_SSL_VERIFY'], '1');
define('SMTP_SSL_VERIFY', !in_array(strtolower((string) $sslPick), ['0', 'false', 'no', 'off'], true));

unset($samapiece_local, $L);

// URL publique du dossier de l’app (ex. http://localhost/samapiece_php). Vide = détection depuis la requête HTTP.
define('SITE_BASE_URL', '');

// Comptes créés « par email » : activation par code (voir register.php). Les comptes « téléphone » utilisent un mot de passe.
define('EMAIL_VERIFICATION_REQUIRED', true);

// Configuration des fichiers
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('DATABASE_FILE', __DIR__ . '/data.json');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']);
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Configuration MySQL
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'samapiece');

// Créer le dossier uploads s'il n'existe pas
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Session : cookie persistant (reste connecté après fermeture du navigateur jusqu’à déconnexion explicite)
if (!defined('SESSION_COOKIE_LIFETIME')) {
    define('SESSION_COOKIE_LIFETIME', 60 * 60 * 24 * 30); // 30 jours
}

if (session_status() === PHP_SESSION_NONE) {
    $sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    session_set_cookie_params([
        'lifetime' => SESSION_COOKIE_LIFETIME,
        'path' => '/',
        'secure' => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string) SESSION_COOKIE_LIFETIME);
    session_start();
}

// Fonction pour obtenir la connexion PDO
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }
    return $pdo;
}

// Fonction pour charger la base de données
function load_db() {
    if (!file_exists(DATABASE_FILE)) {
        return [
            'users' => [],
            'documents' => [],
            'lost_items' => [],
            'alerts' => [],
            'admins' => [],
            'login_tracking' => [],
            'security_logs' => []
        ];
    }
    $data = file_get_contents(DATABASE_FILE);
    return json_decode($data, true) ?: [];
}

// Fonction pour sauvegarder la base de données
function save_db($data) {
    file_put_contents(DATABASE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * URL en chemin absolu depuis la racine du site (gère l’app dans un sous-dossier).
 * Ex. script /projet/register.php → /projet/register_verify_email.php
 */
function samapiece_url(string $relative): string {
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = dirname($script);
    if ($dir === '/' || $dir === '.' || $dir === '') {
        return '/' . $relative;
    }
    return rtrim($dir, '/') . '/' . $relative;
}

/**
 * URL complète http(s) pour header('Location: …') — fiable avec port (ex. :8080) et sous-dossiers.
 * Si SITE_BASE_URL est renseigné (ex. http://127.0.0.1:8080), il est préfixé au chemin samapiece_url().
 */
function samapiece_absolute_url(string $relative): string {
    $path = samapiece_url($relative);
    $base = trim((string) SITE_BASE_URL);
    if ($base !== '') {
        return rtrim($base, '/') . $path;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
    return $scheme . '://' . $host . $path;
}

/** Chemin relatif de la page « Politique de confidentialité » (sous-dossier pris en charge). */
function samapiece_privacy_policy_path(): string {
    return 'privacy.php';
}

function samapiece_privacy_policy_url(): string {
    return samapiece_url(samapiece_privacy_policy_path());
}

/** Page d’aide & FAQ */
function samapiece_help_path(): string {
    return 'aide.php';
}

function samapiece_help_url(): string {
    return samapiece_url(samapiece_help_path());
}

// Fonction pour générer un UUID
function generate_uuid() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}