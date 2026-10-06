<?php
namespace MHW;

/**
 * Posta in uscita, senza librerie.
 *
 *  smtp — il server di posta vero (STARTTLS sulla 587 o SSL sulla 465), con
 *         autenticazione. È la scelta per la produzione.
 *  mail — la funzione mail() di PHP: funziona su molti hosting condivisi, ma
 *         le email finiscono spesso nello spam.
 *  log  — non spedisce: scrive in storage/logs/mail.log. Per lo sviluppo e
 *         per le prove automatiche.
 *
 * Indirizzi e oggetti vengono ripuliti dagli a-capo: un a-capo in un'intestazione
 * permetterebbe di aggiungerne altre (header injection).
 */
final class Mailer
{
    /** Il motivo dell'ultimo invio non riuscito (la risposta del server SMTP): per «Prova la connessione». */
    private static string $ultimoErrore = '';

    public static function ultimoErrore(): string { return self::$ultimoErrore; }

    public static function send(string $to, string $subject, string $text, ?string $html = null): bool
    {
        $cfg = Config::get('mail');
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { Log::error('Mailer: destinatario non valido', ['to' => $to]); return false; }
        $subject = self::oneLine($subject);
        self::$ultimoErrore = '';

        try {
            return match ($cfg['transport']) {
                'smtp'  => self::smtp($cfg, $to, $subject, $text, $html),
                'mail'  => self::phpMail($cfg, $to, $subject, $text, $html),
                default => self::log($to, $subject, $text),
            };
        } catch (\Throwable $e) {
            self::$ultimoErrore = $e->getMessage();
            Log::exception($e, 'Mailer');
            if (is_resource(self::$s)) @fclose(self::$s);
            return false;
        }
    }

    private static function oneLine(string $s): string { return trim(preg_replace('/[\r\n]+/', ' ', $s) ?? ''); }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function message(array $cfg, string $to, string $subject, string $text, ?string $html): array
    {
        $from = self::oneLine($cfg['from']);
        $dominio = substr(strrchr($from, '@') ?: '@localhost', 1);
        $confine = 'mhw-' . bin2hex(random_bytes(12));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader(self::oneLine($cfg['from_name'])) . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $dominio . '>',
            'MIME-Version: 1.0',
        ];
        if ($html === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($text));
        } else {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $confine . '"';
            $body = "--$confine\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                  . chunk_split(base64_encode($text))
                  . "--$confine\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                  . chunk_split(base64_encode($html))
                  . "--$confine--\r\n";
        }
        return [$headers, $body];
    }

    private static function log(string $to, string $subject, string $text): bool
    {
        $riga = json_encode(['t' => Support::now(), 'to' => $to, 'subject' => $subject, 'text' => $text],
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return (bool) @file_put_contents(Log::dir() . '/mail.log', $riga . "\n", FILE_APPEND | LOCK_EX);
    }

    private static function phpMail(array $cfg, string $to, string $subject, string $text, ?string $html): bool
    {
        [$headers, $body] = self::message($cfg, $to, $subject, $text, $html);
        // mail() vuole destinatario e oggetto a parte.
        $extra = array_values(array_filter($headers, fn($h) => !str_starts_with($h, 'To:') && !str_starts_with($h, 'Subject:')));
        return mail($to, self::encodeHeader($subject), $body, implode("\r\n", $extra), '-f' . self::oneLine($cfg['from']));
    }

    // ------------------------------------------------------------------ SMTP

    /** @var resource */
    private static $s;

    private static function smtp(array $cfg, string $to, string $subject, string $text, ?string $html): bool
    {
        $host = (string) $cfg['host'];
        if ($host === '') throw new \RuntimeException('MAIL_HOST non impostato');
        $port = (int) $cfg['port'];
        $cifra = $cfg['encryption'];
        $remoto = ($cifra === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $s = @stream_socket_client($remoto, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) throw new \RuntimeException("SMTP: connessione a $host:$port non riuscita ($errstr)");
        stream_set_timeout($s, 20);
        self::$s = $s;

        self::expect(220);
        $io = gethostname() ?: 'localhost';
        self::cmd("EHLO $io", 250);
        if ($cifra === 'tls') {
            self::cmd('STARTTLS', 220);
            // TLS 1.2 e 1.3 per nome: su alcune versioni di PHP la costante generica sceglie un TLS vecchio che i server rifiutano.
            $metodi = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
            if (!@stream_socket_enable_crypto($s, true, $metodi)) {
                throw new \RuntimeException('SMTP: STARTTLS non riuscito');
            }
            self::cmd("EHLO $io", 250);
        }
        if ((string) $cfg['user'] !== '') {
            self::cmd('AUTH LOGIN', 334);
            self::cmd(base64_encode((string) $cfg['user']), 334);
            // La risposta alla password non va nel messaggio d'errore con il comando: si scrive solo il codice del server.
            try { self::cmd(base64_encode((string) $cfg['pass']), 235); }
            catch (\RuntimeException $e) { throw new \RuntimeException('SMTP: accesso rifiutato — ' . preg_replace('/^SMTP: risposta inattesa /', '', $e->getMessage())); }
        }
        self::cmd('MAIL FROM:<' . self::oneLine($cfg['from']) . '>', 250);
        self::cmd('RCPT TO:<' . $to . '>', [250, 251]);
        self::cmd('DATA', 354);

        [$headers, $body] = self::message($cfg, $to, $subject, $text, $html);
        $dati = implode("\r\n", $headers) . "\r\n\r\n" . str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body);
        // Una riga che comincia con un punto va raddoppiata, o chiuderebbe il messaggio.
        $dati = preg_replace('/^\./m', '..', $dati);
        fwrite($s, $dati . "\r\n.\r\n");
        self::expect(250);
        self::cmd('QUIT', 221);
        fclose($s);
        return true;
    }

    private static function cmd(string $line, int|array $atteso): string
    {
        fwrite(self::$s, $line . "\r\n");
        return self::expect($atteso);
    }

    private static function expect(int|array $atteso): string
    {
        $risposta = '';
        while (($riga = fgets(self::$s, 1024)) !== false) {
            $risposta .= $riga;
            // "250-..." continua, "250 ..." chiude.
            if (strlen($riga) < 4 || $riga[3] !== '-') break;
        }
        $codice = (int) substr($risposta, 0, 3);
        if (!in_array($codice, (array) $atteso, true)) {
            throw new \RuntimeException('SMTP: risposta inattesa ' . trim($risposta));
        }
        return $risposta;
    }
}
