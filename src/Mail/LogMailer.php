<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

/**
 * Scrive il messaggio su file invece di spedirlo.
 *
 * È il trasporto del prototipo: nessuna credenziale, nessuna casella vera,
 * nessun messaggio che parte per sbaglio verso un indirizzo di prova mentre
 * si collauda il modulo. Il file è un .eml leggibile da qualunque client di
 * posta, così si controlla davvero come verrebbe ricevuto.
 */
final class LogMailer implements MailerInterface
{
    public function __construct(private readonly string $directory)
    {
    }

    public function send(MailMessage $message): bool
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            return false;
        }

        $file = sprintf(
            '%s/%s-%s.eml',
            rtrim($this->directory, '/'),
            (new \DateTimeImmutable('now'))->format('Ymd-His'),
            substr(bin2hex(random_bytes(3)), 0, 6)
        );

        // Le intestazioni si costruiscono senza buchi: una riga vuota in mezzo
        // chiude il blocco, e tutto quello che segue diventa corpo del messaggio.
        $headers = array_filter([
            'Date: ' . (new \DateTimeImmutable('now'))->format(\DATE_RFC2822),
            'From: ' . $this->formatFrom($message),
            'To: ' . $message->to,
            $message->replyTo !== '' ? 'Reply-To: ' . $message->replyTo : null,
            'Subject: ' . $this->encodeHeader($message->subject),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Arco-Del-Vento: prototipo, messaggio non spedito',
        ]);

        $eml = implode("\r\n", $headers) . "\r\n\r\n" . $message->body . "\r\n";

        return file_put_contents($file, $eml) !== false;
    }

    public function isPretend(): bool
    {
        return true;
    }

    private function formatFrom(MailMessage $message): string
    {
        return $message->fromName === ''
            ? $message->fromAddress
            : sprintf('%s <%s>', $this->encodeHeader($message->fromName), $message->fromAddress);
    }

    private function encodeHeader(string $value): string
    {
        // Le intestazioni viaggiano in ASCII: «Camera 01 — richiesta» va codificata.
        return preg_match('/[\x80-\xFF]/', $value) === 1
            ? '=?UTF-8?B?' . base64_encode($value) . '?='
            : $value;
    }
}
