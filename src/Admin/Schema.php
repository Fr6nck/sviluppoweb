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
            'Social e mappa' => [
                ['path' => 'social.instagram', 'label' => 'Instagram', 'type' => 'url', 'max' => 300],
                ['path' => 'social.facebook', 'label' => 'Facebook', 'type' => 'url', 'max' => 300],
                ['path' => 'geo.latitude', 'label' => 'Latitudine della casa', 'type' => 'coord', 'min' => -90, 'max' => 90,
                 'help' => 'Da Google Maps: tasto destro sulla porta, il primo numero.'],
                ['path' => 'geo.longitude', 'label' => 'Longitudine della casa', 'type' => 'coord', 'min' => -180, 'max' => 180,
                 'help' => 'Il secondo numero.'],
            ],
        ];
    }

    /**
     * I campi di una camera.
     *
     * Tipologia, letti e occupazione massima non ci sono: da quelli dipende il
     * motore di prenotazione, e si cambiano nel file, con cura.
     *
     * @return list<array<string,mixed>>
     */
    public static function room(int $occupazioneMassima): array
    {
        $campi = [
            ['path' => 'name', 'label' => 'Nome', 'type' => 'prose', 'rows' => 1, 'required' => true],
            ['path' => 'size_sqm', 'label' => 'Metratura, m²', 'type' => 'int', 'min' => 4, 'max' => 200],
            ['path' => 'floor', 'label' => 'Piano', 'type' => 'text', 'max' => 40],
            ['path' => 'view', 'label' => 'Vista', 'type' => 'select',
             'options' => ['' => 'Nessuna vista particolare', 'piazza' => 'Su Piazza San Rufino e il Duomo']],
        ];
        for ($ospiti = 1; $ospiti <= $occupazioneMassima; $ospiti++) {
            $campi[] = [
                'path' => 'rates.' . $ospiti,
                'label' => 'Tariffa a notte, ' . $ospiti . ($ospiti === 1 ? ' ospite' : ' ospiti') . ' (€)',
                'type' => 'money', 'max' => 2000,
                'help' => $ospiti === 1 ? 'Vuota: la camera non si vende a quel numero di persone.' : '',
            ];
        }
        $campi[] = ['path' => 'alt', 'label' => 'Che cosa si vede nella fotografia', 'type' => 'prose', 'rows' => 2,
                    'help' => 'Il testo alternativo, letto da chi non vede l\'immagine. Descrive, non vende.'];

        return $campi;
    }
}
