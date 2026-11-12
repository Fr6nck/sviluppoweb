<?php

declare(strict_types=1);

namespace ArcoDelVento\Mail;

/**
 * Un messaggio, indipendente da come verrà spedito.
 *
 * Il destinatario può essere più d'uno, separati da virgola: così nel .env
 * MAIL_TO_ADDRESS=info@esempio.it, daniele@esempio.it manda ogni richiesta a
 * entrambi.
 *
 * Il testo semplice c'è sempre: è quello che legge chi non mostra l'HTML, e
 * quello che va nell'anteprima. L'HTML (facoltativo) è la stessa e-mail con
 * la grafica del sito; gli allegati sono file piccoli, come il calendario
 * della prenotazione. Mime li mette insieme.
 */
final class MailMessage
{
    /**
     * @param list<array{nome: string, tipo: string, contenuto: string}> $allegati
     */
    public function __construct(
        public readonly string $to,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $fromAddress,
        public readonly string $fromName = '',
        public readonly string $replyTo = '',
        public readonly string $html = '',
        public readonly array $allegati = [],
    ) {
    }

    /**
     * Gli indirizzi a cui spedire, validi e senza doppioni.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        return self::indirizzi($this->to);
    }

    /**
     * «a@x.it, b@y.it; c@z.it» → tre indirizzi. Quello che non è un indirizzo
     * si scarta: un refuso nel .env non deve bloccare gli altri destinatari.
     *
     * @return list<string>
     */
    public static function indirizzi(string $elenco): array
    {
        $out = [];
        foreach (preg_split('/[,;]+/', str_replace(["\r", "\n", "\0"], '', $elenco)) ?: [] as $pezzo) {
            $pezzo = trim($pezzo);
            if ($pezzo !== '' && filter_var($pezzo, FILTER_VALIDATE_EMAIL) !== false) {
                $out[strtolower($pezzo)] ??= $pezzo;
            }
        }

        return array_values($out);
    }
}
