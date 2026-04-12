#!/usr/bin/env php
<?php
/**
 * Supprime la base Samapiece puis la recrée avec toutes les tables à jour (vide).
 *
 *   /Applications/XAMPP/xamppfiles/bin/php scripts/reset_database.php
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "CLI uniquement.\n");
    exit(1);
}

$root = dirname(__DIR__);
require_once $root . '/config.php';

$dbName = DB_NAME;
$sqlFile = $root . '/install/schema_reset.sql';

if (!is_readable($sqlFile)) {
    fwrite(STDERR, "Fichier introuvable : $sqlFile\n");
    exit(1);
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, 'Connexion MySQL : ' . $e->getMessage() . "\n");
    exit(1);
}

$dbQuoted = '`' . str_replace('`', '``', $dbName) . '`';

try {
    $pdo->exec('DROP DATABASE IF EXISTS ' . $dbQuoted);
    $pdo->exec('CREATE DATABASE ' . $dbQuoted . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE ' . $dbQuoted);
} catch (PDOException $e) {
    fwrite(STDERR, 'Recréation de la base : ' . $e->getMessage() . "\n");
    exit(1);
}

$sql = file_get_contents($sqlFile);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
$statements = array_filter(array_map('trim', preg_split('/;\s*\R/', $sql)));

foreach ($statements as $stmt) {
    if ($stmt === '' || preg_match('/^--/', $stmt)) {
        continue;
    }
    try {
        $pdo->exec($stmt);
    } catch (PDOException $e) {
        fwrite(STDERR, 'SQL : ' . $e->getMessage() . "\n" . substr($stmt, 0, 300) . "\n");
        exit(1);
    }
}

echo "OK — base « {$dbName} » recréée (vide). Inscription : register.php\n";
exit(0);
