<?php

declare(strict_types=1);

namespace ArcoDelVento\Booking;

use ArcoDelVento\App;
use ArcoDelVento\Mail\MailMessage;

/**
 * Le due e-mail di una prenotazione pagata: alla struttura, in italiano, e
 * all'ospite, nella lingua in cui ha prenotato.
 *
 * Tutto quello che c'è scritto viene dalla prenotazione e dalle impostazioni
 * della casa: un orario o una regola che non si conoscono non si scrivono.
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
        $d = (array) $voce['dati'];
        $p = (array) ($d['pagamento'] ?? []);
        $daControllare = ($p['stato'] ?? '') === 'da controllare';

        $righe = [
            $daControllare
                ? 'ATTENZIONE: SumUp dice che la prenotazione è pagata, ma l\'importo non coincide. Controlla nel pannello di SumUp.'
                : 'Prenotazione PAGATA dal sito.',
            '',
            'Riferimento: ' . $voce['id'],
            'Camera:      ' . ($d['camera'] ?? '') . ' (' . ($d['ref'] ?? '') . ')',
            'Arrivo:      ' . $this->data((string) ($d['arrivo'] ?? '')),
            'Partenza:    ' . $this->data((string) ($d['partenza'] ?? '')),
            'Notti:       ' . ($d['notti'] ?? ''),
            'Ospiti:      ' . ($d['ospiti'] ?? ''),
            'Da pagare:   ' . $this->euro((float) ($p['importo'] ?? 0)),
            'Pagato:      ' . $this->euro((float) ($p['pagato'] ?? 0)) . ' su SumUp'
                            . (!empty($p['codice']) ? ', codice transazione ' . $p['codice'] : ''),
            '',
            'Ospite:      ' . ($d['nome'] ?? ''),
            'E-mail:      ' . ($d['email'] ?? ''),
            'Lingua:      ' . ($d['lingua'] ?? ''),
            '',
        ];
        if (in_array((string) $this->app->config('booking.provider'), ['demo', 'sito', ''], true)) {
            $righe[] = 'Il calendario del sito non conosce le prenotazioni fatte su Booking o al telefono:';
            $righe[] = 'controlla che le date siano libere. Se non lo sono, rimborsa dal pannello di SumUp';
            $righe[] = 'e scrivi all\'ospite.';
            $righe[] = '';
        }
        $righe[] = 'La prenotazione è anche nell\'area riservata: ' . $this->app->indirizzoCompleto(adminUrl('richieste/' . rawurlencode((string) $voce['id'])));

        $this->app->mailer()->send(new MailMessage(
            to:          $this->app->destinatari(),
            subject:     sprintf('[Arco del Vento] %s %s — %s, %s',
                             $daControllare ? 'DA CONTROLLARE' : 'PAGATA',
                             $voce['id'], (string) ($d['camera'] ?? ''), $this->data((string) ($d['arrivo'] ?? ''))),
            body:        implode("\n", $righe),
            fromAddress: (string) $this->app->config('mail.from.address'),
            fromName:    (string) $this->app->config('mail.from.name'),
            replyTo:     (string) ($d['email'] ?? ''),
        ));
    }

    /** @param array<string,mixed> $voce */
    private function allOspite(array $voce): void
    {
        $d = (array) $voce['dati'];
        $p = (array) ($d['pagamento'] ?? []);
        $email = (string) ($d['email'] ?? '');
        if ($email === '' || ($p['stato'] ?? '') !== 'pagato') {
            // Se l'importo non torna, prima di confermare all'ospite ci guarda qualcuno.
            return;
        }
        $lingua = in_array($d['lingua'] ?? '', (array) $this->app->config('i18n.available'), true) ? (string) $d['lingua'] : 'it';
        $t = fn (string $chiave, array $sost = []): string => $this->app->translator()->get('book.' . $chiave, $sost, $lingua);
        $impostazioni = $this->app->settings();
        $stay = (array) ($impostazioni['stay'] ?? []);

        $arrivo = $this->data((string) $d['arrivo']);
        if (!empty($stay['check_in_from']) && !empty($stay['check_in_to'])) {
            $arrivo .= ', ' . $t('mail.check_in', ['from' => $stay['check_in_from'], 'to' => $stay['check_in_to']]);
        }
        $partenza = $this->data((string) $d['partenza']);
        if (!empty($stay['check_out_by'])) {
            $partenza .= ', ' . $t('mail.check_out', ['time' => $stay['check_out_by']]);
        }
        $etichetta = static fn (string $s): string => str_pad($s . ':', 14);

        $righe = [
            $t('mail.hello', ['name' => (string) ($d['nome'] ?? '')]),
            '',
            $t('mail.intro'),
            '',
            $etichetta($t('mail.reference')) . $voce['id'],
            $etichetta($t('mail.room')) . ($d['camera'] ?? ''),
            $etichetta($t('mail.arrival')) . $arrivo,
            $etichetta($t('mail.departure')) . $partenza,
            $etichetta($t('mail.nights')) . ($d['notti'] ?? ''),
            $etichetta($t('mail.guests')) . ($d['ospiti'] ?? ''),
            $etichetta($t('mail.paid')) . $this->euro((float) ($p['pagato'] ?? $p['importo'] ?? 0)) . ' (SumUp'
                . (!empty($p['codice']) ? ', ' . $p['codice'] : '') . ')',
            '',
        ];
        $tassa = (array) ($stay['city_tax'] ?? []);
        if (!empty($tassa['amount'])) {
            $righe[] = $t('pay.city_tax', [
                'amount' => number_format((float) $tassa['amount'], 2, ',', '.'),
                'nights' => (string) ($tassa['max_nights'] ?? ''),
                'age'    => (string) ($tassa['exempt_under'] ?? ''),
            ]);
        }
        $cancellazione = $stay['cancellation'][$lingua] ?? null;
        if (is_string($cancellazione) && trim($cancellazione) !== '') {
            $righe[] = $t('pay.cancellation') . ': ' . trim($cancellazione);
        }
        $indirizzo = (array) ($impostazioni['address'] ?? []);
        $righe[] = '';
        $righe[] = $etichetta($t('mail.address')) . trim(($indirizzo['street'] ?? '') . ', ' . ($indirizzo['city'] ?? ''), ', ');
        if (!empty($impostazioni['contacts']['phone'])) {
            $righe[] = '';
            $righe[] = $t('mail.contact', ['phone' => (string) $impostazioni['contacts']['phone']]);
        }
        $righe[] = '';
        $righe[] = $t('mail.sign');

        $destinatari = \ArcoDelVento\Mail\MailMessage::indirizzi($this->app->destinatari());
        $this->app->mailer()->send(new MailMessage(
            to:          $email,
            subject:     $t('mail.subject', ['ref' => (string) $voce['id']]),
            body:        implode("\n", $righe),
            fromAddress: (string) $this->app->config('mail.from.address'),
            fromName:    (string) $this->app->config('mail.from.name'),
            // Rispondere alla conferma scrive alla struttura, non al mittente automatico.
            replyTo:     $destinatari[0] ?? (string) ($impostazioni['contacts']['email'] ?? ''),
        ));
    }

    private function data(string $iso): string
    {
        $t = strtotime($iso);

        return $t ? date('d/m/Y', $t) : $iso;
    }

    private function euro(float $importo): string
    {
        return '€ ' . number_format($importo, 2, ',', '.');
    }
}
