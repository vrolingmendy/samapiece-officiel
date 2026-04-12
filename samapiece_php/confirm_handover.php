<?php
require_once 'functions.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . samapiece_absolute_url('dashboard.php#mes-declarations'));
    exit;
}

$lost_item_id = trim($_POST['lost_item_id'] ?? '');
$code = $_POST['code'] ?? '';

list($ok, $msg) = confirm_recovery_handover($lost_item_id, $_SESSION['user_id'], $code);
$_SESSION['handover_flash'] = ['ok' => $ok, 'message' => $msg];
header('Location: ' . samapiece_absolute_url('dashboard.php#mes-declarations'));
exit;
