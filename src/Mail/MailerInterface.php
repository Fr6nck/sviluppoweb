<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

/**
 * Il confine fra il sito e la posta.
 *
 * Il modulo dei contatti e il motore di prenotazione conoscono solo questo
 * metodo. Nel prototipo dietro c'è LogMailer, che scrive il messaggio su file
 * e non spedisce niente; in produzione basta cambiare MAIL_TRANSPORT nel .env
 * per passare a mail() o a SMTP autenticato.
 */
interface MailerInterface
{
    public function send(MailMessage $message): bool;

    /** Vero quando i messaggi non escono davvero: la pagina lo dice all'utente. */
    public function isPretend(): bool;
}
