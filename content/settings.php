<?php
/**
 * I dati della struttura.
 *
 * REGOLA: qui dentro sta solo ciò che è confermato dal cliente. Tutto quello
 * che non lo è vale `null`, e il sito lo mostra come «da confermare» invece
 * di inventarlo. Un campo compilato qui è un campo che il sito dichiara vero.
 *
 * I valori con `null` sono elencati nel README sotto «Da confermare».
 */

declare(strict_types=1);

return [
    // ------------------------------------------------------- Confermato
    'name'        => 'Arco del Vento',
    'legal_name'  => 'Arco del Vento di Pecetta Daniele',
    'type'        => 'affittacamere',
    'owner'       => 'Daniele Pecetta',
    'established' => 2002,
    'rooms_count' => 5,

    'address' => [
        'street'   => 'Via Santa Maria delle Rose 1/A',
        'city'     => 'Assisi',
        'province' => 'PG',
        'region'   => 'Umbria',
        'country'  => 'IT',
        // Il CAP non era nel materiale consegnato: non lo si deduce.
        'postal_code' => null,
    ],

    // --------------------------------------------- Ancora da confermare
    // Compilando un valore, il sito smette di segnalarlo come da confermare
    // e comincia a usarlo: il numero diventa un link «tel:», l'indirizzo
    // e-mail un «mailto:», il numero WhatsApp attiva la seconda azione in
    // tutto il sito. Non serve toccare nessuna vista.
    'contacts' => [
        'phone'    => null,   // es. '+39 075 000 0000'
        'mobile'   => null,
        'whatsapp' => null,   // solo cifre con prefisso: '39333...'
        'email'    => null,   // es. 'info@arcodelvento.it'
    ],

    'stay' => [
        'check_in_from'  => null,   // es. '15:00'
        'check_in_to'    => null,   // es. '20:00'
        'check_out_by'   => null,   // es. '10:30'
        'min_nights'     => null,   // demo: il motore usa BOOKING_MIN_NIGHTS
        'city_tax'       => null,   // importo e condizioni di esenzione
        'breakfast'      => null,   // true | false — NON dichiarato nel materiale
        'parking'        => null,   // descrizione del parcheggio
        'lift'           => null,   // true | false
        'stairs'         => null,   // numero di rampe / descrizione
        'wifi'           => null,   // true | false
        'pets'           => null,   // true | false | 'su richiesta'
        'smoking'        => null,
        'languages'      => null,   // lingue parlate alla reception
    ],

    // Identificativi obbligatori per una struttura ricettiva italiana:
    // vanno nella riga finale del piè di pagina quando il cliente li fornisce.
    'legal' => [
        'cin'      => null,   // Codice Identificativo Nazionale
        'cir'      => null,   // codice regionale Umbria, se assegnato
        'vat'      => null,   // partita IVA
        'rea'      => null,
    ],

    // Coordinate della mappa: non si stimano da un indirizzo, si prendono
    // dal cliente o da un rilievo. Finché mancano, la pagina Assisi mostra
    // l'indirizzo e le indicazioni a piedi, non una mappa con un segnaposto
    // messo a occhio.
    'geo' => [
        'latitude'  => null,
        'longitude' => null,
    ],

    'social' => [
        'instagram' => null,
        'facebook'  => null,
    ],
];
