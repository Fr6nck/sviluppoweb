<?php
/**
 * Le cinque camere.
 *
 * QUATTRO SONO FOTOGRAFATE. Le fotografie di Camera 01–04 sono state fornite
 * dal titolare e ritraggono la casa vera: si pubblicano. La quinta camera non
 * ha ancora una fotografia e tiene il segnaposto disegnato.
 *
 * DA CONFERMARE CON DANIELE, in una riga: quale fotografia corrisponde a
 * quale camera. La numerazione qui sotto è quella in cui le fotografie sono
 * arrivate, non una numerazione della casa. Cambiarla vuol dire rinominare i
 * file in public/assets/img/camere/ e gli originali in docs/foto-originali/,
 * nient'altro.
 *
 * Letti, esposizione e servizi delle prime quattro sono presi DALLE
 * FOTOGRAFIE: due letti singoli affiancati nelle prime tre, un letto grande
 * nella quarta, e la finestra sul campanile dove si vede nell'inquadratura.
 * Metrature, piano e tariffe restano da confermare: non si misurano da una
 * fotografia.
 *
 * Quando le camere passano su MySQL, questo file smette di essere letto:
 * PdoRoomRepository restituisce gli stessi array leggendo dalle tabelle
 * `rooms`, `room_images`, `room_amenities` (vedi database/schema.sql).
 */

declare(strict_types=1);

/** Scorciatoia per non ripetere la stessa struttura cinque volte. */
$camera = static function (int $n, array $overrides = []): array {
    $due = str_pad((string) $n, 2, '0', STR_PAD_LEFT);

    return array_replace_recursive([
        'id'  => $n,
        'ref' => 'camera-' . $due,

        // Gli indirizzi sono per lingua: non si traduce una pagina duplicandola.
        'slug' => [
            'it' => 'camera-' . $due,
            'en' => 'room-' . $due,
            'es' => 'habitacion-' . $due,
        ],

        // I nomi definitivi non sono stati forniti. Fino ad allora l'ordinale
        // è il nome: meglio un numero onesto di un nome inventato che il
        // cliente dovrà poi smentire agli ospiti.
        'name' => ['it' => 'Camera ' . $due, 'en' => 'Room ' . $due, 'es' => 'Habitación ' . $due],
        'name_confirmed' => false,

        // Vero quando la fotografia è della casa e si può pubblicare.
        'photographed' => false,

        'confirmed' => false,
        'position'  => $n,

        'type'        => 'twin',
        'occupancy'   => ['standard' => 2, 'max' => 2],
        'beds'        => ['single' => 2],
        'layouts'     => ['double', 'twin'],
        'size_sqm'    => null,              // non si misura da una fotografia
        'floor'       => null,              // da confermare
        'view'        => 'demo',
        'bathroom'    => ['private' => true, 'shower' => true, 'bathtub' => false],
        'amenities'   => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels'],

        'images' => [
            'card'    => ['src' => 'img/demo/camera-' . $due . '-4x3.svg',  'ratio' => '4/3'],
            'list'    => ['src' => 'img/demo/camera-' . $due . '-3x2.svg',  'ratio' => '3/2'],
            'hero'    => ['src' => 'img/demo/camera-' . $due . '-16x9.svg', 'ratio' => '16/9'],
            'gallery' => [
                ['src' => 'img/demo/camera-' . $due . '-1x1-a.svg', 'ratio' => '1/1'],
                ['src' => 'img/demo/camera-' . $due . '-1x1-b.svg', 'ratio' => '1/1'],
            ],
        ],

        // Il testo alternativo dice cosa si vede, non cosa è.
        'alt' => ['it' => null, 'en' => null],

        'price' => [
            'confirmed' => false,
            'currency'  => 'EUR',
            // Tariffa dimostrativa: serve al motore di prenotazione per
            // calcolare un totale credibile. Non è un prezzo del cliente.
            'demo_from' => 85,
        ],

        // `true` solo dove la fotografia mostra il campanile dalla finestra.
        'view_san_rufino' => null,
    ], $overrides);
};

/** Le immagini vere di una camera fotografata, nei cinque tagli del sistema. */
$fotografie = static function (string $nome): array {
    return [
        'card'    => ['src' => "img/camere/{$nome}-4x3",  'ratio' => '4/3'],
        'list'    => ['src' => "img/camere/{$nome}-3x2",  'ratio' => '3/2'],
        'hero'    => ['src' => "img/camere/{$nome}-16x9", 'ratio' => '16/9'],
        'gallery' => [['src' => "img/camere/{$nome}-1x1", 'ratio' => '1/1']],
    ];
};

return [
    $camera(1, [
        'photographed'    => true,
        'images'          => $fotografie('camera-01'),
        'view_san_rufino' => true,
        'alt' => [
            'it' => 'Due letti singoli affiancati con coperte gialle; dalla finestra aperta si vede '
                  . 'il campanile in pietra di San Rufino.',
            'en' => 'Two single beds side by side with yellow covers; through the open window, the stone '
                  . 'bell tower of San Rufino.',
        ],
        'price' => ['demo_from' => 85],
    ]),
    $camera(2, [
        'photographed'    => true,
        'images'          => $fotografie('camera-02'),
        'view_san_rufino' => true,
        'alt' => [
            'it' => 'Due letti singoli affiancati con coperte gialle; dalla finestra si vedono il '
                  . 'campanile e la facciata della cattedrale di San Rufino.',
            'en' => 'Two single beds side by side with yellow covers; through the window, the bell tower '
                  . 'and the façade of the cathedral of San Rufino.',
        ],
        'price' => ['demo_from' => 90],
    ]),
    $camera(3, [
        'photographed'    => true,
        'images'          => $fotografie('camera-03'),
        'view_san_rufino' => true,
        'amenities'       => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels', 'wardrobe'],
        'alt' => [
            'it' => 'Due letti singoli affiancati con coperte gialle e un armadio in legno chiaro; '
                  . 'dalla finestra si vede il campanile di San Rufino.',
            'en' => 'Two single beds side by side with yellow covers and a pale wooden wardrobe; through '
                  . 'the window, the bell tower of San Rufino.',
        ],
        'price' => ['demo_from' => 85],
    ]),
    $camera(4, [
        'photographed' => true,
        'images'       => $fotografie('camera-04'),
        'type'         => 'double',
        'beds'         => ['double' => 1],
        'layouts'      => ['double'],
        'amenities'    => ['private-bathroom', 'wifi', 'heating', 'linen', 'towels', 'desk'],
        'alt' => [
            'it' => 'Un letto matrimoniale con coperta color crema, pavimento in parquet, una scrivania '
                  . 'e due sedie impagliate sul fondo.',
            'en' => 'A double bed with a cream cover, a parquet floor, a desk and two rush-seated chairs '
                  . 'at the far end.',
        ],
        'price' => ['demo_from' => 95],
    ]),
    // La quinta non ha ancora una fotografia: tiene il segnaposto, e i suoi
    // attributi restano quelli dimostrativi finché non arrivano i dati veri.
    $camera(5, [
        'type'      => 'triple',
        'occupancy' => ['standard' => 2, 'max' => 3],
        'beds'      => ['double' => 1, 'single' => 1],
        'layouts'   => ['double', 'triple'],
        'price'     => ['demo_from' => 110],
    ]),
];
