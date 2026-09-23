<?php
/**
 * Le cinque camere.
 *
 * TIPOLOGIE, OCCUPAZIONE, LETTI E TARIFFE SONO CONFERMATI dal titolare.
 * Le tariffe non sono stagionali: dipendono da quante persone dormono nella
 * camera, ed è questo che il motore di prenotazione calcola.
 *
 * LE FOTOGRAFIE SONO ASSEGNATE PER COERENZA CON I LETTI, non per numero.
 * Nessuna delle quattro fotografie mostra tre letti, quindi la Stanza 1 — la
 * tripla — non può essere nessuna di quelle: resta senza fotografia. Le altre
 * tre mostrano due letti singoli affiancati e vanno alle camere che quei letti
 * li hanno; la quarta mostra un letto grande e va alla camera con il solo
 * matrimoniale.
 *
 * DA CONFERMARE IN UNA RIGA: se l'accoppiamento è giusto. È un'inferenza dai
 * letti che si vedono nell'inquadratura, non un dato. Per cambiarlo si
 * rinominano i file in docs/foto-originali/ e si rilancia
 * `php tools/build-photos.php`.
 *
 * Restano da confermare: i nomi definitivi, le metrature, il piano, la
 * stagionalità delle tariffe e la tassa di soggiorno.
 */

declare(strict_types=1);

/** Scorciatoia per non ripetere la stessa struttura cinque volte. */
$camera = static function (int $n, array $overrides = []): array {
    $due = str_pad((string) $n, 2, '0', STR_PAD_LEFT);

    /* array_replace e NON array_replace_recursive: quello fonde gli array
       chiave per chiave, e qui ogni campo va sostituito per intero. Con la
       versione ricorsiva la Stanza 5 — una singola, tariffa per una persona
       sola — si ritrovava anche la tariffa per due ereditata dal valore
       predefinito, e il motore l'avrebbe venduta a due persone. Lo stesso
       valeva per i letti: la doppia con due singoli teneva anche il
       matrimoniale del default. */
    return array_replace([
        'id'  => $n,
        'ref' => 'camera-' . $due,

        // Gli indirizzi sono per lingua: non si traduce una pagina duplicandola.
        'slug' => [
            'it' => 'camera-' . $due,
            'en' => 'room-' . $due,
            'es' => 'habitacion-' . $due,
        ],

        // I nomi definitivi non sono stati forniti. L'ordinale è quello che
        // usa il titolare — «Stanza 1» — quindi almeno numerazione e realtà
        // coincidono.
        'name' => ['it' => 'Camera ' . $due, 'en' => 'Room ' . $due, 'es' => 'Habitación ' . $due],
        'name_confirmed' => false,

        'photographed' => false,
        'confirmed'    => true,     // tipologia, letti, occupazione e tariffe
        'position'     => $n,

        'type'      => 'double',
        'occupancy' => ['standard' => 2, 'max' => 2],
        'beds'      => ['double' => 1],
        'layouts'   => ['double'],
        'size_sqm'  => null,        // non si misura da una fotografia
        'floor'     => null,
        'bathroom'  => ['private' => true, 'shower' => true, 'bathtub' => false],
        'amenities' => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels'],

        'images' => [
            'card'    => ['src' => 'img/demo/camera-' . $due . '-4x3.svg',  'ratio' => '4/3'],
            'list'    => ['src' => 'img/demo/camera-' . $due . '-3x2.svg',  'ratio' => '3/2'],
            'hero'    => ['src' => 'img/demo/camera-' . $due . '-16x9.svg', 'ratio' => '16/9'],
            'gallery' => [
                ['src' => 'img/demo/camera-' . $due . '-1x1-a.svg', 'ratio' => '1/1'],
                ['src' => 'img/demo/camera-' . $due . '-1x1-b.svg', 'ratio' => '1/1'],
            ],
        ],

        'alt' => ['it' => null, 'en' => null],

        /**
         * La tariffa a notte per numero di ospiti. È il modo in cui il
         * titolare le ha date, ed è anche il modo in cui funzionano davvero:
         * una matrimoniale occupata da una persona sola costa meno.
         */
        'rates'    => [1 => 70, 2 => 80],
        'currency' => 'EUR',
        'price'    => ['confirmed' => true, 'currency' => 'EUR'],

        'view_san_rufino' => null,
    ], $overrides);
};

/** Le immagini vere di una camera fotografata, nei tagli del sistema. */
$fotografie = static function (string $nome): array {
    return [
        'card'    => ['src' => "img/camere/{$nome}-4x3",  'ratio' => '4/3'],
        'list'    => ['src' => "img/camere/{$nome}-3x2",  'ratio' => '3/2'],
        'hero'    => ['src' => "img/camere/{$nome}-16x9", 'ratio' => '16/9'],
        'gallery' => [['src' => "img/camere/{$nome}-1x1", 'ratio' => '1/1']],
    ];
};

$duiLetti = static function (string $coda = ''): array {
    return [
        'it' => 'Due letti singoli affiancati con coperte gialle; dalla finestra si vede '
              . 'il campanile di San Rufino' . $coda . '.',
        'en' => 'Two single beds side by side with yellow covers; through the window, the '
              . 'stone bell tower of San Rufino' . $coda . '.',
    ];
};

return [
    // ---- Stanza 1: la tripla, quella con la vista dichiarata dal titolare.
    // Nessuna fotografia la ritrae: nessuna delle quattro mostra tre letti.
    $camera(1, [
        'type'            => 'triple',
        'occupancy'       => ['standard' => 3, 'max' => 3],
        'beds'            => ['double' => 1, 'single' => 1],
        'layouts'         => ['triple', 'double'],
        'rates'           => [1 => 100, 2 => 110, 3 => 120],
        'view_san_rufino' => true,   // dichiarata dal titolare
        'amenities'       => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels', 'wardrobe'],
    ]),

    // ---- Stanza 2: la doppia con i letti separabili. È l'unica camera per
    // cui il titolare dichiara esplicitamente i due singoli, quindi prende la
    // fotografia in cui la finestra mostra anche la facciata della cattedrale.
    $camera(2, [
        'type'            => 'double-twin',
        'occupancy'       => ['standard' => 2, 'max' => 2],
        'beds'            => ['single' => 2],
        'layouts'         => ['double', 'twin'],
        'rates'           => [1 => 80, 2 => 90],
        'photographed'    => true,
        'images'          => $fotografie('camera-02'),
        'view_san_rufino' => true,
        'alt' => [
            'it' => 'Due letti singoli affiancati con coperte gialle; dalla finestra si vedono '
                  . 'il campanile e la facciata della cattedrale di San Rufino.',
            'en' => 'Two single beds side by side with yellow covers; through the window, the '
                  . 'bell tower and the façade of the cathedral of San Rufino.',
        ],
    ]),

    // ---- Stanze 3 e 4: matrimoniale, stessa tariffa. Prendono le altre due
    // fotografie con i letti affiancati: un matrimoniale composto da due
    // singoli uniti è la regola, non l'eccezione, in una casa così.
    $camera(3, [
        'rates'           => [1 => 70, 2 => 80],
        'photographed'    => true,
        'images'          => $fotografie('camera-01'),
        'view_san_rufino' => true,
        'alt'             => $duiLetti(),
    ]),
    $camera(4, [
        'rates'           => [1 => 70, 2 => 80],
        'photographed'    => true,
        'images'          => $fotografie('camera-03'),
        'view_san_rufino' => true,
        'amenities'       => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels', 'wardrobe'],
        'alt'             => $duiLetti(', e in camera c’è un armadio in legno chiaro'),
    ]),

    // ---- Stanza 5: singola con letto matrimoniale. Una persona sola in una
    // camera con un letto grande: la fotografia con il letto unico e la
    // scrivania è l'unica coerente.
    $camera(5, [
        'type'         => 'single-double',
        'occupancy'    => ['standard' => 1, 'max' => 1],
        'beds'         => ['double' => 1],
        'layouts'      => ['single'],
        'rates'        => [1 => 70],
        'photographed' => true,
        'images'       => $fotografie('camera-04'),
        'amenities'    => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels', 'desk'],
        'alt' => [
            'it' => 'Un letto matrimoniale con coperta color crema, pavimento in parquet, una '
                  . 'scrivania e due sedie impagliate sul fondo.',
            'en' => 'A double bed with a cream cover, a parquet floor, a desk and two '
                  . 'rush-seated chairs at the far end.',
        ],
    ]),
];
