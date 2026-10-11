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

    // ------------------------------------------------------------------ email con il marchio (7A)

    /**
     * L'HTML di un'email con il marchio: sfondo carta, riquadro bianco, «myhouse welcome» in testo,
     * titolo in serif, un solo bottone mattone con il link in chiaro sotto, nota piccola e piede con
     * ragione sociale, partita IVA e indirizzo dell'assistenza. Tabelle e stili in linea (Gmail,
     * Outlook, Apple Mail), niente immagini esterne. I paragrafi sono testo semplice (si escapano);
     * $piede è HTML già pulito (per esempio il link «non mandarmene più»).
     * @param array{0:string,1:string}|null $bottone [testo, indirizzo]
     * @param list<array{0:string,1:string}> $scelte risposte a un clic, link a contorno uno sotto l'altro (7A)
     */
    public static function layout(string $titolo, array $paragrafi, ?array $bottone, ?string $nota, ?string $piede, array $scelte = []): string
    {
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $l = Config::get('legal') ?? [];
        $chi = implode(' · ', array_filter([(string) ($l['company'] ?? ''), ($l['company_vat'] ?? '') !== '' ? 'P.IVA ' . $l['company_vat'] : '']));
        $aiuto = Support::contatto();
        $anteprima = mb_substr(trim((string) ($paragrafi[0] ?? $titolo)), 0, 140);
        $font = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif";
        $h = '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<meta name="color-scheme" content="light"><title>' . $e($titolo) . '</title></head>'
           . '<body style="margin:0;padding:0;background:#faf5ec;' . $font . ';color:#231b12">'
           . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#faf5ec">' . $e($anteprima) . '</div>'
           . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#faf5ec"><tr><td align="center" style="padding:24px 12px">'
           . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px">'
           . '<tr><td style="padding:0 8px 16px;' . $font . ';font-size:18px;font-weight:600;color:#231b12;letter-spacing:-.01em">myhouse welcome<span style="color:#b4451f">.</span></td></tr>'
           . '<tr><td style="background:#ffffff;border-radius:16px;padding:32px 28px">'
           . '<h1 style="margin:0 0 16px;font-family:Gloock,Georgia,\'Times New Roman\',serif;font-weight:400;font-size:28px;line-height:34px;color:#231b12">' . $e($titolo) . '</h1>';
        foreach ($paragrafi as $p) {
            $h .= '<p style="margin:0 0 16px;' . $font . ';font-size:16px;line-height:24px;color:#231b12">' . nl2br($e((string) $p)) . '</p>';
        }
        if ($bottone) {
            [$testoB, $url] = $bottone;
            $h .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 12px"><tr>'
                . '<td style="border-radius:999px;background:#b4451f"><a href="' . $e($url) . '" style="display:inline-block;padding:14px 24px;' . $font
                . ';font-size:16px;font-weight:600;color:#fff8f2;text-decoration:none;border-radius:999px">' . $e($testoB) . '</a></td></tr></table>'
                . '<p style="margin:0 0 16px;' . $font . ';font-size:13px;line-height:20px;color:#6a5b48">Se il bottone non si apre, copia questo indirizzo nel browser:<br>'
                . '<a href="' . $e($url) . '" style="color:#b4451f;word-break:break-all">' . $e($url) . '</a></p>';
        }
        foreach ($scelte as [$testoS, $urlS]) {
            $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px"><tr>'
                . '<td style="border:1px solid #d9cbb6;border-radius:12px"><a href="' . $e($urlS) . '" style="display:block;padding:12px 16px;' . $font
                . ';font-size:16px;font-weight:600;color:#b4451f;text-decoration:none">' . $e($testoS) . '</a></td></tr></table>';
        }
        if ($nota !== null && $nota !== '') $h .= '<p style="margin:16px 0 0;' . $font . ';font-size:13px;line-height:20px;color:#6a5b48">' . $e($nota) . '</p>';
        $h .= '</td></tr><tr><td style="padding:16px 8px 0;' . $font . ';font-size:12px;line-height:18px;color:#6a5b48">'
            . ($aiuto !== '' ? 'Hai bisogno di aiuto? Scrivici a <a href="mailto:' . $e($aiuto) . '" style="color:#6a5b48">' . $e($aiuto) . '</a><br>' : '')
            . ($chi !== '' ? 'MyHouse Welcome · ' . $e($chi) . '<br>' : '')
            . ($piede !== null && $piede !== '' ? $piede : '')
            . '</td></tr></table></td></tr></table></body></html>';
        return $h;
    }

    /** La versione testo della stessa email. $piede: testo semplice. */
    public static function testo(string $titolo, array $paragrafi, ?array $bottone, ?string $nota, ?string $piede = null, array $scelte = []): string
    {
        $aiuto = Support::contatto();
        $t = $titolo . "\n\n" . implode("\n\n", array_map('strval', $paragrafi)) . "\n\n";
        foreach ($scelte as [$testoS, $urlS]) $t .= "- $testoS: $urlS\n";
        if ($scelte) $t .= "\n";
        if ($bottone) $t .= $bottone[0] . ': ' . $bottone[1] . "\n\n";
        if ($nota !== null && $nota !== '') $t .= $nota . "\n\n";
        $t .= "MyHouse Welcome" . ($aiuto !== '' ? "\nHai bisogno di aiuto? Scrivici a $aiuto" : '');
        if ($piede !== null && $piede !== '') $t .= "\n\n—\n" . $piede;
        return $t;
    }

    /**
     * Manda un'email con il marchio: HTML (layout) e testo insieme.
     * @param array{0:string,1:string}|null $bottone
     * @param array{0:string,1:string}|null $piede [html, testo]
     */
    public static function manda(string $to, string $oggetto, string $titolo, array $paragrafi, ?array $bottone = null, ?string $nota = null, ?array $piede = null): bool
    {
        return self::send($to, $oggetto . ' — MyHouse Welcome', self::testo($titolo, $paragrafi, $bottone, $nota, $piede[1] ?? null),
                          self::layout($titolo, $paragrafi, $bottone, $nota, $piede[0] ?? null));
    }

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
                default => self::log($to, $subject, $text, $html),
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

    private static function log(string $to, string $subject, string $text, ?string $html = null): bool
    {
        $riga = json_encode(['t' => Support::now(), 'to' => $to, 'subject' => $subject, 'text' => $text],
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // In prova si tiene anche l'HTML, per vederlo nel browser: storage/logs/email/ (fuori dal web come mail.log).
        if ($html !== null) {
            $dir = Log::dir() . '/email';
            if (is_dir($dir) || @mkdir($dir, 0775, true)) {
                $nome = gmdate('Ymd-His') . '-' . substr(Support::slug(explode(' — ', $subject)[0]), 0, 60) . '.html';
                @file_put_contents($dir . '/' . $nome, $html, LOCK_EX);
            }
        }
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
        $ehlo = self::cmd("EHLO $io", 250);
        if ($cifra === 'tls') {
            self::cmd('STARTTLS', 220);
            // TLS 1.2 e 1.3 per nome: su alcune versioni di PHP la costante generica sceglie un TLS vecchio che i server rifiutano.
            $metodi = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0);
            if (!@stream_socket_enable_crypto($s, true, $metodi)) {
                throw new \RuntimeException('SMTP: STARTTLS non riuscito');
            }
            $ehlo = self::cmd("EHLO $io", 250);
        }
        if ((string) $cfg['user'] !== '') self::accedi((string) $cfg['user'], (string) $cfg['pass'], $ehlo);
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

    /**
     * L'accesso alla casella, con i metodi che il server dichiara dopo EHLO (riga «AUTH …»):
     * LOGIN, come sempre, e PLAIN. Se il primo è rifiutato si prova l'altro, una volta: alcuni
     * server ne accettano uno solo anche se li elencano tutti e due. Nel messaggio d'errore ci
     * sono l'utente, la lunghezza della password e i metodi provati; la password mai.
     */
    private static function accedi(string $utente, string $pass, string $ehlo): void
    {
        if ($pass === '') throw new \RuntimeException('SMTP: accesso rifiutato — manca la password della casella ' . $utente);
        $offerti = preg_match('/^250[ -]AUTH[ =]([^\r\n]*)/mi', $ehlo, $m) === 1 ? array_map('strtoupper', preg_split('/\s+/', trim($m[1])) ?: []) : [];
        $metodi = array_values(array_intersect(['LOGIN', 'PLAIN'], $offerti)) ?: ['LOGIN'];
        $risposta = '';
        foreach ($metodi as $metodo) {
            try {
                if ($metodo === 'PLAIN') {
                    self::cmd('AUTH PLAIN ' . base64_encode("\0" . $utente . "\0" . $pass), 235);
                } else {
                    self::cmd('AUTH LOGIN', 334);
                    self::cmd(base64_encode($utente), 334);
                    self::cmd(base64_encode($pass), 235);
                }
                return;
            } catch (\RuntimeException $e) {
                // Il messaggio contiene solo la risposta del server, mai il comando mandato.
                $risposta = preg_replace('/^SMTP: risposta inattesa /', '', $e->getMessage());
            }
        }
        throw new \RuntimeException('SMTP: accesso rifiutato — ' . $risposta . ' [utente «' . $utente . '», password di ' . mb_strlen($pass) . ' caratteri, '
            . (count($metodi) > 1 ? 'provati ' . implode(' e ', $metodi) : 'metodo ' . $metodi[0]) . ($offerti ? '; il server offre ' . implode(' ', $offerti) : '') . ']');
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
