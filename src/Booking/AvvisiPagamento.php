<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

use ArcoDelVento\App;

/**
 * Le due e-mail di una prenotazione pagata: alla struttura, in italiano, e
 * all'ospite, nella lingua in cui ha prenotato, con il soggiorno da
 * aggiungere al calendario. Qui si decide quando partono; che cosa dicono, e
 * con quale grafica, lo scrive Mail\Messaggi.
 */
final class AvvisiPagamento
{
    public function __construct(private readonly App $app)
    {
    }

    /** @param array<string,mixed> $voce la prenotazione, com'è nell'archivio delle richieste */
    public function __invoke(array $voce): void
    {
        $this->allaStruttura($voce);
        $this->allOspite($voce);
    }

    /** @param array<string,mixed> $voce */
    private function allaStruttura(array $voce): void
    {
        $this->app->mailer()->send($this->app->messaggi()->pagataAllaStruttura($voce));
        // E sul telefono di chi gestisce il sito, dove le notifiche sono attive. Non lancia mai.
        $this->app->notifiche()->pagata($voce);
    }

    /** @param array<string,mixed> $voce */
    private function allOspite(array $voce): void
    {
        $d = (array) $voce['dati'];
        $p = (array) ($d['pagamento'] ?? []);
        if ((string) ($d['email'] ?? '') === '' || ($p['stato'] ?? '') !== 'pagato') {
            // Se l'importo non torna, prima di confermare all'ospite ci guarda qualcuno.
            return;
        }
        // La conferma, con il soggiorno da aggiungere al calendario: Mail\Messaggi.
        $this->app->mailer()->send($this->app->messaggi()->confermataAllOspite($voce));
    }
}
