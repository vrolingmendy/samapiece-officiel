<?php
require_once 'functions.php';
require_login();

$id = trim($_GET['d'] ?? '');
$code = $_GET['c'] ?? '';

list($ok, $msg) = confirm_recovery_handover($id, $_SESSION['user_id'], $code);
$_SESSION['handover_flash'] = ['ok' => $ok, 'message' => $msg];
header('Location: ' . samapiece_absolute_url('dashboard.php#mes-declarations'));
exit;
