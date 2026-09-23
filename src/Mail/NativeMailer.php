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
    public function send(MailMessage $message): bool
    {
        $headers = array_filter([
            'From: ' . $this->formatFrom($message),
            $message->replyTo !== '' ? 'Reply-To: ' . $this->sanitize($message->replyTo) : null,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);

        return @mail(
            $this->sanitize($message->to),
            $this->encodeHeader($message->subject),
            // RFC 5322: le righe finiscono con CRLF e non superano i 998 ottetti.
            wordwrap(str_replace(["\r\n", "\r"], "\n", $message->body), 900, "\r\n", true),
            implode("\r\n", $headers)
        );
    }

    public function isPretend(): bool
    {
        return false;
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
