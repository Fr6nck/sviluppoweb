<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

/**
 * Un messaggio, indipendente da come verrà spedito.
 */
final class MailMessage
{
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $fromAddress,
        public readonly string $fromName = '',
        public readonly string $replyTo = '',
    ) {
    }
}
