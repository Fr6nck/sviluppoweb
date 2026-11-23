<?php
declare(strict_types=1);

defined('CM_ROOT') || exit;

/**
 * Invio email con mail() di PHP oppure SMTP (client incluso, senza dipendenze).
 * Messaggi in testo semplice UTF-8.
 */

/** Cifratura dei segreti salvati nel database (password SMTP). */
function secret_encrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $key = hash('sha256', 'smtp|' . app_secret(), true);
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'v1:' . base64_encode($iv . $tag . $cipher);
}

function secret_decrypt(string $stored): string
{
    if (!str_starts_with($stored, 'v1:')) {
        return '';
    }
    $raw = base64_decode(substr($stored, 3), true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $key = hash('sha256', 'smtp|' . app_secret(), true);
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

function mail_header_clean(string $s): string
{
    return trim(str_replace(["\r", "\n", "\0"], ' ', $s));
}

function mail_from_address(): string
{
    $from = (string) setting('mail_from_email', '');
    if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return $from;
    }
    $host = parse_url(site_url(), PHP_URL_HOST) ?: 'localhost';
    $host = preg_replace('/^www\./', '', (string) $host);
    return 'noreply@' . $host;
}

/** Destinatari delle notifiche (uno per riga o separati da virgola). */
function notify_recipients(): array
{
    $raw = (string) setting('notify_recipients', '');
    $out = [];
    foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $addr) {
        if (filter_var($addr, FILTER_VALIDATE_EMAIL)) {
            $out[] = $addr;
        }
    }
    return array_values(array_unique($out));
}

/**
 * Invia un messaggio. Restituisce null se riuscito, altrimenti il testo dell'errore.
 *
 * @param list<string> $to
 */
function send_mail(array $to, string $subject, string $body, ?string $replyTo = null): ?string
{
    $to = array_values(array_filter($to, static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
    if (!$to) {
        return 'Nessun destinatario valido.';
    }
    $fromEmail = mail_from_address();
    $fromName = mail_header_clean((string) setting('mail_from_name', 'ChiMangia'));
    $subject = mail_header_clean($subject);
    $encodedSubject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n");
    $fromHeader = ($fromName !== '' ? mb_encode_mimeheader($fromName, 'UTF-8', 'B') . ' ' : '') . '<' . $fromEmail . '>';

    $headers = [
        'From: ' . $fromHeader,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: base64',
        'X-Mailer: ChiMangia',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $encodedBody = chunk_split(base64_encode(str_replace("\n", "\r\n", $body)), 76, "\r\n");

    $transport = (string) setting('mail_transport', 'mail');
    try {
        if ($transport === 'smtp') {
            return smtp_send($fromEmail, $to, $encodedSubject, $headers, $encodedBody);
        }
        $ok = @mail(implode(', ', $to), $encodedSubject, $encodedBody, implode("\r\n", $headers), '-f' . $fromEmail);
        return $ok ? null : 'La funzione mail() del server ha rifiutato il messaggio.';
    } catch (Throwable $e) {
        log_message('Invio email non riuscito: ' . $e->getMessage());
        return $e->getMessage();
    }
}

/**
 * Client SMTP minimale: SSL implicito (465) o STARTTLS (587), AUTH LOGIN/PLAIN.
 *
 * @param list<string> $to
 * @param list<string> $headers
 */
function smtp_send(string $from, array $to, string $encodedSubject, array $headers, string $encodedBody): ?string
{
    $host = trim((string) setting('smtp_host', ''));
    $port = (int) setting('smtp_port', 587);
    $enc = (string) setting('smtp_encryption', 'tls');
    $user = (string) setting('smtp_user', '');
    $pass = secret_decrypt((string) setting('smtp_pass', ''));
    if ($host === '') {
        return 'Server SMTP non impostato.';
    }

    $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return "Connessione a $host:$port non riuscita ($errstr).";
    }
    stream_set_timeout($fp, 20);

    $read = static function () use ($fp): array {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return [(int) substr($data, 0, 3), $data];
    };
    $cmd = static function (string $c, array $expect) use ($fp, $read): string {
        fwrite($fp, $c . "\r\n");
        [$code, $resp] = $read();
        if (!in_array($code, $expect, true)) {
            throw new RuntimeException('Il server SMTP ha risposto: ' . trim($resp));
        }
        return $resp;
    };

    try {
        [$code, $greet] = $read();
        if ($code !== 220) {
            throw new RuntimeException('Saluto SMTP inatteso: ' . trim($greet));
        }
        $ehloHost = parse_url(site_url(), PHP_URL_HOST) ?: 'localhost';
        $ehlo = $cmd('EHLO ' . $ehloHost, [250]);
        if ($enc === 'tls') {
            $cmd('STARTTLS', [220]);
            $method = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
            if (!@stream_socket_enable_crypto($fp, true, $method)) {
                throw new RuntimeException('Impossibile avviare la cifratura TLS.');
            }
            $ehlo = $cmd('EHLO ' . $ehloHost, [250]);
        }
        if ($user !== '') {
            if (stripos($ehlo, 'AUTH') !== false && stripos($ehlo, 'LOGIN') === false && stripos($ehlo, 'PLAIN') !== false) {
                $cmd('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), [235]);
            } else {
                $cmd('AUTH LOGIN', [334]);
                $cmd(base64_encode($user), [334]);
                $cmd(base64_encode($pass), [235]);
            }
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        foreach ($to as $rcpt) {
            $cmd('RCPT TO:<' . $rcpt . '>', [250, 251]);
        }
        $cmd('DATA', [354]);
        $msg = implode("\r\n", array_merge([
            'Date: ' . date('r'),
            'To: ' . implode(', ', $to),
            'Subject: ' . $encodedSubject,
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $ehloHost . '>',
        ], $headers)) . "\r\n\r\n" . $encodedBody;
        // Dot-stuffing delle righe che iniziano con un punto.
        $msg = preg_replace('/^\./m', '..', $msg);
        $cmd($msg . "\r\n.", [250]);
        $cmd('QUIT', [221, 250]);
    } catch (Throwable $e) {
        @fwrite($fp, "QUIT\r\n");
        fclose($fp);
        log_message('SMTP: ' . $e->getMessage());
        return $e->getMessage();
    }
    fclose($fp);
    return null;
}
