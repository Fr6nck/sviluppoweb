<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

/**
 * SMTP autenticato, senza librerie.
 *
 * È il trasporto consigliato in produzione: il messaggio parte dalla casella
 * del dominio, quindi passa i controlli SPF e DKIM e arriva nella posta in
 * arrivo invece che in quella indesiderata. Su Hostinger le credenziali sono
 * quelle della casella creata nell'hPanel, host `smtp.hostinger.com`, porta
 * 587 con STARTTLS oppure 465 con TLS implicito.
 *
 * L'utente per l'accesso è MAIL_SMTP_USER; se è vuoto ma la password c'è,
 * si usa il mittente: su Hostinger l'utente è l'indirizzo della casella, lo
 * stesso che spedisce. Senza utente e senza password non si fa l'accesso, e
 * Hostinger rifiuta con «530 Authentication required».
 *
 * Quando un messaggio non parte, ultimoErrore() dice a che punto si è
 * fermato e che cosa ha risposto il server, e la stessa riga va nel registro
 * degli errori di PHP: senza, «non è partito» non dice se è la password,
 * l'utente o il server. La password non compare mai.
 *
 * L'implementazione è volutamente essenziale — testo semplice — perché è
 * tutto ciò che questo sito spedisce. Se un giorno servissero allegati o
 * HTML, è il momento di aggiungere PHPMailer via Composer e sostituire questa
 * classe: il resto del sito non se ne accorge.
 */
final class SmtpMailer implements MailerInterface
{
    private ?string $errore = null;

    /** @param array{host:string,port:int,user:string,password:string,encryption:string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function send(MailMessage $message): bool
    {
        $this->errore = null;
        $host = (string) ($this->config['host'] ?? '');
        if ($host === '') {
            return $this->fallito('MAIL_SMTP_HOST è vuoto: manca il server di posta (su Hostinger: smtp.hostinger.com).');
        }
        $destinatari = $message->recipients();
        if ($destinatari === []) {
            return $this->fallito('Nessun destinatario: controlla gli indirizzi in Ricezione e-mail.');
        }

        $port      = (int) ($this->config['port'] ?? 587);
        $encrypted = strtolower((string) ($this->config['encryption'] ?? 'tls'));
        $endpoint  = ($encrypted === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $mittente  = $this->sanitize($message->fromAddress);
        $password  = (string) ($this->config['password'] ?? '');
        $user      = trim((string) ($this->config['user'] ?? ''));
        if ($user === '' && $password !== '') {
            $user = $mittente;
        }

        $socket = @stream_socket_client($endpoint, $errno, $errstr, 15);
        if (!$socket) {
            return $this->fallito("Il server di posta {$host}, porta {$port}, non risponde"
                . ($errstr !== '' ? ' (' . trim($errstr) . ')' : '') . '. Controlla MAIL_SMTP_HOST, MAIL_SMTP_PORT e MAIL_SMTP_ENCRYPTION: '
                . 'su Hostinger smtp.hostinger.com, 465 con ssl oppure 587 con tls.');
        }
        stream_set_timeout($socket, 15);

        // Ogni passo dice che cosa significa, se il server risponde di no.
        $passo = '';
        try {
            $passo = "Il server di posta {$host} non ha salutato";
            $this->expect($socket, 220);
            $passo = "Il server di posta {$host} non accetta la presentazione (EHLO)";
            $this->command($socket, 'EHLO ' . $this->helo(), 250);

            if ($encrypted === 'tls') {
                $passo = "Il server di posta {$host} non accetta la connessione cifrata (STARTTLS): con la porta 465 serve MAIL_SMTP_ENCRYPTION=ssl";
                $this->command($socket, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('cifratura non avviata');
                }
                // Dopo STARTTLS la sessione ricomincia: l'EHLO va rifatto.
                $passo = "Il server di posta {$host} non accetta la presentazione (EHLO) dopo la cifratura";
                $this->command($socket, 'EHLO ' . $this->helo(), 250);
            }

            if ($user !== '') {
                $passo = "Il server di posta non accetta l'accesso con l'utente {$user}. Controlla MAIL_SMTP_USER e MAIL_SMTP_PASSWORD: "
                       . 'devono essere l\'indirizzo e la password della casella, come in hPanel → Email';
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($user), 334);
                $this->command($socket, base64_encode($password), 235);
            }

            $passo = $user === ''
                ? "Il server di posta non accetta {$mittente} come mittente senza accesso: MAIL_SMTP_USER e MAIL_SMTP_PASSWORD sono vuoti. "
                  . 'Scrivi in Configurazione l\'utente (l\'indirizzo della casella) e la password della casella'
                : "Il server di posta non accetta {$mittente} come mittente. MAIL_FROM_ADDRESS deve essere la stessa casella di MAIL_SMTP_USER ({$user})";
            $this->command($socket, 'MAIL FROM:<' . $mittente . '>', 250);
            foreach ($destinatari as $destinatario) {
                $passo = "Il server di posta rifiuta il destinatario {$destinatario}"
                       . ($user === '' ? ': senza utente e password non si spedisce fuori dal dominio' : '');
                // 250 accettato, 251 inoltrato: vanno bene tutti e due.
                $this->command($socket, 'RCPT TO:<' . $destinatario . '>', 250, 251);
            }
            $passo = 'Il server di posta ha rifiutato il messaggio';
            $this->command($socket, 'DATA', 354);
            $this->write($socket, $this->buildData($message) . "\r\n.");
            $this->expect($socket, 250);
            // Il messaggio è consegnato: un QUIT senza risposta non cambia niente.
            try {
                $this->command($socket, 'QUIT', 221);
            } catch (\RuntimeException) {
            }
        } catch (\RuntimeException $e) {
            return $this->fallito($passo . '. Risposta: ' . $e->getMessage() . '.');
        } finally {
            fclose($socket);
        }

        return true;
    }

    public function isPretend(): bool
    {
        return false;
    }

    public function ultimoErrore(): ?string
    {
        return $this->errore;
    }

    /** Ricorda perché non è partito e lo scrive nel registro degli errori. */
    private function fallito(string $perche): bool
    {
        $this->errore = $perche;
        error_log('Posta non spedita (SMTP): ' . $perche);

        return false;
    }

    private function buildData(MailMessage $message): string
    {
        $mittente = $this->sanitize($message->fromAddress);
        $dominio  = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) substr((string) strrchr($mittente, '@'), 1)) ?: 'localhost';
        $headers = array_filter([
            'Date: ' . (new \DateTimeImmutable('now'))->format(\DATE_RFC2822),
            // Gmail e altri rifiutano o mettono da parte i messaggi senza.
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $dominio . '>',
            'From: ' . ($message->fromName === ''
                ? $mittente
                : sprintf('%s <%s>', $this->encodeHeader($message->fromName), $mittente)),
            'To: ' . implode(', ', $message->recipients()),
            $message->replyTo !== '' ? 'Reply-To: ' . $this->sanitize($message->replyTo) : null,
            'Subject: ' . $this->encodeHeader($message->subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);

        $body = str_replace(["\r\n", "\r"], "\n", $message->body);
        // Un punto a inizio riga va raddoppiato, altrimenti chiude il messaggio.
        $body = preg_replace('/^\./m', '..', $body) ?? $body;

        return implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body);
    }

    /** @param resource $socket */
    private function command($socket, string $line, int ...$expected): void
    {
        $this->write($socket, $line);
        $this->expect($socket, ...$expected);
    }

    /** @param resource $socket */
    private function write($socket, string $line): void
    {
        if (@fwrite($socket, $line . "\r\n") === false) {
            throw new \RuntimeException('la connessione si è chiusa');
        }
    }

    /**
     * Legge la risposta del server; se il codice non è fra quelli attesi,
     * l'eccezione porta la risposta così com'è (es. «535 5.7.8 Error:
     * authentication failed»).
     *
     * @param resource $socket
     */
    private function expect($socket, int ...$codes): void
    {
        $line = '';
        do {
            $line = (string) fgets($socket, 515);
            if ($line === '') {
                throw new \RuntimeException('nessuna, la connessione si è chiusa o è scaduta');
            }
            // Le risposte su più righe hanno un trattino in quarta posizione.
        } while (isset($line[3]) && $line[3] === '-');

        if (!in_array((int) substr($line, 0, 3), $codes, true)) {
            throw new \RuntimeException('«' . mb_substr(trim($line), 0, 200) . '»');
        }
    }

    private function helo(): string
    {
        $host = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');

        return preg_replace('/[^A-Za-z0-9.\-]/', '', $host) ?: 'localhost';
    }

    private function sanitize(string $value): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $value));
    }

    private function encodeHeader(string $value): string
    {
        $value = $this->sanitize($value);

        return preg_match('/[\x80-\xFF]/', $value) === 1
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }
}
