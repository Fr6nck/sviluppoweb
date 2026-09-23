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
 * L'implementazione è volutamente essenziale — testo semplice, un
 * destinatario — perché è tutto ciò che questo sito spedisce. Se un giorno
 * servissero allegati o HTML, è il momento di aggiungere PHPMailer via
 * Composer e sostituire questa classe: il resto del sito non se ne accorge.
 */
final class SmtpMailer implements MailerInterface
{
    /** @param array{host:string,port:int,user:string,password:string,encryption:string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function send(MailMessage $message): bool
    {
        $host = (string) ($this->config['host'] ?? '');
        if ($host === '') {
            return false;
        }

        $port      = (int) ($this->config['port'] ?? 587);
        $encrypted = strtolower((string) ($this->config['encryption'] ?? 'tls'));
        $endpoint  = ($encrypted === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $socket = @stream_socket_client($endpoint, $errno, $errstr, 15);
        if (!$socket) {
            return false;
        }
        stream_set_timeout($socket, 15);

        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO ' . $this->helo(), 250);

            if ($encrypted === 'tls') {
                $this->command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return false;
                }
                // Dopo STARTTLS la sessione ricomincia: l'EHLO va rifatto.
                $this->command($socket, 'EHLO ' . $this->helo(), 250);
            }

            $user = (string) ($this->config['user'] ?? '');
            if ($user !== '') {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($user), 334);
                $this->command($socket, base64_encode((string) ($this->config['password'] ?? '')), 235);
            }

            $this->command($socket, 'MAIL FROM:<' . $this->sanitize($message->fromAddress) . '>', 250);
            $this->command($socket, 'RCPT TO:<' . $this->sanitize($message->to) . '>', 250);
            $this->command($socket, 'DATA', 354);
            $this->write($socket, $this->buildData($message) . "\r\n.");
            $this->expect($socket, 250);
            $this->command($socket, 'QUIT', 221);
        } catch (\RuntimeException) {
            return false;
        } finally {
            fclose($socket);
        }

        return true;
    }

    public function isPretend(): bool
    {
        return false;
    }

    private function buildData(MailMessage $message): string
    {
        $headers = array_filter([
            'Date: ' . (new \DateTimeImmutable('now'))->format(\DATE_RFC2822),
            'From: ' . ($message->fromName === ''
                ? $this->sanitize($message->fromAddress)
                : sprintf('%s <%s>', $this->encodeHeader($message->fromName), $this->sanitize($message->fromAddress))),
            'To: ' . $this->sanitize($message->to),
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
    private function command($socket, string $line, int $expected): void
    {
        $this->write($socket, $line);
        $this->expect($socket, $expected);
    }

    /** @param resource $socket */
    private function write($socket, string $line): void
    {
        if (fwrite($socket, $line . "\r\n") === false) {
            throw new \RuntimeException('SMTP: scrittura fallita.');
        }
    }

    /** @param resource $socket */
    private function expect($socket, int $code): void
    {
        $line = '';
        do {
            $line = (string) fgets($socket, 515);
            if ($line === '') {
                throw new \RuntimeException('SMTP: nessuna risposta.');
            }
            // Le risposte su più righe hanno un trattino in quarta posizione.
        } while (isset($line[3]) && $line[3] === '-');

        if ((int) substr($line, 0, 3) !== $code) {
            throw new \RuntimeException('SMTP: risposta inattesa — ' . trim($line));
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
