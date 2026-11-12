<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

/**
 * La funzione mail() di PHP.
 *
 * È il trasporto più semplice e su Hostinger funziona senza configurare
 * nulla. Ha però un limite noto: i messaggi spediti così finiscono più
 * spesso nella posta indesiderata, perché non sono autenticati dal dominio.
 * Per una struttura che riceve richieste di prenotazione conviene passare a
 * SMTP con la casella del dominio — vedi SmtpMailer.
 */
final class NativeMailer implements MailerInterface
{
    private ?string $errore = null;

    public function send(MailMessage $message): bool
    {
        $this->errore = null;
        $mittente = $this->sanitize($message->fromAddress);
        $dominio  = preg_replace('/[^A-Za-z0-9.\-]/', '', (string) substr((string) strrchr($mittente, '@'), 1)) ?: 'localhost';
        $headers = array_filter([
            'From: ' . $this->formatFrom($message),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $dominio . '>',
            $message->replyTo !== '' ? 'Reply-To: ' . $this->sanitize($message->replyTo) : null,
        ]);
        $mime = Mime::componi($message);
        $headers = [...$headers, ...$mime['intestazioni']];

        $destinatari = $message->recipients();
        if ($destinatari === []) {
            $this->errore = 'Nessun destinatario: controlla gli indirizzi in Ricezione e-mail.';

            return false;
        }

        $partito = @mail(
            implode(', ', $destinatari),
            $this->encodeHeader($message->subject),
            // RFC 5322: le righe finiscono con CRLF e non superano i 998 ottetti.
            $message->html === '' && $message->allegati === []
                ? wordwrap(str_replace(["\r\n", "\r"], "\n", $message->body), 900, "\r\n", true)
                : $mime['corpo'],
            implode("\r\n", $headers)
        );
        if (!$partito) {
            $this->errore = 'La funzione mail() del server ha rifiutato il messaggio'
                . (($ultimo = error_get_last()) !== null ? ': ' . $ultimo['message'] : '')
                . '. Meglio MAIL_TRANSPORT=smtp, con la casella del dominio.';
            error_log('Posta non spedita (mail): ' . $this->errore);
        }

        return $partito;
    }

    public function isPretend(): bool
    {
        return false;
    }

    public function ultimoErrore(): ?string
    {
        return $this->errore;
    }

    private function formatFrom(MailMessage $message): string
    {
        $address = $this->sanitize($message->fromAddress);

        return $message->fromName === ''
            ? $address
            : sprintf('%s <%s>', $this->encodeHeader($this->sanitize($message->fromName)), $address);
    }

    /**
     * Toglie gli a capo da qualunque valore che finisca in un'intestazione.
     * È la difesa contro l'header injection: senza questa riga un indirizzo
     * con dentro un «\n» aggiunge destinatari al messaggio.
     */
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
