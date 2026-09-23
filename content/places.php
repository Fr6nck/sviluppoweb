<?php
/**
 * I luoghi che si raggiungono a piedi dalla casa.
 *
 * `walk_minutes` è il tempo a piedi dalla porta di Via Santa Maria delle Rose.
 * Vale `null` finché non è verificato sul posto: un tempo di percorrenza è un
 * dato che si misura camminando, non si stima da una mappa — e sbagliarlo
 * significa un ospite che arriva in ritardo per colpa del sito.
 *
 * Il nome e la nota di ogni luogo stanno nei file di lingua, sotto «places»,
 * perché vanno tradotti; qui sta l'ordine e il dato misurabile.
 */

declare(strict_types=1);

return [
    // San Rufino apre l'elenco: è la cattedrale della città ed è la chiesa
    // del quartiere in cui sta la casa.
    ['id' => 'san-rufino',    'walk_minutes' => null, 'landmark' => true],
    ['id' => 'comune',        'walk_minutes' => null, 'landmark' => false],
    ['id' => 'santa-chiara',  'walk_minutes' => null, 'landmark' => true],
    ['id' => 'spoliazione',   'walk_minutes' => null, 'landmark' => false],
    ['id' => 'san-francesco', 'walk_minutes' => null, 'landmark' => true],
    ['id' => 'carceri',       'walk_minutes' => null, 'landmark' => false],
];
