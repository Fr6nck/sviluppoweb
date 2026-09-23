<?php
/**
 * Le domande di sempre.
 *
 * Testo per lingua accanto alla domanda: la FAQ è contenuto editoriale, non
 * un'etichetta di interfaccia, e un cliente che la aggiorna vuole vedere
 * domanda e risposta vicine nelle due lingue.
 *
 * Il segno «{dc}» viene sostituito dal marcatore «da confermare». Lo stesso
 * testo alimenta il markup e il JSON-LD di schema.org/FAQPage, così la pagina
 * e il dato strutturato dicono la stessa cosa — che è la sola versione che
 * Google accetta.
 *
 * Una risposta con `confirmed => true` è verificata dal materiale consegnato.
 */

declare(strict_types=1);

return [
    [
        'id'        => 'quante-camere',
        'confirmed' => true,
        'q' => [
            'it' => 'Quante camere ci sono?',
            'en' => 'How many rooms are there?',
        ],
        'a' => [
            'it' => 'Cinque. Arco del Vento è un affittacamere, non un albergo: cinque stanze nella stessa '
                  . 'casa, in Via Santa Maria delle Rose 1/A.',
            'en' => 'Five. Arco del Vento rents guest rooms, it is not a hotel: five rooms in the same house, '
                  . 'at Via Santa Maria delle Rose 1/A.',
        ],
    ],
    [
        'id'        => 'chi-accoglie',
        'confirmed' => true,
        'q' => [
            'it' => 'Chi ci accoglie quando arriviamo?',
            'en' => 'Who greets us when we arrive?',
        ],
        'a' => [
            'it' => 'Daniele Pecetta, il titolare. Ha aperto nel 2002 e gestisce la casa di persona: è lui '
                  . 'a rispondere ai messaggi e a consegnare le chiavi.',
            'en' => 'Daniele Pecetta, the owner. He opened in 2002 and runs the house himself: he is the one '
                  . 'who answers messages and hands over the keys.',
        ],
    ],
    [
        'id'        => 'check-in',
        'confirmed' => false,
        'q' => [
            'it' => 'A che ora possiamo arrivare?',
            'en' => 'What time can we arrive?',
        ],
        'a' => [
            'it' => 'L’orario di check-in è {dc}. Se il tuo treno o il tuo volo arrivano fuori da quella '
                  . 'fascia, scrivilo quando prenoti invece che il giorno stesso: è l’unica cosa che va '
                  . 'organizzata prima.',
            'en' => 'Check-in is {dc}. If your train or flight gets in outside that window, say so when you '
                  . 'book rather than on the day: it is the one thing worth arranging in advance.',
        ],
    ],
    [
        'id'        => 'parcheggio',
        'confirmed' => false,
        'q' => [
            'it' => 'Dove si lascia la macchina?',
            'en' => 'Where do we leave the car?',
        ],
        'a' => [
            'it' => 'Il parcheggio che consigliamo per questa casa, e come si fa l’ultimo tratto con i '
                  . 'bagagli, è {dc}. È la domanda che arriva più spesso e merita una risposta precisa, '
                  . 'non una generica.',
            'en' => 'The car park we recommend for this house, and how to do the last stretch with luggage, '
                  . 'is {dc}. It is the question that comes up most often and deserves a precise answer, '
                  . 'not a generic one.',
        ],
    ],
    [
        'id'        => 'scale',
        'confirmed' => false,
        'q' => [
            'it' => 'Ci sono scale? C’è l’ascensore?',
            'en' => 'Are there stairs? Is there a lift?',
        ],
        'a' => [
            'it' => 'Quante rampe separano la strada dalle camere, e se ci sia un ascensore, è {dc}. In una '
                  . 'casa del centro storico è un dato che cambia il viaggio a chi ha una valigia pesante o '
                  . 'un ginocchio che non collabora, quindi va scritto per esteso.',
            'en' => 'How many flights of stairs there are between the street and the rooms, and whether '
                  . 'there is a lift, is {dc}. In a house in the old town this changes the trip for anyone '
                  . 'with a heavy suitcase or an uncooperative knee, so it has to be spelled out.',
        ],
    ],
    [
        'id'        => 'colazione',
        'confirmed' => false,
        'q' => [
            'it' => 'La colazione è compresa?',
            'en' => 'Is breakfast included?',
        ],
        'a' => [
            'it' => 'Se la colazione ci sia è {dc}: nel materiale consegnato non è dichiarata, e un sito non '
                  . 'può prometterla al posto di chi la prepara. Appena è confermato, la risposta prende il '
                  . 'posto di questa riga.',
            'en' => 'Whether breakfast is served is {dc}: the material handed over does not say, and a '
                  . 'website cannot promise it on behalf of whoever would make it. As soon as it is '
                  . 'confirmed, the answer replaces this line.',
        ],
    ],
];
