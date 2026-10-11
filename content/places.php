<?php
/**
 * I luoghi che si raggiungono a piedi dalla casa.
 *
 * `walk_minutes` è il tempo a piedi dalla porta di Via Santa Maria delle Rose,
 * a passo tranquillo, approvato dal titolare (ottobre 2026). Dove a piedi non
 * ha senso — l'Eremo delle Carceri, 4 km in salita — vale `null` e il sito
 * mostra la frase «places.<id>.time» dei file di lingua.
 *
 * Il nome e la nota di ogni luogo stanno nei file di lingua, sotto «places»,
 * perché vanno tradotti; qui sta l'ordine e il dato misurabile.
 */

declare(strict_types=1);

return [
    // San Rufino apre l'elenco: è la cattedrale della città ed è la chiesa
    // del quartiere in cui sta la casa.
    ['id' => 'san-rufino',    'walk_minutes' => 2, 'landmark' => true],
    ['id' => 'comune',        'walk_minutes' => 4, 'landmark' => false],
    ['id' => 'santa-chiara',  'walk_minutes' => 6, 'landmark' => true],
    ['id' => 'spoliazione',   'walk_minutes' => 8, 'landmark' => false],
    ['id' => 'san-francesco', 'walk_minutes' => 20, 'landmark' => true],
    ['id' => 'carceri',       'walk_minutes' => null, 'landmark' => false],
];
