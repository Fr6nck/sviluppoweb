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
            'it' => 'Dalle 13:00 alle 20:00, e ti accoglie Daniele in persona: non c’è una cassetta con '
                  . 'le chiavi. Se il tuo treno o il tuo volo arrivano fuori da quella fascia, scrivilo '
                  . 'quando prenoti invece che il giorno stesso. L’orario di check-out è {dc}.',
            'en' => 'Between 13:00 and 20:00, and Daniele meets you himself: there is no key box. If your '
                  . 'train or flight gets in outside that window, say so when you book rather than on the '
                  . 'day. The check-out time is {dc}.',
        ],
    ],
    [
        'id'        => 'parcheggio',
        'confirmed' => true,
        'q' => [
            'it' => 'Dove si lascia la macchina?',
            'en' => 'Where do we leave the car?',
        ],
        'a' => [
            'it' => 'In auto non si arriva fino alla porta. Il parcheggio più comodo è Piazza Matteotti, a '
                  . 'pagamento — circa 18 € per 24 ore — a duecento metri da casa; in alternativa si lascia '
                  . 'gratis in via dell’Eremo, verso la Porta dei Cappuccini. L’ultimo tratto si fa a piedi, '
                  . 'quindi conviene una valigia che si porti su qualche scalino.',
            'en' => 'You cannot drive to the door. The handiest car park is Piazza Matteotti — paid, about '
                  . '€18 per 24 hours — two hundred metres from the house; otherwise you can park free in '
                  . 'via dell’Eremo, towards Porta dei Cappuccini. The last stretch is on foot, so pack a '
                  . 'case you do not mind carrying up a few steps.',
        ],
    ],
    [
        'id'        => 'scale',
        'confirmed' => true,
        'q' => [
            'it' => 'Ci sono scale? C’è l’ascensore?',
            'en' => 'Are there stairs? Is there a lift?',
        ],
        'a' => [
            'it' => 'Le camere sono al secondo piano e l’ascensore non c’è. Si salgono due rampe: una decina '
                  . 'di scalini, poi altri tre. Non è una salita lunga, ma va saputa prima da chi ha una '
                  . 'valigia pesante o un ginocchio che non collabora.',
            'en' => 'The rooms are on the second floor and there is no lift. It is two flights: about ten '
                  . 'steps, then three more. It is not a long climb, but it is worth knowing in advance if '
                  . 'you have a heavy suitcase or an uncooperative knee.',
        ],
    ],
    [
        'id'        => 'colazione',
        'confirmed' => true,
        'q' => [
            'it' => 'La colazione è compresa?',
            'en' => 'Is breakfast included?',
        ],
        'a' => [
            'it' => 'No, e non c’è nemmeno servita a parte: Arco del Vento è un affittacamere, non dà pasti. '
                  . 'In camera trovi un bollitore, e a pochi passi ci sono i bar del centro. Lo diciamo qui '
                  . 'perché è una cosa da sapere prima di prenotare, non da scoprire la mattina.',
            'en' => 'No, and it is not available separately either: Arco del Vento rents rooms and does not '
                  . 'serve meals. There is a kettle in the room, and the cafés of the old town are a few '
                  . 'steps away. We say so here because it is something to know before booking, not to '
                  . 'discover in the morning.',
        ],
    ],
];
