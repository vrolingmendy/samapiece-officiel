<?php
/**
 * Chargement .env + lecture des paramètres (local, hébergeur, variables serveur).
 */

if (!function_exists('samapiece_load_dotenv')) {
    /**
     * Charge le fichier .env à la racine du projet (clés non déjà définies dans l’environnement).
     */
    function samapiece_load_dotenv($projectRoot) {
        $path = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || (isset($line[0]) && ($line[0] === '#' || $line[0] === ';'))) {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $name = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if ($name === '') {
                continue;
            }
            if (strlen($value) >= 2 && $value[0] === '"' && substr($value, -1) === '"') {
                $value = stripcslashes(substr($value, 1, -1));
            } elseif (strlen($value) >= 2 && $value[0] === "'" && substr($value, -1) === "'") {
                $value = str_replace(["\\'", '\\\\'], ["'", '\\'], substr($value, 1, -1));
            }
            $already = getenv($name);
            if ($already !== false && $already !== '') {
                continue;
            }
            putenv($name . '=' . $value);
            $_ENV[$name] = $value;
        }
    }

    /**
     * Priorité : config.local.php (clé présente et valeur non vide) → getenv / $_SERVER / $_ENV → défaut.
     * Le fichier .env est chargé avant et alimente l’environnement si pas déjà défini sur le serveur.
     *
     * @param array<string, mixed> $local
     * @param list<string>         $envNames
     */
    function samapiece_config_pick(array $local, $localKey, array $envNames, $default = '') {
        if (array_key_exists($localKey, $local)) {
            $lv = $local[$localKey];
            if ($lv !== null && $lv !== '') {
                return $lv;
            }
        }
        foreach ($envNames as $name) {
            $v = getenv($name);
            if ($v !== false && $v !== '') {
                return $v;
            }
            if (!empty($_SERVER[$name])) {
                return $_SERVER[$name];
            }
            if (isset($_ENV[$name]) && $_ENV[$name] !== '') {
                return $_ENV[$name];
            }
        }
        return $default;
    }
}
