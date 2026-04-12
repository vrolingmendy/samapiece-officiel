<?php
require_once 'functions.php';

if (is_logged_in()) {
    SecurityManager::log_security_event('LOGOUT', ['user_id' => $_SESSION['user_id']]);
}

session_destroy();
header('Location: index.php');
exit;
?>