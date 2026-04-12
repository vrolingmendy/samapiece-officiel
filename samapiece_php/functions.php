<?php
require_once 'config.php';
require_once 'security.php';

ensure_auth_schema();

// Fonction pour vérifier si un fichier est autorisé
function allowed_file($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ALLOWED_EXTENSIONS);
}

// Fonction pour optimiser une image
function optimize_image($filepath) {
    if (!extension_loaded('gd')) {
        return false;
    }

    $info = getimagesize($filepath);
    if (!$info) return false;

    $width = $info[0];
    $height = $info[1];
    $type = $info[2];

    // Redimensionner si trop grande
    $max_width = 800;
    $max_height = 600;

    if ($width > $max_width || $height > $max_height) {
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = round($width * $ratio);
        $new_height = round($height * $ratio);

        $src = null;
        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($filepath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($filepath);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($filepath);
                break;
        }

        if ($src) {
            $dst = imagecreatetruecolor($new_width, $new_height);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            switch ($type) {
                case IMAGETYPE_JPEG:
                    imagejpeg($dst, $filepath, 85);
                    break;
                case IMAGETYPE_PNG:
                    imagepng($dst, $filepath, 8);
                    break;
                case IMAGETYPE_GIF:
                    imagegif($dst, $filepath);
                    break;
            }

            imagedestroy($src);
            imagedestroy($dst);
        }
    }

    return true;
}

// Fonction pour obtenir tous les utilisateurs
function get_all_users() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM users");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour obtenir un utilisateur par email
function get_user_by_email($email) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/** Téléphone complet ex. +221771234567 (comme en base). */
function normalize_full_phone($code_pays, $telephone) {
    $code = preg_replace('/[^\d+]/', '', (string) $code_pays);
    if ($code !== '' && strpos($code, '+') !== 0) {
        $code = '+' . ltrim($code, '+');
    }
    $num = preg_replace('/\D/', '', (string) $telephone);
    return $code . $num;
}

function get_user_by_telephone($full_phone) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE telephone = ?');
    $stmt->execute([$full_phone]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function auth_column_exists(PDO $pdo, $table, $col) {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $q->execute([$db, $table, $col]);
    return (int) $q->fetchColumn() > 0;
}

/**
 * Colonnes de suivi : quand un utilisateur demande la récupération depuis la recherche,
 * le déclarant voit le statut mis à jour sur son tableau de bord.
 */
function ensure_lost_item_recovery_schema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = get_db_connection();
        if (!auth_column_exists($pdo, 'lost_items', 'recovery_status')) {
            $pdo->exec("ALTER TABLE lost_items ADD COLUMN recovery_status VARCHAR(32) NOT NULL DEFAULT 'en_attente'");
        }
        if (!auth_column_exists($pdo, 'lost_items', 'recovery_requested_at')) {
            $pdo->exec('ALTER TABLE lost_items ADD COLUMN recovery_requested_at DATETIME NULL');
        }
        if (!auth_column_exists($pdo, 'lost_items', 'recovery_requester_id')) {
            $pdo->exec('ALTER TABLE lost_items ADD COLUMN recovery_requester_id VARCHAR(64) NULL');
        }
        if (!auth_column_exists($pdo, 'lost_items', 'recovery_handover_code')) {
            $pdo->exec('ALTER TABLE lost_items ADD COLUMN recovery_handover_code VARCHAR(16) NULL');
        }
        if (!auth_column_exists($pdo, 'lost_items', 'recovery_handover_at')) {
            $pdo->exec('ALTER TABLE lost_items ADD COLUMN recovery_handover_at DATETIME NULL');
        }
    } catch (Throwable $e) {
        // schéma non modifié
    }
}

function normalize_recovery_handover_code($code) {
    $code = preg_replace('/[^A-Za-z0-9]/', '', (string) $code);
    return strtoupper($code);
}

/**
 * Code unique 8 caractères (sans 0, O, I, 1 pour limiter les confusions).
 */
function generate_recovery_handover_code() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 8; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function generate_unique_recovery_handover_code(PDO $pdo) {
    for ($attempt = 0; $attempt < 60; $attempt++) {
        $c = generate_recovery_handover_code();
        $chk = $pdo->prepare('SELECT 1 FROM lost_items WHERE recovery_handover_code = ? LIMIT 1');
        $chk->execute([$c]);
        if (!$chk->fetchColumn()) {
            return $c;
        }
    }
    return generate_recovery_handover_code();
}

/**
 * Garantit un code de remise pour une déclaration en « demande de récupération ».
 *
 * @return array{0:bool,1:?string}
 */
function lost_item_ensure_handover_code($lost_item_id) {
    ensure_lost_item_recovery_schema();
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT * FROM lost_items WHERE id = ?');
    $stmt->execute([$lost_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item || lost_item_recovery_status($item) !== 'demande_recuperation') {
        return [false, null];
    }
    $existing = trim((string) ($item['recovery_handover_code'] ?? ''));
    if ($existing !== '') {
        return [true, $existing];
    }
    $code = generate_unique_recovery_handover_code($pdo);
    $pdo->prepare('UPDATE lost_items SET recovery_handover_code = ? WHERE id = ?')->execute([$code, $lost_item_id]);
    return [true, $code];
}

function recovery_handover_public_url($lost_item_id, $code) {
    return samapiece_absolute_url('handover.php?d=' . rawurlencode((string) $lost_item_id) . '&c=' . rawurlencode((string) $code));
}

/**
 * @return array{0:bool,1:string}
 */
function confirm_recovery_handover($lost_item_id, $owner_user_id, $code_input) {
    ensure_lost_item_recovery_schema();
    $lost_item_id = trim((string) $lost_item_id);
    $owner_user_id = trim((string) $owner_user_id);
    $code = normalize_recovery_handover_code($code_input);
    if ($lost_item_id === '' || strlen($code) !== 8) {
        return [false, 'Indiquez le code à 8 caractères.'];
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT * FROM lost_items WHERE id = ?');
    $stmt->execute([$lost_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return [false, 'Déclaration introuvable.'];
    }
    if ((string) ($item['user_id'] ?? '') !== $owner_user_id) {
        return [false, 'Vous n’êtes pas le déclarant de ce document.'];
    }
    if (lost_item_recovery_status($item) !== 'demande_recuperation') {
        return [false, 'Aucune demande de récupération en cours pour ce document.'];
    }
    $expected = normalize_recovery_handover_code($item['recovery_handover_code'] ?? '');
    if ($expected === '' || !hash_equals($expected, $code)) {
        return [false, 'Code incorrect.'];
    }
    $pdo->prepare('UPDATE lost_items SET recovery_status = ?, recovery_handover_at = NOW() WHERE id = ?')->execute(['recupere', $lost_item_id]);
    return [true, 'La remise du document est confirmée.'];
}

/** @return 'en_attente'|'demande_recuperation'|'recupere' */
function lost_item_recovery_status(array $item) {
    $s = $item['recovery_status'] ?? 'en_attente';
    return $s !== '' ? $s : 'en_attente';
}

function lost_item_recovery_status_label($status) {
    $map = [
        'en_attente' => 'En attente',
        'demande_recuperation' => 'Demande de récupération',
        'recupere' => 'Récupéré',
    ];
    $s = $status ?? 'en_attente';
    return $map[$s] ?? $map['en_attente'];
}

/** Libellé français pour la catégorie de document (e-mails, affichage). */
function lost_item_categorie_label($categorie) {
    $map = [
        'carte_identite' => 'Carte d’identité',
        'passeport' => 'Passeport',
        'permis_conduire' => 'Permis de conduire',
        'carte_vitale' => 'Carte Vitale',
        'autre' => 'Autre',
    ];
    $k = (string) $categorie;
    return $map[$k] ?? ($k !== '' ? $k : 'Document');
}

/**
 * Enveloppe HTML générique pour les e-mails transactionnels (compatible clients mail).
 */
function samapiece_email_html_layout(string $title, string $innerHtml) {
    $brand = htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8');
    $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $helpUrl = htmlspecialchars(samapiece_absolute_url(samapiece_help_path()), ENT_QUOTES, 'UTF-8');
    return '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $titleEsc . '</title></head>'
        . '<body style="margin:0;padding:0;background:#f1f5f9;font-family:Segoe UI,Roboto,Helvetica,Arial,sans-serif;font-size:16px;line-height:1.55;color:#0f172a;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f1f5f9;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 10px 40px rgba(15,23,42,0.08);">'
        . '<tr><td style="background:linear-gradient(120deg,#16a34a 0%,#22c55e 45%,#0ea5e9 100%);padding:22px 28px;">'
        . '<p style="margin:0;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:rgba(255,255,255,0.9);">' . $brand . '</p>'
        . '<h1 style="margin:8px 0 0;font-size:20px;font-weight:700;color:#ffffff;line-height:1.25;">' . $titleEsc . '</h1>'
        . '</td></tr>'
        . '<tr><td style="padding:28px 28px 8px;">' . $innerHtml . '</td></tr>'
        . '<tr><td style="padding:0 28px 24px;font-size:13px;color:#64748b;border-top:1px solid #e2e8f0;">'
        . '<p style="margin:16px 0 8px;">Besoin d’aide ? <a href="' . $helpUrl . '" style="color:#15803d;font-weight:600;">Page Aide</a> · '
        . '<a href="mailto:' . htmlspecialchars(APP_CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') . '" style="color:#15803d;font-weight:600;">' . htmlspecialchars(APP_CONTACT_EMAIL, ENT_QUOTES, 'UTF-8') . '</a></p>'
        . '<p style="margin:0;font-size:12px;">© ' . date('Y') . ' ' . $brand . ' · ' . htmlspecialchars(APP_COMPANY_NAME, ENT_QUOTES, 'UTF-8') . '</p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

/**
 * Envoie les e-mails au déclarant et au demandeur après une demande de récupération (ne bloque pas si SMTP indisponible).
 */
function samapiece_send_recovery_request_emails(array $item, ?array $ownerUser, ?array $requesterUser, $handoverCode) {
    $handoverCode = normalize_recovery_handover_code((string) $handoverCode);
    if (strlen($handoverCode) !== 8) {
        return;
    }
    $lostId = (string) ($item['id'] ?? '');
    if ($lostId === '') {
        return;
    }
    $catLabel = lost_item_categorie_label($item['categorie'] ?? '');
    $catEsc = htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8');
    $dashboardOwner = htmlspecialchars(samapiece_absolute_url('dashboard.php#mes-declarations'), ENT_QUOTES, 'UTF-8');
    $viewDecl = htmlspecialchars(samapiece_absolute_url('view_declaration.php?id=' . rawurlencode($lostId)), ENT_QUOTES, 'UTF-8');
    $dashboardRequester = htmlspecialchars(samapiece_absolute_url('dashboard.php#mes-demandes-recuperation'), ENT_QUOTES, 'UTF-8');
    $handoverUrl = htmlspecialchars(recovery_handover_public_url($lostId, $handoverCode), ENT_QUOTES, 'UTF-8');
    $codeEsc = htmlspecialchars($handoverCode, ENT_QUOTES, 'UTF-8');

    $requesterHint = 'Une personne';
    if ($requesterUser) {
        $reqPrenom = trim((string) ($requesterUser['prenom'] ?? ''));
        $reqNom = trim((string) ($requesterUser['nom'] ?? ''));
        $nomInitial = $reqNom !== ''
            ? (function_exists('mb_substr') ? mb_substr($reqNom, 0, 1, 'UTF-8') : substr($reqNom, 0, 1)) . '.'
            : '';
        if ($reqPrenom !== '') {
            $requesterHint = htmlspecialchars(trim($reqPrenom . ($nomInitial !== '' ? ' ' . $nomInitial : '')), ENT_QUOTES, 'UTF-8');
        }
    }

    /* --- Déclarant --- */
    $ownerEmail = $ownerUser ? trim((string) ($ownerUser['email'] ?? '')) : '';
    if ($ownerUser && $ownerEmail !== '' && SecurityManager::validate_email($ownerEmail)) {
        $inner = '<p style="margin:0 0 16px;">Bonjour,</p>'
            . '<p style="margin:0 0 16px;"><strong>' . $requesterHint . '</strong> a demandé la récupération d’un document que vous avez déclaré sur <strong>' . htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') . '</strong>, dans la catégorie <strong>' . $catEsc . '</strong>.</p>'
            . '<p style="margin:0 0 16px;">Remettez le document <strong>en personne</strong>. Lors de la remise, vérifiez le <strong>code à 8 caractères</strong> ou le <strong>QR code</strong> présenté par la personne, puis confirmez la remise depuis votre tableau de bord.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;"><tr>'
            . '<td style="border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0;padding:14px 18px;">'
            . '<p style="margin:0 0 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:#166534;">Prochaine étape</p>'
            . '<p style="margin:0;font-size:14px;color:#14532d;">Ouvrez votre tableau de bord pour afficher le code et le QR à comparer lors de la remise.</p>'
            . '</td></tr></table>'
            . '<p style="margin:20px 0 16px;text-align:center;">'
            . '<a href="' . $dashboardOwner . '" style="display:inline-block;padding:12px 22px;background:#16a34a;color:#ffffff;text-decoration:none;font-weight:700;border-radius:999px;font-size:15px;">Tableau de bord</a>'
            . '</p>'
            . '<p style="margin:0 0 8px;font-size:14px;"><a href="' . $viewDecl . '" style="color:#0ea5e9;font-weight:600;">Détails de la déclaration</a></p>'
            . '<p style="margin:16px 0 0;font-size:13px;color:#64748b;">Si vous n’êtes pas à l’origine de cette déclaration ou en cas de doute, contactez le support.</p>';
        $html = samapiece_email_html_layout('Demande de récupération pour votre déclaration', $inner);
        $ok = send_email($ownerEmail, APP_NAME . ' — Demande de récupération pour votre déclaration', $html);
        if (!$ok) {
            error_log('samapiece: e-mail déclarant recovery non envoyé vers ' . $ownerEmail);
        }
    }

    /* --- Demandeur --- */
    if (!$requesterUser) {
        return;
    }
    $requesterEmail = trim((string) ($requesterUser['email'] ?? ''));
    if ($requesterEmail !== '' && SecurityManager::validate_email($requesterEmail)) {
        $inner = '<p style="margin:0 0 16px;">Bonjour,</p>'
            . '<p style="margin:0 0 16px;">Votre demande de récupération est bien enregistrée pour un document de type <strong>' . $catEsc . '</strong>. Le déclarant a été <strong>notifié par e-mail</strong> (si une adresse est associée à son compte).</p>'
            . '<p style="margin:0 0 16px;">Lors du rendez-vous, présentez <strong>le code ci-dessous</strong> ou le <strong>lien / QR</strong> pour que le déclarant puisse confirmer la remise en toute sécurité.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">'
            . '<tr><td style="padding:18px 20px;text-align:center;">'
            . '<p style="margin:0 0 8px;font-size:11px;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#64748b;">Code de remise</p>'
            . '<p style="margin:0;font-size:26px;font-weight:800;letter-spacing:0.25em;color:#0f172a;font-family:ui-monospace,Consolas,monospace;">' . $codeEsc . '</p>'
            . '</td></tr></table>'
            . '<p style="margin:16px 0;text-align:center;">'
            . '<a href="' . $handoverUrl . '" style="display:inline-block;padding:12px 22px;background:#0ea5e9;color:#ffffff;text-decoration:none;font-weight:700;border-radius:999px;font-size:15px;">Ouvrir la page QR / lien</a>'
            . '</p>'
            . '<p style="margin:0 0 16px;font-size:13px;color:#64748b;word-break:break-all;">Lien direct :<br><a href="' . $handoverUrl . '" style="color:#0ea5e9;">' . $handoverUrl . '</a></p>'
            . '<p style="margin:20px 0 16px;text-align:center;">'
            . '<a href="' . $dashboardRequester . '" style="display:inline-block;padding:10px 18px;background:#f1f5f9;color:#0f172a;text-decoration:none;font-weight:600;border-radius:10px;font-size:14px;border:1px solid #e2e8f0;">Mes demandes de récupération</a>'
            . '</p>'
            . '<p style="margin:0;font-size:13px;color:#64748b;">Conservez ce message : il contient votre code et votre lien personnels pour cette demande.</p>';
        $html = samapiece_email_html_layout('Votre demande de récupération est enregistrée', $inner);
        $ok = send_email($requesterEmail, APP_NAME . ' — Votre demande de récupération est enregistrée', $html);
        if (!$ok) {
            error_log('samapiece: e-mail demandeur recovery non envoyé vers ' . $requesterEmail);
        }
    }
}

/**
 * @return array{0:bool,1:string}
 */
function request_recovery_for_lost_item($lost_item_id, $requester_user_id, array $search_criteria) {
    ensure_lost_item_recovery_schema();
    $lost_item_id = trim((string) $lost_item_id);
    $requester_user_id = trim((string) $requester_user_id);
    if ($lost_item_id === '' || $requester_user_id === '') {
        return [false, 'Requête invalide.'];
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT * FROM lost_items WHERE id = ?');
    $stmt->execute([$lost_item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        return [false, 'Document introuvable.'];
    }
    if (!lost_item_matches_reminder_criteria($item, $search_criteria)) {
        return [false, 'Ce document ne correspond pas aux critères de votre recherche.'];
    }
    $owner = trim((string) ($item['user_id'] ?? ''));
    if ($owner !== '' && $owner === $requester_user_id) {
        return [false, 'Vous ne pouvez pas demander la récupération de votre propre déclaration.'];
    }
    $st = lost_item_recovery_status($item);
    if ($st === 'demande_recuperation') {
        return [false, 'Une demande de récupération existe déjà pour ce document.'];
    }
    if ($st === 'recupere') {
        return [false, 'Ce document est déjà marqué comme récupéré.'];
    }
    $code = generate_unique_recovery_handover_code($pdo);
    $upd = $pdo->prepare('UPDATE lost_items SET recovery_status = ?, recovery_requested_at = NOW(), recovery_requester_id = ?, recovery_handover_code = ? WHERE id = ?');
    $upd->execute(['demande_recuperation', $requester_user_id, $code, $lost_item_id]);

    $ownerUser = $owner !== '' ? get_user_by_id($owner) : null;
    $requesterUser = get_user_by_id($requester_user_id);
    samapiece_send_recovery_request_emails($item, $ownerUser, $requesterUser, $code);

    return [true, 'Votre demande a été enregistrée. Le déclarant et vous recevez un e-mail de confirmation si une adresse e-mail est renseignée. Retrouvez aussi le code et le QR dans « Mes demandes de récupération ».'];
}

function ensure_auth_schema() {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $pdo = get_db_connection();
        if (!auth_column_exists($pdo, 'users', 'registration_type')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN registration_type VARCHAR(32) NOT NULL DEFAULT 'password_email'");
        }
        if (!auth_column_exists($pdo, 'users', 'date_naissance')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN date_naissance DATE NULL');
        }
        if (!auth_column_exists($pdo, 'users', 'email_otp_hash')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_otp_hash VARCHAR(64) NULL');
        }
        if (!auth_column_exists($pdo, 'users', 'email_otp_expires')) {
            $pdo->exec('ALTER TABLE users ADD COLUMN email_otp_expires DATETIME NULL');
        }
        try {
            $pdo->exec('ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL');
        } catch (Throwable $e) {
            // ignore
        }
        try {
            $pdo->exec('ALTER TABLE users MODIFY COLUMN email VARCHAR(255) NULL');
        } catch (Throwable $e) {
            // ignore
        }
    } catch (Throwable $e) {
        // schéma non modifié (droits, etc.)
    }
}

function user_registration_type(array $user) {
    $t = $user['registration_type'] ?? 'password_email';
    return $t !== '' ? $t : 'password_email';
}

/** Connexion par téléphone + mot de passe (compte téléphone ou ancien enregistrement sans email). */
function user_can_login_with_phone(array $user) {
    if (empty($user['password_hash'])) {
        return false;
    }
    if (user_registration_type($user) === 'password_phone') {
        return true;
    }
    $em = trim((string) ($user['email'] ?? ''));
    return $em === '';
}

function auth_otp_hash($user_id, $code) {
    return hash_hmac('sha256', (string) $code, SECRET_KEY . '|otp|' . $user_id);
}

function auth_generate_otp() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function auth_set_email_otp($user_id, $code, $ttl_minutes = 15) {
    $pdo = get_db_connection();
    $hash = auth_otp_hash($user_id, $code);
    $exp = (new DateTimeImmutable('now'))->modify('+' . (int) $ttl_minutes . ' minutes')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare('UPDATE users SET email_otp_hash = ?, email_otp_expires = ? WHERE id = ?');
    $stmt->execute([$hash, $exp, $user_id]);
}

function auth_clear_email_otp($user_id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('UPDATE users SET email_otp_hash = NULL, email_otp_expires = NULL WHERE id = ?');
    $stmt->execute([$user_id]);
}

/**
 * @return array{0:bool,1:?string} [ok, message_erreur]
 */
function auth_verify_email_otp(array $user, $code) {
    $code = preg_replace('/\D/', '', (string) $code);
    if (strlen($code) !== 6) {
        return [false, 'Le code doit comporter 6 chiffres.'];
    }
    $hash = $user['email_otp_hash'] ?? '';
    $exp = $user['email_otp_expires'] ?? null;
    if ($hash === '' || empty($exp)) {
        return [false, 'Aucun code actif. Demandez un nouveau code.'];
    }
    if (strtotime((string) $exp) < time()) {
        return [false, 'Ce code a expiré. Demandez un nouveau code.'];
    }
    $expected = auth_otp_hash($user['id'], $code);
    if (!hash_equals($hash, $expected)) {
        return [false, 'Code incorrect.'];
    }
    return [true, null];
}

function auth_send_otp_email_html($to, $code) {
    $safe = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
    $body = '<p>Bonjour,</p>';
    $body .= '<p>Votre code Samapiece est : <strong style="font-size:1.25rem;letter-spacing:0.2em;">' . $safe . '</strong></p>';
    $body .= '<p>Ce code expire dans 15 minutes. Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.</p>';
    return send_email($to, 'Samapiece — votre code de vérification', $body);
}

function auth_user_names_match(array $user, $nom, $prenom, $date_naissance) {
    $un = mb_strtolower(trim($user['nom'] ?? ''), 'UTF-8');
    $up = mb_strtolower(trim($user['prenom'] ?? ''), 'UTF-8');
    $dn = isset($user['date_naissance']) && $user['date_naissance'] !== null && $user['date_naissance'] !== ''
        ? substr((string) $user['date_naissance'], 0, 10)
        : '';
    $nn = mb_strtolower(trim($nom), 'UTF-8');
    $np = mb_strtolower(trim($prenom), 'UTF-8');
    $nd = trim((string) $date_naissance);
    if ($nd !== '') {
        $nd = substr($nd, 0, 10);
    }
    if ($nn === '' || $np === '' || $nd === '') {
        return false;
    }
    return $un === $nn && $up === $np && $dn === $nd;
}

// Fonction pour obtenir un utilisateur par ID
function get_user_by_id($id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Indicatif + numéro national pour préremplir un formulaire (téléphone stocké en format complet).
 *
 * @return array{0:string,1:string} [code_pays, numero_local]
 */
function phone_local_parts_for_form(array $user) {
    $code = trim((string) ($user['code_pays'] ?? '+221'));
    $code = preg_replace('/[^\d+]/', '', $code);
    if ($code !== '' && strpos($code, '+') !== 0) {
        $code = '+' . ltrim($code, '+');
    }
    if ($code === '') {
        $code = '+221';
    }
    $full = (string) ($user['telephone'] ?? '');
    $local = '';
    if ($full !== '' && $code !== '' && strpos($full, $code) === 0) {
        $local = substr($full, strlen($code));
    } else {
        $local = preg_replace('/\D/', '', $full);
    }
    return [$code, $local];
}

/**
 * Met à jour nom, prénom, email, téléphone pour l’utilisateur connecté.
 * Si le second élément vaut « email_verification_sent », un code OTP a été envoyé (changement d’email otp_email).
 *
 * @return array{0:bool,1:?string}
 */
function update_user_profile(array $user, $nom, $prenom, $email, $code_pays, $telephone_local) {
    $pdo = get_db_connection();
    $nom = SecurityManager::sanitize_input($nom);
    $prenom = SecurityManager::sanitize_input($prenom);
    $email = trim(SecurityManager::sanitize_input($email));
    $code_pays = SecurityManager::sanitize_input($code_pays);
    $telephone_local = SecurityManager::sanitize_input($telephone_local);
    $rt = user_registration_type($user);
    $uid = $user['id'];

    if ($nom === '' || $prenom === '') {
        return [false, 'Le nom et le prénom sont obligatoires.'];
    }

    $full_phone = normalize_full_phone($code_pays, $telephone_local);
    if (strlen(preg_replace('/\D/', '', $telephone_local)) < 6) {
        return [false, 'Numéro de téléphone invalide.'];
    }

    $stmt_other = $pdo->prepare('SELECT id FROM users WHERE telephone = ? AND id != ? LIMIT 1');
    $stmt_other->execute([$full_phone, $uid]);
    if ($stmt_other->fetch()) {
        return [false, 'Ce numéro de téléphone est déjà utilisé par un autre compte.'];
    }

    $email_null = ($email === '');
    if ($rt === 'otp_email' || $rt === 'password_email') {
        if ($email_null) {
            return [false, 'L’adresse email est obligatoire.'];
        }
        if (!SecurityManager::validate_email($email)) {
            return [false, 'Adresse email invalide.'];
        }
    } else {
        if (!$email_null && !SecurityManager::validate_email($email)) {
            return [false, 'Adresse email invalide.'];
        }
    }

    if (!$email_null) {
        $q = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $q->execute([$email, $uid]);
        if ($q->fetch()) {
            return [false, 'Cette adresse email est déjà utilisée.'];
        }
    }

    $old_email = trim((string) ($user['email'] ?? ''));
    $email_for_db = $email_null ? null : $email;

    if ($rt === 'otp_email' && !$email_null && $email !== $old_email) {
        require_once __DIR__ . '/includes/smtp_mail.php';
        if (!smtp_mail_is_configured()) {
            return [false, 'Modifier l’email nécessite un serveur SMTP configuré.'];
        }
        $otp = auth_generate_otp();
        auth_set_email_otp($uid, $otp, 15);
        $stmt = $pdo->prepare('UPDATE users SET nom = ?, prenom = ?, email = ?, telephone = ?, code_pays = ?, is_verified = 0 WHERE id = ?');
        $stmt->execute([$nom, $prenom, $email, $full_phone, $code_pays, $uid]);
        if (!auth_send_otp_email_html($email, $otp)) {
            return [false, 'Impossible d’envoyer le code de vérification à la nouvelle adresse.'];
        }
        $_SESSION['user_email'] = $email;
        return [true, 'email_verification_sent'];
    }

    $stmt = $pdo->prepare('UPDATE users SET nom = ?, prenom = ?, email = ?, telephone = ?, code_pays = ? WHERE id = ?');
    $stmt->execute([$nom, $prenom, $email_for_db, $full_phone, $code_pays, $uid]);
    $_SESSION['user_email'] = $email_for_db ?? '';
    return [true, null];
}

// Fonction pour obtenir tous les documents
function get_all_documents() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM documents");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour obtenir tous les objets perdus
function get_all_lost_items() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM lost_items");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour ajouter un objet perdu
function add_lost_item($item) {
    if (empty($item['id'])) {
        $item['id'] = generate_uuid();
    }
    if (!isset($item['user_id'])) {
        $item['user_id'] = null;
    }

    ensure_lost_item_recovery_schema();
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO lost_items (id, user_id, nom, prenom, date_naissance, lieu_naissance, categorie, telephone, description, photo1, photo2, recovery_status, date_declared) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', NOW())");
    $stmt->execute([
        $item['id'],
        $item['user_id'],
        $item['nom'],
        $item['prenom'],
        $item['date_naissance'],
        $item['lieu_naissance'],
        $item['categorie'],
        $item['telephone'],
        $item['description'] ?? null,
        $item['photo1'] ?? null,
        $item['photo2'] ?? null
    ]);
    notify_search_reminders_for_lost_item($item);
    return $item['id'];
}

function site_base_url() {
    if (defined('SITE_BASE_URL') && SITE_BASE_URL !== '') {
        return rtrim(SITE_BASE_URL, '/');
    }
    if (!empty($_SERVER['HTTP_HOST'])) {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $proto = $https ? 'https' : 'http';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $base = $dir === '' || $dir === '.' ? '' : $dir;
        return $proto . '://' . $_SERVER['HTTP_HOST'] . $base;
    }
    return '';
}

function ensure_search_reminder_tables() {
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = get_db_connection();
    $pdo->exec("CREATE TABLE IF NOT EXISTS search_reminders (
        id VARCHAR(64) NOT NULL PRIMARY KEY,
        notify_email VARCHAR(255) NOT NULL,
        nom VARCHAR(255) NOT NULL DEFAULT '',
        prenom VARCHAR(255) NOT NULL DEFAULT '',
        date_naissance DATE NULL,
        lieu_naissance VARCHAR(255) NOT NULL DEFAULT '',
        categorie VARCHAR(64) NOT NULL DEFAULT '',
        user_id VARCHAR(64) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS search_reminder_hits (
        reminder_id VARCHAR(64) NOT NULL,
        lost_item_id VARCHAR(64) NOT NULL,
        notified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (reminder_id, lost_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

function filter_lost_items_by_search_criteria(array $items, array $criteria) {
    return array_values(array_filter($items, function ($item) use ($criteria) {
        return lost_item_matches_reminder_criteria($item, $criteria);
    }));
}

function lost_item_matches_reminder_criteria(array $item, array $criteria) {
    $nom = trim($criteria['nom'] ?? '');
    $prenom = trim($criteria['prenom'] ?? '');
    $date_naissance = trim($criteria['date_naissance'] ?? '');
    $lieu_naissance = trim($criteria['lieu_naissance'] ?? '');
    $categorie = trim($criteria['categorie'] ?? '');
    $itemDate = isset($item['date_naissance']) ? substr((string) $item['date_naissance'], 0, 10) : '';
    $critDate = $date_naissance !== '' ? substr($date_naissance, 0, 10) : '';
    $matches_nom = $nom === '' || stripos($item['nom'] ?? '', $nom) !== false;
    $matches_prenom = $prenom === '' || stripos($item['prenom'] ?? '', $prenom) !== false;
    $matches_date = $critDate === '' || $itemDate === $critDate;
    $matches_lieu = $lieu_naissance === '' || stripos($item['lieu_naissance'] ?? '', $lieu_naissance) !== false;
    $matches_categorie = $categorie === '' || ($item['categorie'] ?? '') === $categorie;
    return $matches_nom && $matches_prenom && $matches_date && $matches_lieu && $matches_categorie;
}

function add_search_reminder(array $data) {
    ensure_search_reminder_tables();
    $email = trim($data['notify_email'] ?? '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Adresse email invalide.'];
    }
    $nom = trim($data['nom'] ?? '');
    $prenom = trim($data['prenom'] ?? '');
    $date_naissance = trim($data['date_naissance'] ?? '');
    $lieu_naissance = trim($data['lieu_naissance'] ?? '');
    $categorie = trim($data['categorie'] ?? '');
    if ($nom === '' && $prenom === '' && $date_naissance === '') {
        return [false, 'Indiquez au moins un nom, un prénom ou une date de naissance pour l’alerte.'];
    }
    $id = generate_uuid();
    $user_id = isset($data['user_id']) ? $data['user_id'] : null;
    $date_sql = $date_naissance !== '' ? $date_naissance : null;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare('INSERT INTO search_reminders (id, notify_email, nom, prenom, date_naissance, lieu_naissance, categorie, user_id, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
    $stmt->execute([$id, $email, $nom, $prenom, $date_sql, $lieu_naissance, $categorie, $user_id]);
    return [true, 'Votre alerte est enregistrée. Vous recevrez un email dès qu’un document correspondant sera déclaré sur Samapiece.'];
}

function notify_search_reminders_for_lost_item(array $item) {
    ensure_search_reminder_tables();
    $pdo = get_db_connection();
    $stmt = $pdo->query('SELECT * FROM search_reminders WHERE is_active = 1');
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$reminders) {
        return;
    }
    $base = site_base_url();
    $search_link = ($base !== '' ? $base . '/' : '') . 'search.php';
    $item_id = $item['id'] ?? '';
    foreach ($reminders as $r) {
        if (!lost_item_matches_reminder_criteria($item, $r)) {
            continue;
        }
        $check = $pdo->prepare('SELECT 1 FROM search_reminder_hits WHERE reminder_id = ? AND lost_item_id = ?');
        $check->execute([$r['id'], $item_id]);
        if ($check->fetchColumn()) {
            continue;
        }
        $subject = 'Samapiece : un document correspond à votre recherche';
        $safe_nom = htmlspecialchars($item['nom'] ?? '', ENT_QUOTES, 'UTF-8');
        $safe_prenom = htmlspecialchars($item['prenom'] ?? '', ENT_QUOTES, 'UTF-8');
        $safe_cat = htmlspecialchars($item['categorie'] ?? '', ENT_QUOTES, 'UTF-8');
        $body = '<p>Bonjour,</p>';
        $body .= '<p>Un document a été déclaré sur Samapiece avec des informations qui correspondent aux critères de votre alerte « Me rappeler ».</p>';
        $body .= '<p><strong>Indications :</strong> ' . $safe_prenom . ' ' . $safe_nom . ' — type : ' . $safe_cat . '</p>';
        $body .= '<p>Connectez-vous à la plateforme et lancez à nouveau une <a href="' . htmlspecialchars($search_link, ENT_QUOTES, 'UTF-8') . '">recherche</a> pour vérifier s’il s’agit bien de votre document.</p>';
        $body .= '<p>Ce message est automatique — ne répondez pas directement à cet email.</p>';
        $sent = @send_email($r['notify_email'], $subject, $body);
        if ($sent) {
            $ins = $pdo->prepare('INSERT INTO search_reminder_hits (reminder_id, lost_item_id, notified_at) VALUES (?, ?, NOW())');
            $ins->execute([$r['id'], $item_id]);
        }
    }
}

// Fonction pour obtenir les alertes actives
function get_active_alerts() {
    $pdo = get_db_connection();
    $stmt = $pdo->query("SELECT * FROM alerts WHERE is_active = 1");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonction pour vérifier si l'utilisateur est connecté
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Fonction pour vérifier si l'utilisateur est admin
function is_admin() {
    if (!is_logged_in()) return false;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

// Fonction pour exiger une connexion
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

// Fonction pour exiger un admin
function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: home.php');
        exit;
    }
}

// Fonction pour envoyer un email (SMTP si configuré, sinon mail())
function send_email($to, $subject, $body) {
    static $smtp_loaded = false;
    if (!$smtp_loaded) {
        $smtp_loaded = true;
        require_once __DIR__ . '/includes/smtp_mail.php';
    }
    if (function_exists('smtp_mail_is_configured') && smtp_mail_is_configured()) {
        return smtp_send_html($to, $subject, $body);
    }
    $headers = 'From: ' . EMAIL_FROM . "\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return @mail($to, $subject, $body, $headers);
}

// Fonction pour vérifier l'email
function verify_email($token) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE verification_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
        return $user;
    }
    return false;
}

// Fonction pour générer un code de récupération
function generate_recovery_code() {
    return SecurityManager::generate_recovery_code();
}