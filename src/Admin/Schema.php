<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

/**
 * Che cosa si può modificare dall'area riservata, campo per campo.
 *
 * Il modulo, la convalida e il salvataggio si costruiscono da qui: aggiungere
 * un campo al pannello vuol dire aggiungere una riga a questa lista, non
 * scrivere un modulo nuovo.
 *
 * Un campo lasciato vuoto si salva come «non confermato»: il sito lo mostra
 * come [da confermare], esattamente come un null in content/settings.php.
 *
 * Tipi: text, email, tel, url, time (HH:MM), int, money, score (0–10),
 *       bool, select, prose (un testo per lingua).
 */
final class Schema
{
    /**
     * I dati della struttura, a gruppi.
     *
     * @return array<string, list<array<string,mixed>>>
     */
    public static function settings(): array
    {
        return [
            'Obblighi di legge' => [
                ['path' => 'legal.cin', 'label' => 'CIN — Codice Identificativo Nazionale', 'type' => 'text', 'max' => 40,
                 'help' => 'Obbligatorio: va esposto nel sito e in ogni annuncio. Compare in fondo a ogni pagina.'],
                ['path' => 'legal.cir', 'label' => 'CIR — codice regionale', 'type' => 'text', 'max' => 40],
                ['path' => 'legal.vat', 'label' => 'Partita IVA', 'type' => 'text', 'max' => 20],
                ['path' => 'legal.rea', 'label' => 'REA', 'type' => 'text', 'max' => 30],
                ['path' => 'address.postal_code', 'label' => 'CAP', 'type' => 'text', 'max' => 5,
                 'pattern' => '/^\d{5}$/', 'pattern_help' => 'Cinque cifre.'],
            ],
            'Contatti' => [
                ['path' => 'contacts.phone', 'label' => 'Telefono', 'type' => 'tel', 'max' => 30],
                ['path' => 'contacts.whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'max' => 20,
                 'help' => 'Solo cifre, con il prefisso internazionale e senza «+»: 393381777983.',
                 'pattern' => '/^\d{8,15}$/', 'pattern_help' => 'Da 8 a 15 cifre, senza spazi né «+».'],
                ['path' => 'contacts.email', 'label' => 'E-mail', 'type' => 'email', 'max' => 180],
                ['path' => 'contacts.hours.from', 'label' => 'Reperibile dalle', 'type' => 'time'],
                ['path' => 'contacts.hours.to', 'label' => 'Reperibile fino alle', 'type' => 'time'],
            ],
            'Arrivo e partenza' => [
                ['path' => 'stay.check_in_from', 'label' => 'Check-in dalle', 'type' => 'time'],
                ['path' => 'stay.check_in_to', 'label' => 'Check-in fino alle', 'type' => 'time'],
                ['path' => 'stay.check_out_by', 'label' => 'Check-out entro le', 'type' => 'time'],
                ['path' => 'stay.parking', 'label' => 'Parcheggio', 'type' => 'prose', 'rows' => 3],
                ['path' => 'stay.stairs', 'label' => 'Scale', 'type' => 'prose', 'rows' => 2],
                ['path' => 'stay.accessibility', 'label' => 'Accessibilità', 'type' => 'prose', 'rows' => 2],
            ],
            'Soggiorno minimo e tassa' => [
                ['path' => 'stay.min_nights.default', 'label' => 'Soggiorno minimo, notti', 'type' => 'int', 'min' => 1, 'max' => 30],
                ['path' => 'stay.min_nights.saturday', 'label' => 'Se comprende un sabato, notti', 'type' => 'int', 'min' => 1, 'max' => 30,
                 'help' => 'Il sabato notte non si prenota da solo: con 2, chi arriva di sabato resta almeno due notti.'],
                ['path' => 'stay.min_nights.peak', 'label' => 'Nei periodi di punta, notti', 'type' => 'int', 'min' => 1, 'max' => 30,
                 'help' => 'Per ora il sito lo mostra soltanto; non lo impone.'],
                ['path' => 'stay.min_nights.peak_dates_note', 'label' => 'Periodi di punta', 'type' => 'prose', 'rows' => 1],
                ['path' => 'stay.cancellation', 'label' => 'Condizioni di cancellazione', 'type' => 'prose', 'rows' => 3,
                 'help' => 'Fino a quando si può annullare e quanto si rimborsa. Compare prima del pagamento e nella conferma: con il pagamento online è obbligatorio dirlo.'],
                ['path' => 'stay.city_tax.amount', 'label' => 'Tassa di soggiorno, € a persona per notte', 'type' => 'money', 'max' => 20],
                ['path' => 'stay.city_tax.max_nights', 'label' => 'Tassa: per le prime notti', 'type' => 'int', 'min' => 1, 'max' => 30],
                ['path' => 'stay.city_tax.exempt_under', 'label' => 'Tassa: esenti sotto gli anni', 'type' => 'int', 'min' => 0, 'max' => 18],
                ['path' => 'stay.show_prices_publicly', 'label' => 'Mostrare i prezzi fuori dalla prenotazione', 'type' => 'bool',
                 'help' => 'Spento: i prezzi compaiono solo dopo aver scelto le date. Acceso: anche nelle pagine delle camere.'],
            ],
            'La casa' => [
                ['path' => 'address.floor', 'label' => 'Piano', 'type' => 'text', 'max' => 40],
                ['path' => 'stay.lift', 'label' => 'Ascensore', 'type' => 'bool'],
                ['path' => 'stay.breakfast', 'label' => 'Colazione', 'type' => 'bool'],
                ['path' => 'stay.wifi', 'label' => 'Wi-Fi', 'type' => 'bool'],
                ['path' => 'stay.wifi_speed', 'label' => 'Velocità del Wi-Fi', 'type' => 'text', 'max' => 20],
                ['path' => 'stay.heating', 'label' => 'Riscaldamento', 'type' => 'bool'],
                ['path' => 'stay.air_conditioning', 'label' => 'Aria condizionata', 'type' => 'bool'],
                ['path' => 'stay.fans', 'label' => 'Ventilatori', 'type' => 'bool'],
                ['path' => 'stay.kettle', 'label' => 'Bollitore', 'type' => 'bool'],
                ['path' => 'stay.minibar', 'label' => 'Minibar', 'type' => 'prose', 'rows' => 1],
                ['path' => 'stay.pets', 'label' => 'Animali', 'type' => 'prose', 'rows' => 1],
                ['path' => 'stay.smoking', 'label' => 'Si può fumare', 'type' => 'bool'],
                ['path' => 'stay.languages', 'label' => 'Lingue parlate', 'type' => 'text', 'max' => 120,
                 'help' => 'Per esempio: italiano, inglese.'],
            ],
            'Recensioni' => [
                ['path' => 'reviews.booking.score', 'label' => 'Booking: punteggio', 'type' => 'score',
                 'help' => 'Da 0 a 10, come lo mostra Booking. Lascia vuoto finché non è verificato: la sezione recensioni compare solo con dati veri.'],
                ['path' => 'reviews.booking.count', 'label' => 'Booking: numero di recensioni', 'type' => 'int', 'min' => 0, 'max' => 100000],
                ['path' => 'reviews.booking.url', 'label' => 'Booking: indirizzo della pagina', 'type' => 'url', 'max' => 400],
                ['path' => 'reviews.google.score', 'label' => 'Google: punteggio', 'type' => 'score', 'scale' => 5,
                 'help' => 'Da 0 a 5.'],
                ['path' => 'reviews.google.count', 'label' => 'Google: numero di recensioni', 'type' => 'int', 'min' => 0, 'max' => 100000],
                ['path' => 'reviews.google.url', 'label' => 'Google: indirizzo della scheda', 'type' => 'url', 'max' => 400],
            ],
            'Link utili e mappa' => [
                ['path' => 'links.parking', 'label' => 'Sito del parcheggio di Piazza Matteotti', 'type' => 'url', 'max' => 400,
                 'help' => 'Il sito rimanda qui per tariffe e orari, invece di scriverli: cambiano con la stagione.'],
                ['path' => 'links.bus', 'label' => 'Orari degli autobus di Assisi', 'type' => 'url', 'max' => 400],
                ['path' => 'links.airlink', 'label' => 'Bus dall’aeroporto (Umbria Airlink)', 'type' => 'url', 'max' => 400],
                ['path' => 'social.instagram', 'label' => 'Instagram', 'type' => 'url', 'max' => 300],
                ['path' => 'social.facebook', 'label' => 'Facebook', 'type' => 'url', 'max' => 300],
                ['path' => 'geo.latitude', 'label' => 'Latitudine della casa', 'type' => 'coord', 'min' => -90, 'max' => 90,
                 'help' => 'Da Google Maps: tasto destro sulla porta, il primo numero.'],
                ['path' => 'geo.longitude', 'label' => 'Longitudine della casa', 'type' => 'coord', 'min' => -180, 'max' => 180,
                 'help' => 'Il secondo numero.'],
            ],
        ];
    }

    /** Quante persone al massimo si possono scrivere in una camera. */
    public const OSPITI_MASSIMI = 6;

    /** La tipologia scritta, in ogni lingua: un'etichetta, non una descrizione. */
    public const TIPOLOGIA_MAX = 80;

    /**
     * I campi di una camera.
     *
     * I letti e la tipologia che usa il motore di prenotazione (`type` in
     * content/rooms.php) non ci sono: si cambiano nel file, con cura. Il super
     * admin cambia il testo della tipologia, quello che si legge sul sito
     * (`type_label`), e quanti ospiti la camera può avere.
     *
     * @param int  $occupazioneMassima quanti campi di tariffa: uno per ospite
     * @param bool $super              anche tipologia e numero di ospiti: solo il super admin
     * @return list<array<string,mixed>>
     */
    public static function room(int $occupazioneMassima, bool $super = false): array
    {
        $campi = [
            ['path' => 'name', 'label' => 'Nome', 'type' => 'prose', 'rows' => 1, 'required' => true],
        ];
        if ($super) {
            $campi[] = ['path' => 'type_label', 'label' => 'Tipologia', 'type' => 'prose', 'rows' => 1, 'required' => true,
                        'help' => 'Come si legge sul sito: nella pagina della camera, negli elenchi e nella prenotazione, al massimo '
                                . self::TIPOLOGIA_MAX . ' caratteri. Vale solo per questa camera; per cambiarla a tutte le camere dello stesso tipo, '
                                . 'Testi del sito → Tipologie, letti e viste. Gli ospiti e le tariffe non cambiano.'];
        }
        array_push($campi,
            ['path' => 'size_sqm', 'label' => 'Metratura, m²', 'type' => 'int', 'min' => 4, 'max' => 200],
            ['path' => 'floor', 'label' => 'Piano', 'type' => 'text', 'max' => 40],
            ['path' => 'view', 'label' => 'Vista', 'type' => 'select',
             'options' => ['' => 'Sui vicoli di Assisi', 'piazza' => 'Su Piazza San Rufino e il Duomo']],
            // Il link iCal di Booking.com: il sito lo legge e tiene occupate le
            // notti prenotate lì. Facoltativo: vuoto non è un dato che manca.
            ['path' => 'ical_import', 'label' => 'Calendario di Booking.com (link iCal)', 'type' => 'text', 'max' => 500, 'facoltativo' => true,
             'help' => 'Nell\'extranet di Booking.com: Tariffe e disponibilità → Sincronizza calendari → Esporta calendario. Copia il link '
                     . 'e incollalo qui: le notti prenotate su Booking risultano occupate anche sul sito, entro ' . (int) (\ArcoDelVento\Booking\CalendariEsterni::OGNI / 60)
                     . ' minuti. Va bene anche il link iCal di Airbnb. Vuoto: il sito non sa niente di Booking.'],
        );
        if ($super) {
            $campi[] = ['path' => 'occupancy.max', 'label' => 'Ospiti al massimo', 'type' => 'int', 'min' => 1,
                        'max' => self::OSPITI_MASSIMI, 'required' => true,
                        'help' => 'Quante persone può ospitare. Salvato, qui sotto compare un campo di tariffa per ogni ospite: '
                                . 'la camera si prenota fino all\'ultimo numero di ospiti che ha una tariffa. I letti e la tipologia scritta qui sopra non cambiano da soli.'];
        }
        for ($ospiti = 1; $ospiti <= $occupazioneMassima; $ospiti++) {
            $campi[] = [
                'path' => 'rates.' . $ospiti,
                'label' => 'Tariffa a notte, ' . $ospiti . ($ospiti === 1 ? ' ospite' : ' ospiti') . ' (€)',
                'type' => 'money', 'max' => 2000,
                'help' => 'Vuota: la camera non si vende a ' . ($ospiti === 1 ? 'una persona sola' : $ospiti . ' persone') . '.',
            ];
        }
        $campi[] = ['path' => 'alt', 'label' => 'Che cosa si vede nella fotografia', 'type' => 'prose', 'rows' => 2,
                    'help' => 'Il testo alternativo, letto da chi non vede l\'immagine. Descrive, non vende.'];

        return $campi;
    }
}
