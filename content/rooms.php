<?php
/**
 * Le cinque camere.
 *
 * TIPOLOGIE, OCCUPAZIONE, LETTI E TARIFFE SONO CONFERMATI dal titolare.
 * Le tariffe non sono stagionali: dipendono da quante persone dormono nella
 * camera, ed è questo che il motore di prenotazione calcola.
 *
 * LE FOTOGRAFIE SONO ACCOPPIATE ALLE CAMERE, e l'accoppiamento è confermato
 * dal titolare. Il nome del file è il numero della camera: camera-03.webp è
 * la Camera 03, e non c'è altro da sapere.
 *
 * LA CAMERA 01 — la tripla, quella con la vista su San Rufino dichiarata dal
 * titolare, e la più cara del listino — è l'unica senza fotografia: nessuna
 * di quelle consegnate mostra tre letti. È la prima da fotografare.
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
    // ---- La tripla. Vista su San Rufino dichiarata dal titolare. È l'unica
    // camera ancora senza fotografia, ed è quella che ne ha più bisogno.
    $camera(1, [
        'type'            => 'triple',
        'occupancy'       => ['standard' => 3, 'max' => 3],
        'beds'            => ['double' => 1, 'single' => 1],
        'layouts'         => ['triple', 'double'],
        'rates'           => [1 => 100, 2 => 110, 3 => 120],
        'view_san_rufino' => true,   // dichiarata dal titolare
        'amenities'       => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels', 'wardrobe'],
    ]),

    // ---- La doppia con i letti separabili: si uniscono in un matrimoniale o
    // si dividono. Dalla sua finestra si vedono il campanile e la facciata.
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

    // ---- Le due matrimoniali, stessa tariffa. Il letto è composto da due
    // singoli uniti, che in una casa così è la regola e non l'eccezione.
    $camera(3, [
        'rates'           => [1 => 70, 2 => 80],
        'photographed'    => true,
        'images'          => $fotografie('camera-03'),
        'view_san_rufino' => true,
        'alt'             => $duiLetti(),
    ]),
    $camera(4, [
        'rates'           => [1 => 70, 2 => 80],
        'photographed'    => true,
        'images'          => $fotografie('camera-04'),
        'view_san_rufino' => true,
        'amenities'       => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels', 'wardrobe'],
        'alt'             => $duiLetti(', e in camera c’è un armadio in legno chiaro'),
    ]),

    // ---- Singola con letto matrimoniale: una persona sola in una camera con
    // un letto grande e una scrivania.
    $camera(5, [
        'type'         => 'single-double',
        'occupancy'    => ['standard' => 1, 'max' => 1],
        'beds'         => ['double' => 1],
        'layouts'      => ['single'],
        'rates'        => [1 => 70],
        'photographed' => true,
        'images'       => $fotografie('camera-05'),
        'amenities'    => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels', 'desk'],
        'alt' => [
            'it' => 'Un letto matrimoniale con coperta color crema, pavimento in parquet, una '
                  . 'scrivania e due sedie impagliate sul fondo.',
            'en' => 'A double bed with a cream cover, a parquet floor, a desk and two '
                  . 'rush-seated chairs at the far end.',
        ],
    ]),
];
