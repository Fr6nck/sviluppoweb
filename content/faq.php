<?php
/**
 * Le domande di sempre.
 *
 * Testo per lingua accanto alla domanda: la FAQ è contenuto editoriale, non
 * un'etichetta di interfaccia, e un cliente che la aggiorna vuole vedere
 * domanda e risposta vicine nelle due lingue.
 *
 * I segni fra graffe li riempie il sito con i dati della struttura, così un
 * orario cambiato nell'area riservata cambia anche qui: {checkout} è l'orario
 * di check-out, {checkin} la fascia di arrivo. Il segno «{dc}» viene
 * sostituito dal marcatore «da confermare». Lo stesso
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
        'confirmed' => true,
        'q' => [
            'it' => 'A che ora possiamo arrivare?',
            'en' => 'What time can we arrive?',
        ],
        'a' => [
            'it' => 'Dalle {checkin}, e ti accoglie Daniele in persona per consegnarti le chiavi. Se il tuo '
                  . 'treno o il tuo volo arrivano fuori da quella fascia, scrivilo quando prenoti: si trova '
                  . 'sempre una soluzione. Il check-out è entro le {checkout}.',
            'en' => 'From {checkin}, and Daniele meets you himself to hand over the keys. If your train or '
                  . 'flight gets in outside that window, say so when you book: there is always a way. '
                  . 'Check-out is by {checkout}.',
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
            'it' => 'In auto non si arriva fino alla porta: si parcheggia in Piazza Matteotti, a pagamento, '
                  . 'a circa duecento metri da casa. Tariffe e orari aggiornati sono sul sito del gestore, '
                  . 'nelle informazioni pratiche. L’ultimo tratto si fa a piedi, con qualche scalino.',
            'en' => 'You cannot drive to the door: you park in Piazza Matteotti, a paid car park about two '
                  . 'hundred metres from the house. Current prices and hours are on the operator’s website, '
                  . 'linked in the practical information. The last stretch is on foot, up a few steps.',
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
            'it' => 'Le camere sono al secondo piano e l’ascensore non c’è: si salgono due rampe, una decina '
                  . 'di scalini e poi altri tre. Non è una salita lunga, ma è bene saperlo se si viaggia con '
                  . 'valigie pesanti.',
            'en' => 'The rooms are on the second floor and there is no lift: two flights, about ten steps '
                  . 'and then three more. It is not a long climb, but worth knowing if you travel with heavy '
                  . 'luggage.',
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
            'it' => 'No: Arco del Vento è un affittacamere e non serve pasti. In camera trovi un bollitore, '
                  . 'e i bar del centro storico sono a pochi passi: Daniele ti dice volentieri i suoi preferiti.',
            'en' => 'No: Arco del Vento rents rooms and does not serve meals. There is a kettle in the room, '
                  . 'and the cafés of the old town are a few steps away: Daniele will gladly tell you his '
                  . 'favourites.',
        ],
    ],
];
