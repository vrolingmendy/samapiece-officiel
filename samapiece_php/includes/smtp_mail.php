<?php
/**
 * Envoi d’email via SMTP (TLS implicite sur port 465 ou STARTTLS sur 587).
 * Nécessite OpenSSL pour ssl:// et tls.
 */

function smtp_mail_is_configured() {
    return SMTP_HOST !== ''
        && SMTP_PORT > 0
        && SMTP_USERNAME !== ''
        && SMTP_PASSWORD !== '';
}

/**
 * @return bool true si accepté par le serveur SMTP
 */
function smtp_send_html($to, $subject, $htmlBody) {
    if (!smtp_mail_is_configured()) {
        return false;
    }

    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $user = SMTP_USERNAME;
    $pass = SMTP_PASSWORD;
    $from = EMAIL_FROM;
    $encryption = strtolower(SMTP_ENCRYPTION);

    $remote = ($encryption === 'ssl' ? 'ssl' : 'tcp') . '://' . $host . ':' . $port;
    $verify = defined('SMTP_SSL_VERIFY') ? SMTP_SSL_VERIFY : true;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => $verify,
            'verify_peer_name' => $verify,
            'allow_self_signed' => !$verify,
        ],
    ]);

    $socket = @stream_socket_client(
        $remote,
        $errno,
        $errstr,
        25,
        STREAM_CLIENT_CONNECT,
        $ctx
    );

    if (!$socket) {
        return false;
    }
    stream_set_timeout($socket, 25);

    $read = function () use ($socket) {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };

    $expect = function ($resp, $codes) use ($read) {
        $ok = false;
        foreach ($codes as $c) {
            if (strpos($resp, (string) $c) === 0) {
                $ok = true;
                break;
            }
        }
        return $ok;
    };

    $write = function ($cmd) use ($socket) {
        fwrite($socket, $cmd . "\r\n");
    };

    $greeting = $read();
    if (!$expect($greeting, [220])) {
        fclose($socket);
        return false;
    }

    $ehloHost = 'localhost';
    if (preg_match('/@([a-z0-9.-]+)/i', $from, $m)) {
        $ehloHost = $m[1];
    }
    $write('EHLO ' . $ehloHost);
    $ehloResp = $read();
    if (!$expect($ehloResp, [250])) {
        fclose($socket);
        return false;
    }

    if ($encryption === 'tls' && $port === 587) {
        $write('STARTTLS');
        $tlsResp = $read();
        if (!$expect($tlsResp, [220])) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }
        $write('EHLO ' . $ehloHost);
        $read(); // capacités post-STARTTLS
    }

    $write('AUTH LOGIN');
    if (!$expect($read(), [334])) {
        fclose($socket);
        return false;
    }
    $write(base64_encode($user));
    if (!$expect($read(), [334])) {
        fclose($socket);
        return false;
    }
    $write(base64_encode($pass));
    if (!$expect($read(), [235])) {
        fclose($socket);
        return false;
    }

    $write('MAIL FROM:<' . $from . '>');
    if (!$expect($read(), [250])) {
        fclose($socket);
        return false;
    }
    $write('RCPT TO:<' . $to . '>');
    if (!$expect($read(), [250, 251])) {
        fclose($socket);
        return false;
    }
    $write('DATA');
    if (!$expect($read(), [354])) {
        fclose($socket);
        return false;
    }

    $boundary = 'b' . bin2hex(random_bytes(8));
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $headers = [
        'From: ' . $from,
        'To: ' . $to,
        'Subject: ' . $subj,
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
    ];
    $body = chunk_split(base64_encode($htmlBody));
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    $write($payload);
    if (!$expect($read(), [250])) {
        fclose($socket);
        return false;
    }
    $write('QUIT');
    fclose($socket);
    return true;
}
