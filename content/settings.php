<?php
/**
 * I dati della struttura.
 *
 * REGOLA: qui dentro sta solo ciò che è confermato dal cliente. Tutto quello
 * che non lo è vale `null`, e il sito lo mostra come «da confermare» invece
 * di inventarlo. Un campo compilato qui è un campo che il sito dichiara vero.
 *
 * Quasi tutto è stato confermato nell'intervista al titolare. Quello che
 * resta `null` è elencato nel README sotto «Da confermare».
 *
 * SECONDA REGOLA: questo file è versionato e finisce su GitHub. I dati
 * personali e le credenziali stanno in .env, che non lo è.
 *
 * Certe voci sono prosa e non numeri — com'è fatta la scala, dove si
 * parcheggia. Quelle si scrivono per lingua, ['it' => '…', 'en' => '…'], e le
 * viste le passano da testoLocale(): una frase italiana in una pagina inglese
 * sembra un guasto del sito.
 */

declare(strict_types=1);

use ArcoDelVento\Support\Env;

return [
    // ------------------------------------------------------- L'impresa
    'name'        => 'Arco del Vento',
    'legal_name'  => 'Arco del Vento di Pecetta Daniele',
    'type'        => 'affittacamere',
    'owner'       => 'Daniele Pecetta',
    'established' => 2002,
    'rooms_count' => 5,

    'address' => [
        'street'      => 'Via Santa Maria delle Rose 1/A',
        'city'        => 'Assisi',
        'province'    => 'PG',
        'region'      => 'Umbria',
        'country'     => 'IT',
        'postal_code' => null,          // non fornito
        'floor'       => 'secondo piano',
    ],

    'contacts' => [
        'phone'    => '+39 338 177 7983',
        'mobile'   => '+39 338 177 7983',
        'whatsapp' => '393381777983',    // solo cifre, con prefisso internazionale
        'email'    => 'info@arcodelvento.it',
        // Gli orari in cui qualcuno risponde davvero. Dirli evita la telefonata
        // delle 22 a cui nessuno risponde, che l'ospite legge come menefreghismo.
        'hours'    => ['from' => '10:00', 'to' => '20:00'],
    ],

    'stay' => [
        'check_in_from' => '13:00',
        'check_in_to'   => '20:00',
        'check_out_by'  => null,         // non fornito
        'check_in_kind' => 'in-person',  // accoglienza di persona, quasi sempre

        'open_all_year' => true,
        'quiet_months'  => [
            'it' => ['novembre', 'febbraio'],
            'en' => ['November', 'February'],
        ],

        // Nessun servizio di ristorazione: né colazione né altro. È una cosa
        // che si dice prima, non si lascia scoprire all'ospite la mattina.
        'breakfast' => false,
        'meals'     => false,

        // Prosa, non numeri: va scritta una volta per lingua. Una frase
        // italiana dentro una pagina inglese sembra un guasto del sito.
        'parking' => [
            'it' => 'Piazza Matteotti a pagamento (circa 18 € per 24 ore, 200 m) '
                  . 'oppure gratuito in via dell’Eremo, verso la Porta dei Cappuccini',
            'en' => 'Piazza Matteotti, paid (about €18 per 24 hours, 200 m), '
                  . 'or free in via dell’Eremo, towards Porta dei Cappuccini',
        ],

        'lift'   => false,
        'stairs' => [
            'it' => 'due rampe, circa 10 scalini e poi 3',
            'en' => 'two flights: about 10 steps, then 3',
        ],

        'wifi'          => true,
        'wifi_speed'    => '60 Mbps',
        'heating'       => true,
        'air_conditioning' => false,
        'fans'          => true,          // ventilatori a disposizione
        'kettle'        => true,          // bollitore in camera
        'minibar'       => [
            'it' => 'su richiesta, senza costi aggiuntivi',
            'en' => 'on request, at no extra cost',
        ],

        'pets'    => [
            'it' => 'ammessi senza sovrapprezzo, avvisando prima',
            'en' => 'welcome at no extra charge, with notice beforehand',
        ],
        'smoking' => false,

        /**
         * Le tariffe si mostrano solo quando l'ospite apre il calendario o
         * chiede di prenotare, non prima: è una scelta del titolare, perché
         * i prezzi si muovono con la domanda e il periodo e un listino fermo
         * in pagina invecchia male.
         *
         * Il costo da conoscere: chi arriva sul sito senza vedere nemmeno una
         * cifra può andarsene prima di provare le date. Se un giorno si vuole
         * cambiare idea, basta mettere `true` qui: tornano il «da € …» negli
         * elenchi, le tariffe nella pagina della camera e la tabella di
         * confronto. Nient'altro da toccare.
         */
        'show_prices_publicly' => false,

        'city_tax' => [
            'amount'       => 3.00,
            'per'          => 'persona/notte',
            'max_nights'   => 3,
            'exempt_under' => 12,
            'paid_at'      => 'check-in',
        ],

        // Il sabato notte non si vende da solo. Vale tutto l'anno: un soggiorno
        // che comprende un sabato dura almeno due notti — venerdì e sabato,
        // oppure sabato e domenica.
        'min_nights' => [
            'default'           => 1,
            'saturday'          => 2,
            'peak'              => null,   // 3 o 4 nelle iperfestività: da precisare
            'peak_dates_note'   => [
                'it' => 'Ferragosto, 4 ottobre, Capodanno',
                'en' => 'Ferragosto (15 August), 4 October, New Year',
            ],
        ],

        'languages'     => null,          // non fornito
        'accessibility' => [
            'it' => 'scale senza ascensore: non adatto a chi ha gravi difficoltà motorie',
            'en' => 'stairs and no lift: not suitable for guests with serious mobility '
                  . 'difficulties',
        ],
        'remote_work'   => true,
    ],

    // Identificativi obbligatori per una struttura ricettiva italiana.
    'legal' => [
        'cin'    => null,                 // non fornito: obbligatorio, va richiesto
        'cir'    => null,
        'vat'    => '03323290548',
        'rea'    => null,
        // Il codice fiscale del titolare NON si pubblica e non sta qui: per
        // una ditta individuale la partita IVA basta, e il codice fiscale di
        // una persona è un dato personale. Questo file finisce su GitHub, e da
        // una cronologia git non si cancella più niente — quindi se all'uso
        // amministrativo serve, si mette in .env (OWNER_TAX_CODE), che non è
        // versionato. Nessuna vista lo stampa, in nessun caso.
        'tax_code'         => Env::get('OWNER_TAX_CODE') ?: null,
        'tax_code_public'  => false,
    ],

    // Per l'auto si imposta Piazza San Rufino, non l'indirizzo: la via è
    // senza uscita e il navigatore ci manda dentro. A piedi l'indirizzo
    // preciso funziona.
    'navigation' => [
        'by_car'  => 'Piazza San Rufino',
        'on_foot' => 'Via Santa Maria delle Rose 1/A',
    ],

    'parking_spots' => [
        [
            'id'    => 'matteotti',
            'paid'  => true,
            'price' => [
                'it' => 'circa 18 € per 24 ore',
                'en' => 'about €18 per 24 hours',
            ],
            'metres' => 200,
        ],
        [
            'id'    => 'eremo',
            'paid'  => false,
            'price' => null,
            'metres' => null,               // distanza non fornita
        ],
    ],

    // Coordinate della mappa: non si stimano da un indirizzo.
    'geo' => ['latitude' => null, 'longitude' => null],

    // Recensioni: i numeri dell'intervista sono dichiarati «indicativi, in
    // verifica». Finché lo sono restano qui a null e la sezione non compare:
    // un punteggio sbagliato in pagina è peggio di nessun punteggio.
    'reviews' => [
        'booking' => ['count' => null, 'score' => null, 'url' => null],
        'google'  => ['count' => null, 'score' => null, 'url' => null],
    ],

    'social' => ['instagram' => null, 'facebook' => null],
];
