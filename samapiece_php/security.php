<?php
require_once 'config.php';

class SecurityManager {
    public static function get_client_hash() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown';
        return hash('sha256', $ip);
    }

    public static function sanitize_input($input) {
        if (!is_string($input)) {
            return (string)$input;
        }
        $dangerous = ['<', '>', '"', "'", '&', '%', '\\', '/'];
        $cleaned = $input;
        foreach ($dangerous as $char) {
            if ($char === '&' && strpos($input, '&') !== false) {
                continue; // Skip if it's part of an entity
            }
            $cleaned = str_replace($char, '', $cleaned);
        }
        return trim($cleaned);
    }

    public static function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validate_password($password) {
        if (strlen($password) < 8) {
            return [false, "Mot de passe trop court (minimum 8 caractères)"];
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return [false, "Doit contenir au moins une majuscule"];
        }
        if (!preg_match('/\d/', $password)) {
            return [false, "Doit contenir au moins un chiffre"];
        }
        if (!preg_match('/[!@#$%^&*()\-_=+[\]{}|;:,.<>?]/', $password)) {
            return [false, "Doit contenir au moins un caractère spécial"];
        }
        return [true, "Mot de passe valide"];
    }

    public static function hash_password($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verify_password($password, $hash) {
        if ($hash === null || $hash === '') {
            return false;
        }
        return password_verify($password, $hash);
    }

    public static function check_login_attempts($identifier) {
        $db = load_db();
        $client_hash = self::get_client_hash();
        $key = "login_attempts_{$identifier}_{$client_hash}";

        if (!isset($db['login_tracking'])) {
            $db['login_tracking'] = [];
        }

        if (!isset($db['login_tracking'][$key])) {
            $db['login_tracking'][$key] = [
                'attempts' => 0,
                'first_attempt' => date('c'),
                'locked_until' => null
            ];
            save_db($db);
        }

        $tracking = $db['login_tracking'][$key];

        if ($tracking['locked_until']) {
            $locked_time = strtotime($tracking['locked_until']);
            if (time() < $locked_time) {
                return [false, 'COMPTE_BLOQUE'];
            } else {
                $tracking['attempts'] = 0;
                $tracking['locked_until'] = null;
                $db['login_tracking'][$key] = $tracking;
                save_db($db);
            }
        }

        return [true, $tracking['attempts']];
    }

    public static function record_failed_attempt($identifier) {
        $db = load_db();
        $client_hash = self::get_client_hash();
        $key = "login_attempts_{$identifier}_{$client_hash}";

        if (!isset($db['login_tracking'])) {
            $db['login_tracking'] = [];
        }

        if (!isset($db['login_tracking'][$key])) {
            $db['login_tracking'][$key] = [
                'attempts' => 0,
                'first_attempt' => date('c'),
                'locked_until' => null
            ];
        }

        $tracking = $db['login_tracking'][$key];
        $tracking['attempts']++;

        if ($tracking['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $lock_time = date('c', strtotime("+".LOCK_TIME_MINUTES." minutes"));
            $tracking['locked_until'] = $lock_time;

            if (!isset($db['security_logs'])) {
                $db['security_logs'] = [];
            }

            $db['security_logs'][] = [
                'timestamp' => date('c'),
                'event' => 'ACCOUNT_LOCKED',
                'identifier' => $identifier,
                'client_hash' => $client_hash,
                'reason' => 'Multiple failed login attempts'
            ];
        }

        $db['login_tracking'][$key] = $tracking;
        save_db($db);
        return $tracking['attempts'];
    }

    public static function reset_login_attempts($identifier) {
        // Simplified, no db
    }

    public static function log_security_event($event, $details) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("INSERT INTO security_logs (id, event_type, user_id, details, ip_address, timestamp) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                generate_uuid(),
                $event,
                $details['user_id'] ?? null,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? null
            ]);
        } catch (Throwable $e) {
            // journalisation facultative (table absente, etc.)
        }
    }

    public static function generate_token() {
        return bin2hex(random_bytes(32));
    }

    public static function generate_recovery_code() {
        return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }
}