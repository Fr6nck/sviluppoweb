<?php
/**
 * Le cinque camere.
 *
 * CONTENUTO DIMOSTRATIVO. Confermato dal cliente c'è solo il numero — cinque —
 * e il fatto che la casa sia un affittacamere in Via Santa Maria delle Rose.
 * Nomi, metrature, letti, esposizioni, servizi e tariffe qui sotto servono a
 * far funzionare e leggere il sito finché non arrivano i dati veri.
 *
 * Ogni scheda porta `confirmed => false`: finché è così, l'elenco delle camere
 * mostra in cima un avviso che lo dice, i nomi restano «Camera 01…05» e le
 * tariffe sono marcate come da confermare. Confermando una camera si mette
 * `confirmed => true`, si scrivono i valori veri e il sito smette di segnalarla.
 *
 * Le fotografie: nel materiale consegnato non ci sono immagini delle camere di
 * Arco del Vento con una provenienza dichiarata, quindi nessuna foto d'interno
 * va sul sito pubblico. I file in public/assets/img/demo/ sono segnaposto
 * disegnati, tagliati ai rapporti del design system (4:3 per la scheda, 3:2
 * per l'elenco, 16:9 per l'apertura): la foto vera prende lo stesso nome e
 * lo stesso rapporto, e non cambia nient'altro.
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

        'confirmed' => false,
        'position'  => $n,

        'type'        => 'double',          // chiave tradotta in content/lang/
        'occupancy'   => ['standard' => 2, 'max' => 2],
        'beds'        => ['double' => 1],
        'layouts'     => ['double'],        // configurazioni possibili
        'size_sqm'    => null,              // da confermare
        'floor'       => null,              // da confermare
        'view'        => 'demo',            // chiave tradotta
        'bathroom'    => ['private' => true, 'shower' => true, 'bathtub' => false],
        'amenities'   => ['private-bathroom', 'wifi', 'linen', 'towels'],

        'images' => [
            'card'    => ['src' => 'img/demo/camera-' . $due . '-4x3.svg',  'ratio' => '4/3'],
            'list'    => ['src' => 'img/demo/camera-' . $due . '-3x2.svg',  'ratio' => '3/2'],
            'hero'    => ['src' => 'img/demo/camera-' . $due . '-16x9.svg', 'ratio' => '16/9'],
            'gallery' => [
                ['src' => 'img/demo/camera-' . $due . '-1x1-a.svg', 'ratio' => '1/1'],
                ['src' => 'img/demo/camera-' . $due . '-1x1-b.svg', 'ratio' => '1/1'],
            ],
        ],

        'price' => [
            'confirmed' => false,
            'currency'  => 'EUR',
            // Tariffa dimostrativa: serve al motore di prenotazione per
            // calcolare un totale credibile. Non è un prezzo del cliente.
            'demo_from' => 85,
        ],

        // Quali camere vedano la Cattedrale di San Rufino è la cosa che più
        // conta per il racconto di questo sito, ed è anche una di quelle che
        // non si possono dedurre da una pianta. Il campo aspetta la conferma.
        'view_san_rufino' => null,
    ], $overrides);
};

return [
    $camera(1, [
        'type'      => 'double',
        'occupancy' => ['standard' => 2, 'max' => 2],
        'beds'      => ['double' => 1],
        'layouts'   => ['double', 'twin'],
        'price'     => ['demo_from' => 85],
    ]),
    $camera(2, [
        'type'      => 'double-extra',
        'occupancy' => ['standard' => 2, 'max' => 3],
        'beds'      => ['double' => 1, 'single' => 1],
        'layouts'   => ['double', 'triple'],
        'amenities' => ['private-bathroom', 'wifi', 'linen', 'towels', 'desk'],
        'price'     => ['demo_from' => 95],
    ]),
    $camera(3, [
        'type'      => 'twin',
        'occupancy' => ['standard' => 2, 'max' => 2],
        'beds'      => ['single' => 2],
        'layouts'   => ['twin', 'double'],
        'price'     => ['demo_from' => 85],
    ]),
    $camera(4, [
        'type'      => 'triple',
        'occupancy' => ['standard' => 3, 'max' => 3],
        'beds'      => ['double' => 1, 'single' => 1],
        'layouts'   => ['triple', 'double'],
        'amenities' => ['private-bathroom', 'wifi', 'linen', 'towels', 'wardrobe'],
        'price'     => ['demo_from' => 110],
    ]),
    $camera(5, [
        'type'      => 'single',
        'occupancy' => ['standard' => 1, 'max' => 2],
        'beds'      => ['single' => 1],
        'layouts'   => ['single', 'double'],
        'price'     => ['demo_from' => 70],
    ]),
];
