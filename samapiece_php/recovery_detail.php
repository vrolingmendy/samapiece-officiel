<?php
require_once 'functions.php';
require_login();

$user = get_user_by_id($_SESSION['user_id']);
if (!$user) {
    header('Location: logout.php');
    exit;
}

$id = trim($_GET['id'] ?? '');
if ($id === '') {
    header('Location: ' . samapiece_absolute_url('dashboard.php#mes-demandes-recuperation'));
    exit;
}

ensure_lost_item_recovery_schema();
$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT * FROM lost_items WHERE id = ? AND recovery_requester_id = ?');
$stmt->execute([$id, $user['id']]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: ' . samapiece_absolute_url('dashboard.php'));
    exit;
}

list($ok_code, $handover_code) = lost_item_ensure_handover_code($id);
if (!$ok_code || !$handover_code) {
    header('Location: ' . samapiece_absolute_url('dashboard.php'));
    exit;
}

$handover_url = recovery_handover_public_url($id, $handover_code);
$qr_img = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&ecc=M&data=' . rawurlencode($handover_url);

$page_title = 'Code de remise - Samapiece';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <?php require __DIR__ . '/includes/head_favicon.php'; ?>
    <style>
        :root {
            --accent: #00b7ff;
            --accent-2: #0ea5e9;
            --dark: #0f172a;
            --muted: #425466;
            --bg: #f9fbff;
            --surface: #ffffff;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            color: var(--dark);
            line-height: 1.6;
        }
        .container { max-width: 560px; margin: 0 auto; padding: 0 20px; }
        main { padding: 24px 0 0; }
        h1 {
            font-size: 1.35rem;
            margin-bottom: 8px;
        }
        .lead { color: var(--muted); margin-bottom: 24px; font-size: 0.95rem; }
        .panel {
            background: var(--surface);
            border: 1px solid #e5edf6;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 8px 28px rgba(15,23,42,0.07);
        }
        .panel img.qr {
            width: 240px;
            height: 240px;
            max-width: 100%;
            border-radius: 12px;
            border: 1px solid #e5edf6;
        }
        .code-box {
            margin-top: 20px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
            border: 1px dashed #cbd5e1;
        }
        .code-box .label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 8px;
        }
        .code-box .value {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: 0.35em;
            font-family: ui-monospace, monospace;
            color: var(--dark);
        }
        .hint {
            margin-top: 20px;
            font-size: 0.9rem;
            color: var(--muted);
            text-align: left;
        }
        .back {
            display: inline-block;
            margin-top: 24px;
            color: var(--accent-2);
            font-weight: 600;
            text-decoration: none;
        }
        .back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/includes/site_nav.php'; ?>
    <main>
        <div class="container">
            <h1>Remise du document</h1>
            <p class="lead">Présentez ce QR code ou le code au déclarant pour qu’il confirme la remise depuis son tableau de bord.</p>
            <div class="panel">
                <img class="qr" src="<?php echo htmlspecialchars($qr_img); ?>" width="240" height="240" alt="QR code de remise">
                <div class="code-box">
                    <div class="label">Code</div>
                    <div class="value"><?php echo htmlspecialchars($handover_code); ?></div>
                </div>
                <p class="hint">Le déclarant peut saisir ce code ou scanner le QR (connecté en tant que « déclarant ») pour valider la remise.</p>
            </div>
            <a class="back" href="<?php echo htmlspecialchars(samapiece_absolute_url('dashboard.php#mes-demandes-recuperation')); ?>">← Retour au tableau de bord</a>
        </div>
    </main>
    <?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>
