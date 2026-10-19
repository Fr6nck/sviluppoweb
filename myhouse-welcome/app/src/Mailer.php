<?php
namespace MHW;

/**
 * Posta in uscita, senza librerie.
 *
 *  smtp — il server di posta vero (STARTTLS sulla 587 o SSL sulla 465), con
 *         autenticazione. È la scelta per la produzione.
 *  mail — la funzione mail() di PHP: funziona su molti hosting condivisi, ma
 *         le email finiscono spesso nello spam.
 *  log  — non spedisce: scrive in storage/logs/mail-AAAA-MM-GG.log (tenuto 7 giorni, 8I). Per lo sviluppo e
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

    /** 9I · Il posto della riga «Codice cliente …» nel piede: send() la scrive se il destinatario è un cliente, se no la toglie. */
    private const SEGNO_CODICE = '<!--codice-cliente-->';

    /** 9I · Il codice cliente del destinatario, se è un cliente (non la vetrina, non un amico invitato, non noi). */
    private static function codiceDi(string $to): string
    {
        try {
            if (!Codici::disponibili()) return '';
            $to = mb_strtolower(trim($to));
            if ($to === '' || $to === mb_strtolower(trim((string) Config::get('vetrina_email', '')))) return '';
            return (string) Db::val("SELECT a.code FROM users u JOIN accounts a ON a.user_id = u.id WHERE LOWER(u.email) = ? AND u.role = 'host'", [$to], '');
        } catch (\Throwable) { return ''; }
    }

    /** 9H · Il Reply-To degli invii in corso, se non è quello di serie (l'email assistenza). */
    private static ?string $rispondiA = null;

    /**
     * 9H · Esegue $invio con un altro Reply-To (per esempio l'email commerciale per i preventivi e gli inviti),
     * poi torna a quello di serie anche se qualcosa va storto.
     */
    public static function conRispostaA(string $indirizzo, callable $invio): mixed
    {
        $prima = self::$rispondiA;
        self::$rispondiA = $indirizzo;
        try { return $invio(); } finally { self::$rispondiA = $prima; }
    }

    /** 9H · Un'email di pagamento (rinnovo, scadenza, cambio piano, rimborso): Reply-To all'email amministrazione. */
    public static function diPagamento(callable $invio): mixed { return self::conRispostaA(Support::amministrazione(), $invio); }

    /** 9H · Un avviso interno a noi sui pagamenti (fallito, rimborso): all'email amministrazione. Non blocca mai chi lo chiama. */
    public static function avvisaAmministrazione(string $oggetto, array $paragrafi, ?array $bottone = null): void
    {
        $a = Support::amministrazione();
        if ($a === '') return;
        try { self::manda($a, $oggetto, $oggetto, $paragrafi, $bottone); }
        catch (\Throwable $e) { Log::exception($e, 'avviso all\'amministrazione'); }
    }

    // ------------------------------------------------------------------ email con il marchio (7A)

    /**
     * L'HTML di un'email con il marchio (8A: impaginazione dei grandi servizi). Tutto centrato: in alto
     * il marchio come immagine (assets/email/marchio-email.png, 282×42; con le immagini bloccate resta
     * l'alt «myhouse welcome»), il titolo in serif, il testo largo al massimo 440 px, UN bottone pieno con
     * il link in chiaro sotto, la nota piccola. Sotto una riga sottile il piede: ragione sociale, partita
     * IVA e indirizzo, poi Assistenza · Privacy · Termini, e a destra le icone social solo se in «SEO e
     * GEO» ci sono gli indirizzi. Tabelle e stili in linea (Gmail, Outlook, Apple Mail). I paragrafi sono
     * testo semplice (si escapano); $piede è HTML già pulito (per esempio «non mandarmene più»).
     * @param array{0:string,1:string}|null $bottone [testo, indirizzo]
     * @param list<array{0:string,1:string}> $scelte risposte a un clic, link a contorno uno sotto l'altro (7A)
     */
    public static function layout(string $titolo, array $paragrafi, ?array $bottone, ?string $nota, ?string $piede, array $scelte = []): string
    {
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $chi = Legale::rigaTitolare();   // 8J: il titolare (9K: con il REA, senza la PEC)
        $aiuto = Support::contatto();
        $radice = Support::radice();
        $sito = Support::baseUrl();
        $anteprima = mb_substr(trim((string) ($paragrafi[0] ?? $titolo)), 0, 140);
        $font = "font-family:'Helvetica Neue',Helvetica,Arial,sans-serif";
        $serif = "font-family:Gloock,Georgia,'Times New Roman',serif";
        $grigio = '#6a5b48';
        $h = '<!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<meta name="color-scheme" content="light"><title>' . $e($titolo) . '</title></head>'
           . '<body style="margin:0;padding:0;background:#faf5ec;' . $font . ';color:#231b12">'
           . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#faf5ec">' . $e($anteprima) . '</div>'
           . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#faf5ec"><tr><td align="center" style="padding:32px 12px">'
           . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#ffffff;border-radius:16px">'
           . '<tr><td align="center" style="padding:40px 28px 8px">'
           . '<img src="' . $e($radice . '/assets/email/marchio-email.png') . '" width="282" height="42" alt="myhouse welcome" '
           . 'style="display:block;width:282px;max-width:100%;height:auto;border:0;' . $font . ';font-size:22px;font-weight:600;color:#231b12">'
           . '</td></tr>'
           . '<tr><td align="center" style="padding:32px 28px 0">'
           . '<h1 style="margin:0 auto 20px;max-width:440px;' . $serif . ';font-weight:400;font-size:30px;line-height:36px;color:#231b12;text-align:center">' . $e($titolo) . '</h1>';
        foreach ($paragrafi as $p) {
            $h .= '<p style="margin:0 auto 16px;max-width:440px;' . $font . ';font-size:16px;line-height:24px;color:#231b12;text-align:center">' . nl2br($e((string) $p)) . '</p>';
        }
        if ($bottone) {
            [$testoB, $url] = $bottone;
            $h .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:12px auto 12px"><tr>'
                . '<td align="center" style="border-radius:999px;background:#b4451f"><a href="' . $e($url) . '" style="display:inline-block;padding:15px 30px;' . $font
                . ';font-size:16px;font-weight:600;color:#fff8f2;text-decoration:none;border-radius:999px">' . $e($testoB) . '</a></td></tr></table>'
                . '<p style="margin:0 auto 8px;max-width:440px;' . $font . ';font-size:12px;line-height:18px;color:' . $grigio . ';text-align:center">Se il bottone non si apre, copia questo indirizzo nel browser:<br>'
                . '<a href="' . $e($url) . '" style="color:' . $grigio . ';word-break:break-all">' . $e($url) . '</a></p>';
        }
        foreach ($scelte as [$testoS, $urlS]) {
            $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 8px;max-width:440px"><tr>'
                . '<td align="center" style="border:1px solid #d9cbb6;border-radius:12px"><a href="' . $e($urlS) . '" style="display:block;padding:12px 16px;' . $font
                . ';font-size:16px;font-weight:600;color:#b4451f;text-decoration:none;text-align:center">' . $e($testoS) . '</a></td></tr></table>';
        }
        if ($nota !== null && $nota !== '') $h .= '<p style="margin:16px auto 0;max-width:440px;' . $font . ';font-size:13px;line-height:20px;color:' . $grigio . ';text-align:center">' . $e($nota) . '</p>';
        $h .= '</td></tr><tr><td style="padding:32px 28px 0"><div style="border-top:1px solid #e9e0d0;height:1px;line-height:1px;font-size:1px">&nbsp;</div></td></tr>';

        // Il piede: a sinistra chi siamo e i link, a destra le icone social (solo quelle che ci sono).
        $link = fn(string $t, string $u) => '<a href="' . $e($u) . '" style="color:' . $grigio . ';text-decoration:underline">' . $e($t) . '</a>';
        $voci = array_filter([$aiuto !== '' ? $link('Assistenza', 'mailto:' . $aiuto) : '', $link('Privacy', $sito . '/privacy'), $link('Termini', $sito . '/termini')]);
        $icone = '';
        foreach (self::social() as [$nome, $u]) {
            $icone .= '<a href="' . $e($u) . '" style="display:inline-block;margin-left:8px;text-decoration:none"><img src="' . $e($radice . '/assets/email/social-' . $nome . '.png')
                    . '" width="28" height="28" alt="' . $e(ucfirst($nome)) . '" style="display:block;border:0;' . $font . ';font-size:11px;color:' . $grigio . '"></a>';
        }
        $h .= '<tr><td style="padding:20px 28px 32px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td valign="top" style="' . $font . ';font-size:12px;line-height:18px;color:' . $grigio . ';text-align:left">'
            . ($chi !== '' ? $e($chi) . '<br>' : '') . implode(' · ', $voci) . self::SEGNO_CODICE
            . '</td>' . ($icone !== '' ? '<td valign="top" align="right" style="white-space:nowrap;padding-left:12px">' . $icone . '</td>' : '') . '</tr></table>'
            . ($piede !== null && $piede !== '' ? '<p style="margin:16px 0 0;' . $font . ';font-size:12px;line-height:18px;color:' . $grigio . '">' . $piede . '</p>' : '')
            . '</td></tr></table></td></tr></table></body></html>';
        return $h;
    }

    /** Le icone del piede: [nome dell'icona, indirizzo] dagli indirizzi di «SEO e GEO». */
    private static function social(): array
    {
        $out = [];
        try { $elenco = Seo::social(); } catch (\Throwable) { return []; }
        foreach ($elenco as $u) {
            $host = strtolower((string) parse_url($u, PHP_URL_HOST));
            $nome = match (true) {
                str_contains($host, 'instagram') => 'instagram', str_contains($host, 'facebook') || $host === 'fb.com' => 'facebook',
                str_contains($host, 'tiktok') => 'tiktok', str_contains($host, 'linkedin') => 'linkedin',
                str_contains($host, 'youtube') || $host === 'youtu.be' => 'youtube', $host === 'x.com' || str_contains($host, 'twitter') => 'x',
                default => 'web',
            };
            $out[] = [$nome, $u];
        }
        return array_slice($out, 0, 6);
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
        $t .= "MyHouse Welcome" . ($aiuto !== '' ? "\nHai bisogno di aiuto? Scrivici a $aiuto" : '') . self::SEGNO_CODICE;
        if ($piede !== null && $piede !== '') $t .= "\n\n—\n" . $piede;
        return $t;
    }

    /**
     * Manda un'email con il marchio: HTML (layout) e testo insieme.
     * @param array{0:string,1:string}|null $bottone
     * @param array{0:string,1:string}|null $piede [html, testo]
     */
    /**
     * 9H · Il mittente sta sul dominio del sito? (SPF e DKIM valgono per quel dominio.) Vuoto, un IP o localhost: non si giudica.
     * Senza argomenti usa mail.from e base_url della configurazione.
     */
    public static function mittenteFuoriDominio(?string $mittente = null, ?string $base = null): bool
    {
        $mittente = strtolower(trim($mittente ?? (string) (Config::get('mail')['from'] ?? '')));
        $host = strtolower((string) parse_url($base ?? (string) Config::get('base_url'), PHP_URL_HOST));
        $host = (string) preg_replace('/^www\./', '', $host);
        if ($mittente === '' || $host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) return false;
        return !str_ends_with($mittente, '@' . $host);
    }

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
        // 9I · «Codice cliente MW-…» nel piede delle email a un cliente.
        $cod = self::codiceDi($to);
        $text = str_replace(self::SEGNO_CODICE, $cod !== '' ? "\nCodice cliente $cod" : '', $text);
        if ($html !== null) $html = str_replace(self::SEGNO_CODICE, $cod !== '' ? '<br>Codice cliente ' . htmlspecialchars($cod, ENT_QUOTES, 'UTF-8') : '', $html);

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
        // 9H · Chi risponde a un'email automatica scrive all'assistenza (Impostazioni → Sito), qualunque sia il mittente.
        $rispondi = self::$rispondiA ?? Support::contatto();
        $risposte = filter_var($rispondi, FILTER_VALIDATE_EMAIL) ? self::oneLine($rispondi) : '';
        if ($risposte !== '') $headers[] = 'Reply-To: <' . $risposte . '>';
        if ($html === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($text));
        } else {
            // 9C · Il marchio viaggia dentro l'email (multipart/related, Content-ID «marchio»): si vede anche con le immagini
            // esterne bloccate. Le icone social restano esterne (piccole, facoltative). Niente marchio nel file: come prima.
            [$html, $marchio] = self::incorporaMarchio($html);
            $alternativa = "--$confine\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                  . chunk_split(base64_encode($text))
                  . "--$confine\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                  . chunk_split(base64_encode($html))
                  . "--$confine--\r\n";
            if ($marchio === null) {
                $headers[] = 'Content-Type: multipart/alternative; boundary="' . $confine . '"';
                $body = $alternativa;
            } else {
                $esterno = 'mhw-rel-' . bin2hex(random_bytes(12));
                $headers[] = 'Content-Type: multipart/related; type="multipart/alternative"; boundary="' . $esterno . '"';
                $body = "--$esterno\r\nContent-Type: multipart/alternative; boundary=\"$confine\"\r\n\r\n" . $alternativa
                      . "--$esterno\r\nContent-Type: image/png; name=\"marchio-email.png\"\r\nContent-Transfer-Encoding: base64\r\n"
                      . "Content-ID: <marchio>\r\nContent-Disposition: inline; filename=\"marchio-email.png\"\r\n\r\n"
                      . chunk_split(base64_encode($marchio)) . "--$esterno--\r\n";
            }
        }
        return [$headers, $body];
    }

    /** Il file del marchio delle email (assets/email/marchio-email.png), se c'è. */
    public static function fileMarchio(): string
    {
        $f = defined('MHW_PUBLIC') ? MHW_PUBLIC . '/assets/email/marchio-email.png' : dirname(MHW_APP) . '/assets/email/marchio-email.png';
        return is_file($f) ? $f : '';
    }

    /**
     * 9C · Sostituisce l'indirizzo esterno del marchio con «cid:marchio» e dà i byte del PNG da allegare in linea.
     * Senza il file (o senza il marchio nell'HTML) l'HTML resta com'è. @return array{0:string,1:?string}
     */
    public static function incorporaMarchio(string $html): array
    {
        $f = self::fileMarchio();
        if ($f === '' || !str_contains($html, '/assets/email/marchio-email.png')) return [$html, null];
        $bytes = (string) @file_get_contents($f);
        if ($bytes === '' || strlen($bytes) > 60000) return [$html, null];   // un marchio enorme non va nelle email
        return [preg_replace('#src="[^"]*/assets/email/marchio-email\.png"#', 'src="cid:marchio"', $html) ?? $html, $bytes];
    }

    private static function log(string $to, string $subject, string $text, ?string $html = null): bool
    {
        $riga = json_encode(['t' => Support::now(), 'to' => $to, 'subject' => $subject, 'text' => $text],
                            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // In prova si tiene anche l'HTML, per vederlo nel browser: storage/logs/email/ (fuori dal web come i registri della posta).
        if ($html !== null) {
            $dir = Log::dir() . '/email';
            if (is_dir($dir) || @mkdir($dir, 0775, true)) {
                $nome = gmdate('Ymd-His') . '-' . substr(Support::slug(explode(' — ', $subject)[0]), 0, 60);
                // 9C: l'HTML come parte nell'email (con «cid:marchio») e il messaggio intero (.eml), con il marchio in linea.
                [$htmlCid] = self::incorporaMarchio($html);
                @file_put_contents($dir . '/' . $nome . '.html', $htmlCid, LOCK_EX);
                [$h, $b] = self::message(Config::get('mail'), $to, $subject, $text, $html);
                @file_put_contents($dir . '/' . $nome . '.eml', implode("\r\n", $h) . "\r\n\r\n" . $b, LOCK_EX);
            }
        }
        return (bool) @file_put_contents(Log::file('mail'), $riga . "\n", FILE_APPEND | LOCK_EX);
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
