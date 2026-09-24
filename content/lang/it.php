<?php
/**
 * Italiano — la lingua di casa.
 *
 * Voce, dal manuale del design system: si dà del tu a chi legge e si parla al
 * plurale per la casa. Frasi corte e concrete. Nessun superlativo turistico.
 * Assisi si nomina una volta per pagina. Gli occhielli sono scritti in
 * maiuscolo qui dentro, non con text-transform, perché gli screen reader li
 * leggano come parole e non lettera per lettera.
 */

declare(strict_types=1);

return [
    'common' => [
        'brand'            => 'Arco del Vento',
        'brand_full'       => 'Arco del Vento di Pecetta Daniele',
        'to_confirm'       => '[da confermare]',
        'price_to_confirm' => '[tariffa da confermare]',
        'demo_asset'       => '[immagine dimostrativa]',
        'skip'             => 'Vai al contenuto',
        'menu_open'        => 'Apri il menu',
        'menu_close'       => 'Chiudi il menu',
        'menu'             => 'Menu',
        'language'         => 'Lingua',
        'per_night'        => 'a notte',
        'from'             => 'da',
        'night'            => 'notte',
        'nights'           => 'notti',
        'guest'            => 'ospite',
        'guests'           => 'ospiti',
        'optional'         => 'facoltativo',
        'yes'              => 'Sì',
        'no'               => 'No',
        'required_note'    => 'I campi senza la nota «facoltativo» servono tutti.',
        'back'             => 'Torna indietro',
        'close'            => 'Chiudi',
        'loading'          => 'Un momento',
        'read_more'        => 'Continua a leggere',
    ],

    'date' => [
        'months' => [
            'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno',
            'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre',
        ],
        'weekdays_short' => ['dom', 'lun', 'mar', 'mer', 'gio', 'ven', 'sab'],
    ],

    'nav' => [
        'label'    => 'Principale',
        'home'     => 'Home',
        'rooms'    => 'Camere',
        'property' => 'La struttura',
        'assisi'   => 'Assisi a piedi',
        'info'     => 'Informazioni',
        'contact'  => 'Contatti',
        'book'     => 'Prenota ora',
        'privacy'  => 'Privacy',
    ],

    'cta' => [
        'book'         => 'Prenota ora',
        'book_room'    => 'Prenota questa camera',
        'check'        => 'Verifica disponibilità',
        'whatsapp'     => 'WhatsApp',
        'write'        => 'Scrivici',
        'call'         => 'Chiama',
        'see_rooms'    => 'Scopri le camere',
        'see_room'     => 'Guarda la camera',
        'all_rooms'    => 'Tutte e cinque le camere',
        'see_assisi'   => 'Assisi a piedi',
        'see_info'     => 'Informazioni pratiche',
        'see_property' => 'Dentro la casa',
        'send'         => 'Invia la richiesta',
        'continue'     => 'Continua',
    ],

    // ------------------------------------------------------------------ Home
    'home' => [
        'seo_title'       => 'Arco del Vento — affittacamere ad Assisi, cinque camere in centro',
        'seo_description' => 'Cinque camere in una casa di Assisi, in Via Santa Maria delle Rose. '
                           . 'Un affittacamere gestito da Daniele Pecetta dal 2002. Prenotazione diretta.',

        'trust' => [
            'Cinque camere',
            'Nel centro storico',
            'Aperto dal 2002',
            'Ti accoglie il titolare',
        ],

        'hero' => [
            'eyebrow' => 'AFFITTACAMERE · VIA SANTA MARIA DELLE ROSE',
            'title'   => 'Cinque camere in una casa di',
            'sign'    => 'Assisi',
            'lead'    => 'Via Santa Maria delle Rose 1/A. Cinque stanze, gestite da Daniele Pecetta dal 2002. '
                       . 'Non siamo un albergo: siamo una casa che affitta camere, e la differenza si sente dal primo giorno.',
            'image_alt' => 'Un vicolo in salita del centro di Assisi, con un campanile in pietra sullo sfondo.',
            'image_credit' => 'Assisi, centro storico — fotografia Niels Baars, Unsplash',
        ],

        'position' => [
            'eyebrow' => 'LA POSIZIONE',
            'title'   => 'La città comincia fuori dalla',
            'sign'    => 'porta',
            'lead'    => 'La casa sta in Via Santa Maria delle Rose, nel centro storico. Da qui non serve '
                       . 'l’auto: tutto quello che si viene a vedere si raggiunge camminando.',
            'rufino_title' => 'San Rufino',
            'rufino_text'  => 'San Rufino è la chiesa del quartiere ed è la cattedrale della città: nel suo '
                            . 'fonte battesimale sono stati battezzati Francesco e Chiara. È il riferimento '
                            . 'più vicino alla casa, e il primo campanile che si impara a riconoscere.',
            'rufino_note'  => 'Quali camere vedano il campanile dalla finestra',
            'frame_caption' => 'LA VALLE VISTA DALLA COLLINA DI ASSISI',
            'frame_count'   => 'UMBRIA',
            'frame_alt'     => 'La valle umbra vista dall’alto, con i campi coltivati e un borgo in lontananza.',
        ],

        'rooms' => [
            'eyebrow' => 'LE CAMERE',
            'title'   => 'Le cinque camere della',
            'sign'    => 'casa',
            'lead'    => 'Cinque stanze, tutte nella stessa casa. Quello che le distingue sono i letti, '
                       . 'l’esposizione e quanto ci si sta larghi.',
        ],

        'manifesto' => [
            'text'   => 'Cinque camere. Una sola persona a darti le chiavi,',
            'sign'   => 'dal 2002',
            'author' => 'ARCO DEL VENTO · AFFITTACAMERE',
        ],

        'reviews' => [
            'title'   => 'Chi è già stato qui',
            'booking' => 'Booking',
            'google'  => 'Google',
            'count'   => ':count recensioni su :platform',
        ],

        'host' => [
            'eyebrow' => 'CHI TIENE APERTO',
            'title'   => 'La casa la manda avanti una',
            'sign'    => 'persona',
            'lead'    => 'Nessuna reception, nessun servizio in camera, nessun numero verde. C’è Daniele, '
                       . 'che ha aperto nel 2002 e da allora tiene aperto.',
            'numbers' => [
                ['value' => '5',    'name' => 'CAMERE'],
                ['value' => '2002', 'name' => 'APERTO DAL'],
                ['value' => '1',    'name' => 'PERSONA A GESTIRLA'],
            ],
        ],

        'walk' => [
            'eyebrow' => 'A PIEDI',
            'title'   => 'Quanto ci vuole, davvero, a',
            'sign'    => 'piedi',
            'lead'    => 'I tempi qui sotto sono quelli che serve sapere prima di prenotare. Vanno confermati '
                       . 'da Daniele prima di finire sul sito pubblico: un minuto dichiarato male è un ospite '
                       . 'che arriva in ritardo.',
            'image_alt' => 'La Basilica di San Francesco al tramonto, con la facciata in pietra chiara illuminata di lato.',
            'image_credit' => 'Basilica di San Francesco — fotografia Alessandro Guarino, Unsplash',
        ],

        'faq' => [
            'eyebrow' => 'LE DOMANDE DI SEMPRE',
            'title'   => 'Quello che si chiede',
            'sign'    => 'prima',
        ],

        'closing' => [
            'eyebrow' => 'PRENOTA',
            'title'   => 'Chiedi le date che ti',
            'sign'    => 'servono',
            'lead'    => 'La richiesta arriva direttamente a Daniele, senza intermediari e senza commissioni '
                       . 'di piattaforma. Ti risponde lui.',
        ],
    ],

    // ---------------------------------------------------------------- Camere
    'rooms' => [
        'seo_title'       => 'Le cinque camere — Arco del Vento, Assisi',
        'seo_description' => 'Le cinque camere di Arco del Vento, affittacamere in Via Santa Maria delle Rose, '
                           . 'ad Assisi. Letti, occupazione, servizi e tariffe.',
        'eyebrow'   => 'LE CAMERE',
        'title'     => 'Cinque stanze, una',
        'sign'      => 'casa',
        'lead'      => 'Sono cinque e stanno tutte nello stesso edificio. Qui sotto trovi letti, occupazione '
                     . 'massima e servizi di ciascuna; la disponibilità si verifica con le date.',
        'image_alt' => 'Segnaposto: la fotografia della casa non è ancora disponibile.',
        'corridor_alt' => 'Il corridoio della casa, con il pavimento in parquet e una rosa dei venti '
                        . 'intarsiata nel legno; alle pareti, vedute di Assisi incorniciate.',
        'corridor_caption' => 'La rosa dei venti nel pavimento del corridoio. È la stessa del marchio, '
                            . 'ed era lì prima.',

        'demo_notice_title' => 'Che cosa manca ancora',
        'demo_notice_text'  => 'Tariffe, tipologie, letti, occupazione e piano sono quelli veri, e le '
                             . 'fotografie sono della casa. Restano da scrivere le metrature. La Camera 01 '
                             . '— la tripla — è l’unica ancora senza fotografia.',

        'list_title'    => 'Una per',
        'list_sign'     => 'una',
        'rates_title'   => 'Le tariffe a confronto',
        'price_on_dates' => 'tariffa con le date',
        'price_on_dates_long' => 'La tariffa dipende dalle date e da quante persone siete: la vedi '
                               . 'scegliendo il periodo, senza registrarti e senza impegno.',
        'rates_hidden'  => 'Le tariffe cambiano con il periodo e con quante persone dormono in camera, '
                         . 'quindi non c’è un listino fermo in pagina: scegli le date e vedi il prezzo '
                         . 'vero per quelle notti. Non serve registrarsi e non impegna a niente.',
        'rates_note'    => 'Il prezzo è della camera, non della persona: una matrimoniale occupata '
                         . 'da una sola persona costa meno.',
        'rates_caption' => 'Tariffa a notte per camera, secondo quante persone dormono. Tassa di '
                         . 'soggiorno esclusa. Eventuali variazioni stagionali sono da confermare.',
        'table' => [
            'room'      => 'Camera',
            'type'      => 'Tipologia',
            'occupancy' => 'Ospiti',
            'beds'      => 'Letti',
            'rate'          => 'A notte',
            'not_available' => 'non disponibile per questo numero di ospiti',
        ],
        'status' => [
            'free'     => 'LIBERA',
            'last'     => 'ULTIME DATE',
            'busy'     => 'OCCUPATA',
            'to_check' => 'DISPONIBILITÀ DA VERIFICARE',
        ],
    ],

    'room' => [
        'seo_description' => ':name — affittacamere Arco del Vento, Via Santa Maria delle Rose, Assisi. '
                           . 'Letti, occupazione, servizi e prenotazione diretta.',
        'eyebrow'       => 'CAMERA',
        'gallery'       => 'La camera',
        'gallery_note'  => 'Le fotografie di questa camera non sono ancora disponibili.',
        'photo_pending' => 'Questa camera aspetta ancora la sua fotografia.',
        'description'   => 'La camera',
        'characteristics' => 'Caratteristiche',
        'amenities'     => 'Servizi',
        'bathroom'      => 'Il bagno',
        'view'          => 'Esposizione',
        'rate'          => 'Tariffa',
        'other_rooms'   => 'Le altre camere',
        'name_to_confirm' => 'Nome della camera',
        'meta' => [
            'occupancy' => 'Ospiti',
            'beds'      => 'Letti',
            'layouts'   => 'Configurazioni',
            'size'      => 'Metratura',
            'floor'     => 'Piano',
        ],
        'not_found_title' => 'Questa camera non esiste',
        'not_found_text'  => 'L’indirizzo che hai seguito non corrisponde a nessuna delle cinque camere. '
                           . 'Le trovi tutte nell’elenco.',
    ],

    'room_types' => [
        'single'       => 'Singola',
        'double'       => 'Matrimoniale',
        'double-extra' => 'Matrimoniale con letto aggiunto',
        'double-twin'  => 'Doppia, letti separabili',
        'single-double' => 'Singola con letto matrimoniale',
        'twin'         => 'Doppia a due letti',
        'triple'       => 'Tripla',
    ],

    'beds' => [
        'double' => ['uno matrimoniale', ':count matrimoniali'],
        'single' => ['uno singolo', ':count singoli'],
        'sofa'   => ['un divano letto', ':count divani letto'],
    ],

    'layouts' => [
        'single' => 'uso singola',
        'double' => 'matrimoniale',
        'twin'   => 'due letti separati',
        'triple' => 'tripla',
    ],

    'views' => [
        'demo'       => 'Esposizione da confermare',
        'piazza'     => 'Su Piazza San Rufino e il Duomo',
    ],

    'amenities' => [
        'private-bathroom' => 'Bagno privato',
        'wifi'             => 'Wi-Fi',
        'linen'            => 'Biancheria',
        'towels'           => 'Asciugamani',
        'desk'             => 'Scrittoio',
        'wardrobe'         => 'Armadio',
        'kettle'           => 'Bollitore',
        'minibar'          => 'Mini frigo su richiesta',
        'fan'              => 'Ventilatore',
        'heating'          => 'Riscaldamento',
    ],

    'bathroom' => [
        'private' => 'Bagno privato',
        'shared'  => 'Bagno in comune',
        'shower'  => 'Doccia',
        'bathtub' => 'Vasca',
    ],

    // ----------------------------------------------------------- La struttura
    'property' => [
        'seo_title'       => 'La struttura — Arco del Vento, affittacamere ad Assisi',
        'seo_description' => 'Cinque camere in Via Santa Maria delle Rose, ad Assisi. Un affittacamere '
                           . 'gestito di persona da Daniele Pecetta dal 2002.',
        'eyebrow' => 'LA STRUTTURA',
        'title'   => 'Una casa, cinque camere, una',
        'sign'    => 'persona',
        'lead'    => 'Arco del Vento è un affittacamere: cinque stanze in una casa di Via Santa Maria delle '
                   . 'Rose, ad Assisi, affittate una per volta a chi passa. Non è un albergo e non prova a '
                   . 'sembrarlo.',
        'image_alt' => 'Segnaposto: la fotografia della casa non è ancora disponibile.',

        'what_title' => 'Che cosa vuol dire affittacamere',
        'what_text'  => 'Vuol dire che le camere sono poche e sono dentro una casa vera, che non c’è un '
                      . 'bancone all’ingresso e che chi ti apre la porta è la stessa persona che decide come '
                      . 'si sta. Vuol dire anche che alcune cose che un albergo dà per scontate qui non ci '
                      . 'sono: le trovi elencate, senza giri di parole, nelle informazioni pratiche.',

        'host_title' => 'Daniele',
        'host_text'  => 'Daniele Pecetta ha aperto Arco del Vento nel 2002 e da allora lo gestisce lui. '
                      . 'Risponde al telefono, consegna le chiavi, tiene in ordine le cinque stanze. '
                      . 'Quando scrivi a questo indirizzo, è lui che legge.',
        'host_role'  => 'TITOLARE · DAL 2002',
        'host_bio'   => 'Ha aperto l’affittacamere nel 2002 in Via Santa Maria delle Rose e da ventitré anni '
                      . 'lo tiene aperto di persona: cinque camere, nessun personale, nessuna reception.',
        'host_more'  => 'Il resto della storia della casa — com’era prima, cosa è stato rifatto e quando',

        'building_title' => 'L’edificio',
        'building_text'  => 'Della casa sappiamo che le camere stanno al secondo piano e che ci si arriva per '
                          . 'due rampe di scale. L’epoca, i materiali, com’era prima di diventare '
                          . 'affittacamere: questo no, e sono esattamente le cose che un ospite ricorda. '
                          . 'Vanno raccontate da chi le sa.',

        'city_title' => 'Stare dentro la città',
        'city_text'  => 'Dormire nel centro storico non è come dormire in periferia con la navetta: le '
                      . 'campane si sentono, le strade sono in salita e la macchina, se la porti, va lasciata '
                      . 'dove si può. In cambio, la mattina sei già dove gli altri stanno arrivando.',
    ],

    // ------------------------------------------------------------- Assisi
    'assisi' => [
        'seo_title'       => 'Assisi a piedi — Arco del Vento',
        'seo_description' => 'Cosa si raggiunge a piedi da Via Santa Maria delle Rose: San Rufino, Santa '
                           . 'Chiara, San Francesco, Piazza del Comune, la Spoliazione, le Carceri.',
        'eyebrow' => 'A PIEDI',
        'title'   => 'Tutto quello che c’è, senza salire in',
        'sign'    => 'auto',
        'lead'    => 'La casa è dentro il centro storico di Assisi: da lì si va a piedi, in salita e in '
                   . 'discesa, e la distanza si misura in minuti, non in chilometri.',
        'image_alt' => 'Un vicolo in salita del centro di Assisi, con un campanile in pietra sullo sfondo.',

        'places_title' => 'I sei posti che si raggiungono camminando',
        'places_note'  => 'I tempi a piedi devono essere verificati sul posto prima della pubblicazione. '
                        . 'Finché non lo sono, restano marcati.',
        'walk_label'   => 'a piedi',

        'rufino_title' => 'Perché San Rufino viene prima',
        'rufino_text'  => 'Perché è la cattedrale della città e la chiesa di questo quartiere, e perché è '
                        . 'la meno affollata delle tre grandi: si entra, si guarda il fonte battesimale dove '
                        . 'sono stati battezzati Francesco e Chiara, e si esce in una piazza dove ci si può '
                        . 'ancora sedere.',

        'moving_title' => 'Muoversi',
        'moving_text'  => 'Il centro si gira interamente a piedi. Chi arriva in treno scende a Santa Maria '
                        . 'degli Angeli, alla stazione di Assisi, e sale in autobus o in taxi; chi arriva in '
                        . 'auto lascia la macchina nei parcheggi a valle delle mura e fa l’ultimo tratto a '
                        . 'piedi o con le scale mobili.',
        'moving_note'  => 'Linee, orari e parcheggio consigliato per questa casa',
    ],

    'places' => [
        'san-rufino'   => ['name' => 'Cattedrale di San Rufino',   'note' => 'la cattedrale, e la chiesa del quartiere'],
        'santa-chiara' => ['name' => 'Basilica di Santa Chiara',    'note' => 'in fondo alla discesa, verso est'],
        'comune'       => ['name' => 'Piazza del Comune',           'note' => 'il tempio di Minerva e i caffè'],
        'spoliazione'  => ['name' => 'Santuario della Spoliazione',  'note' => 'accanto al vescovado'],
        'san-francesco' => ['name' => 'Basilica di San Francesco',  'note' => 'all’estremità opposta del centro'],
        'carceri'      => ['name' => 'Eremo delle Carceri',         'note' => 'fuori città, in salita sul Subasio'],
    ],

    // ------------------------------------------------------- Informazioni
    'info' => [
        'seo_title'       => 'Informazioni pratiche — Arco del Vento, Assisi',
        'seo_description' => 'Check-in, arrivo, parcheggio, scale, Wi-Fi, animali e regole della casa. '
                           . 'Le informazioni pratiche di Arco del Vento, affittacamere ad Assisi.',
        'eyebrow' => 'INFORMAZIONI',
        'title'   => 'Quello che significa stare',
        'sign'    => 'qui',
        'lead'    => 'Questa non è la pagina secondaria del sito: è quella che si legge la sera prima di '
                   . 'partire. Dove un dato manca è segnato, non arrotondato.',

        'notice_title' => 'Perché tanti dati sono segnati',
        'notice_text'  => 'Orari di arrivo, parcheggio, scale, servizi e regole della casa li ha dichiarati '
                        . 'Daniele, e li leggi come li ha detti. Quello che manca ancora resta marcato: '
                        . 'l’orario di check-out, come si paga e si disdice, i tempi a piedi verso i sei '
                        . 'luoghi. Un orario sbagliato sul sito è una telefonata in più e un ospite in meno, '
                        . 'quindi preferiamo lasciarlo in bianco.',

        'sections' => [
            'arrival'  => 'Prima del tuo arrivo',
            'getting'  => 'Come arrivare',
            'stay'     => 'Il soggiorno',
            'house'    => 'La casa',
            'rules'    => 'Regole',
            'contact'  => 'Contatti',
        ],

        'items' => [
            'check_in'      => 'Orario di check-in',
            'check_out'     => 'Orario di check-out',
            'welcome'       => 'Chi ti accoglie',
            'documents'     => 'Documenti',
            'contact_hours' => 'Quando rispondiamo',
            'navigator'     => 'Che cosa mettere nel navigatore',
            'taxi'          => 'In taxi',
            'plane'         => 'Dall’aeroporto',
            'kettle'        => 'Bollitore',
            'minibar'       => 'Mini frigo',
            'fans'          => 'Ventilatori',
            'air_conditioning' => 'Aria condizionata',
            'rooms'         => 'Quante camere',
            'floor'         => 'A che piano',
            'common_areas'  => 'Spazi comuni',
            'open'          => 'Quando siamo aperti',
            'guest_contact' => 'Chi prenota per altri',
            'late_arrival'  => 'Arrivo fuori orario',
            'parking'       => 'Parcheggio',
            'car'           => 'Arrivo in auto',
            'train'         => 'Arrivo in treno',
            'bus'           => 'Autobus e scale mobili',
            'stairs'        => 'Scale',
            'lift'          => 'Ascensore',
            'wifi'          => 'Wi-Fi',
            'breakfast'     => 'Colazione',
            'heating'       => 'Riscaldamento',
            'cleaning'      => 'Pulizia',
            'linen'         => 'Cambio biancheria',
            'pets'          => 'Animali',
            'smoking'       => 'Fumo',
            'children'      => 'Bambini',
            'city_tax'      => 'Tassa di soggiorno',
            'min_nights'    => 'Soggiorno minimo',
            'payment'       => 'Pagamento e caparra',
            'cancellation'  => 'Disdetta',
            'languages'     => 'Lingue parlate',
            'accessibility' => 'Accessibilità',
        ],

        'known' => [
            'welcome'       => 'Daniele, di persona: consegna le chiavi e mostra la casa.',
            'documents'     => 'Al check-in, oppure mandali prima su WhatsApp e facciamo più in fretta.',
            'min_nights'    => 'Un soggiorno che comprende un sabato dura almeno :nights notti.',
            'navigator'     => 'Imposta :place, non l’indirizzo: la via è senza uscita. A piedi l’indirizzo preciso funziona.',
            'car'           => 'In auto non si arriva fino alla porta: si lascia la macchina e si fa l’ultimo tratto a piedi.',
            'train'         => 'Stazione di Assisi, poi AssisiLink o la linea C fino a Piazza Matteotti.',
            'plane'         => 'Aeroporto di Perugia, servizio Airlink: circa quattro corse al giorno, arrivo a Piazza Matteotti.',
            'taxi'          => 'Fatti portare a :place.',
            'bus_note'      => 'Linee, orari aggiornati e il collegamento per l’Eremo delle Carceri',
            'no_meals'      => 'Non c’è: non serviamo pasti, e nemmeno la colazione.',
            'wifi'          => 'Sì, circa :speed — abbastanza per lavorare.',
            'city_tax'      => ':amount a persona per notte, per le prime :nights notti. Esenti i minori di :age anni. Si paga al check-in.',
            'rooms'         => 'Cinque, tutte con bagno privato.',
            'common_areas'  => 'L’ingresso è condominiale e il corridoio è di passaggio.',
            'open_all_year' => 'Tutto l’anno.',
            'no_smoking'    => 'Non si fuma nelle camere.',
            'guest_contact' => 'Se prenoti per qualcun altro, lasciaci un contatto diretto di chi dorme qui.',
            'remote_work'   => 'La connessione regge il lavoro da remoto.',
            'keys'          => 'Le chiavi le consegna Daniele di persona.',
            'address'       => 'Via Santa Maria delle Rose 1/A, Assisi.',
            'since'         => 'Aperto dal 2002.',
            'direct'        => 'La prenotazione è diretta: la richiesta arriva a Daniele, non a una piattaforma.',
        ],
    ],

    // ---------------------------------------------------------- Prenota
    'book' => [
        'seo_title'       => 'Prenota — Arco del Vento, Assisi',
        'seo_description' => 'Verifica le date e invia la richiesta di prenotazione ad Arco del Vento, '
                           . 'affittacamere in Via Santa Maria delle Rose, ad Assisi.',
        'eyebrow' => 'PRENOTA',
        'title'   => 'Le date, e poi tutto il',
        'sign'    => 'resto',
        'lead'    => 'Quattro passaggi: le date, la camera, i tuoi dati, la richiesta. Non si paga nulla '
                   . 'online e non serve un account.',

        'demo_title' => 'Le date sono di prova, le tariffe no',
        'demo_text'  => 'I prezzi che vedi sono quelli della casa, e il totale è calcolato su quelli. '
                      . 'Quali date risultino libere, invece, lo decide un provider di prova incluso nel '
                      . 'sito e non un calendario reale — e la richiesta non raggiunge ancora nessuna '
                      . 'casella di posta.',

        'steps' => [
            'dates'   => 'Date e ospiti',
            'rooms'   => 'Camere libere',
            'details' => 'I tuoi dati',
            'done'    => 'Richiesta inviata',
        ],
        'step_of' => 'Passo :current di :total',

        'search' => [
            'legend'    => 'Cerca la disponibilità',
            'arrival'   => 'Arrivo',
            'departure' => 'Partenza',
            'guests'    => 'Ospiti',
            'submit'    => 'Verifica disponibilità',
            'note'      => 'Soggiorno minimo :nights notti. Tassa di soggiorno esclusa, da confermare.',
            'note_no_min' => 'Il prezzo dipende da quante persone dormono in camera. Tassa di '
                           . 'soggiorno di 3 € a persona per notte, per le prime tre notti, da pagare '
                           . 'al check-in.',
            'note_stay' => ':nights notti · :guests',
        ],

        'results' => [
            'title'      => 'Le camere libere',
            'for_dates'  => 'Dal :from al :to, :guests.',
            'none_title' => 'Queste date non sono libere',
            'none_text'  => 'Nessuna delle cinque camere è disponibile per il periodo che hai chiesto. '
                          . 'Prova a spostarti di qualche giorno, oppure scrivi a Daniele: a volte si libera '
                          . 'qualcosa che il calendario non ha ancora registrato.',
            'nearest'    => 'Le date libere più vicine:',
            'too_many_title' => 'Non abbiamo una camera per :guests persone',
            'too_many_text'  => 'La camera più grande ne ospita :max. Per un gruppo più numeroso servono '
                              . 'due camere: scrivici con le date e Daniele ti dice cosa si riesce a fare.',
            'choose'     => 'Scegli questa camera',
            'total'      => 'Totale :nights notti',
            'per_night'  => ':amount a notte',
            'change'     => 'Cambia le date',
            'unavailable_room' => 'Non libera in queste date',
        ],

        'details' => [
            'title'     => 'I tuoi dati',
            'summary'   => 'Riepilogo',
            'legend'    => 'Chi arriva',
            'first_name' => 'Nome',
            'last_name' => 'Cognome',
            'email'     => 'E-mail',
            'phone'     => 'Telefono',
            'country'   => 'Paese di residenza',
            'notes'     => 'Qualcosa che dobbiamo sapere',
            'notes_help' => 'Orario di arrivo previsto, un letto in più, un’allergia. Scrivilo qui.',
            'privacy'   => 'Ho letto come vengono trattati i miei dati.',
            'submit'    => 'Invia la richiesta',
            'change_room' => 'Cambia camera',
        ],

        'done' => [
            'title'     => 'La richiesta è partita',
            'text'      => 'Daniele l’ha ricevuta e ti risponde lui. Qui sotto c’è il riepilogo di quello '
                         . 'che hai chiesto: conviene tenerlo.',
            'reference' => 'Riferimento',
            'next'      => 'Che cosa succede adesso',
            'next_text' => 'La richiesta non è ancora una prenotazione confermata: lo diventa quando Daniele '
                         . 'risponde che la camera è tenuta per te.',
            'demo_note' => 'Siamo in un prototipo: nessuna e-mail è stata spedita davvero. Il messaggio è '
                         . 'stato scritto nel registro del sito.',
            'home'      => 'Torna alla home',
        ],

        'summary' => [
            'room'      => 'Camera',
            'dates'     => 'Date',
            'nights'    => 'Notti',
            'guests'    => 'Ospiti',
            'rate'      => 'Tariffa a notte',
            'total'     => 'Totale indicativo',
            'total_note' => 'Il totale definitivo lo conferma Daniele.',
        'city_tax'   => 'Tassa di soggiorno',
        'city_tax_note' => ':amount a persona per notte, per le prime :nights notti. Esenti i minori '
                         . 'di :age anni. Si paga al check-in insieme al saldo.',
        'city_tax_upto' => 'fino a :amount',
        ],
    ],

    // ---------------------------------------------------------- Contatti
    'contact' => [
        'seo_title'       => 'Contatti — Arco del Vento, Assisi',
        'seo_description' => 'Scrivi ad Arco del Vento, affittacamere in Via Santa Maria delle Rose, ad '
                           . 'Assisi. Risponde Daniele Pecetta.',
        'eyebrow' => 'CONTATTI',
        'title'   => 'Scrivi, risponde una',
        'sign'    => 'persona',
        'lead'    => 'Non c’è un centralino e non c’è un modulo che smista: quello che scrivi lo legge '
                   . 'Daniele.',

        'where_title' => 'Dove siamo',
        'how_title'   => 'Come raggiungerci',
        'form_title'  => 'Scrivici',

        'form' => [
            'legend'     => 'Il tuo messaggio',
            'name'       => 'Nome',
            'email'      => 'E-mail',
            'phone'      => 'Telefono',
            'subject'    => 'Oggetto',
            'subjects'   => [
                'info'      => 'Una informazione',
                'booking'   => 'Una prenotazione',
                'arrival'   => 'Il mio arrivo',
                'other'     => 'Altro',
            ],
            'message'      => 'Messaggio',
            'message_help' => 'Le date che hai in mente, quante persone siete, quello che ti serve sapere.',
            'privacy'      => 'Ho letto come vengono trattati i miei dati.',
            'submit'       => 'Invia il messaggio',
        ],

        'success_title' => 'Messaggio inviato',
        'success_text'  => 'Grazie: il messaggio è arrivato. Ti risponde Daniele.',
        'demo_note'     => 'Siamo in un prototipo: nessuna e-mail è stata spedita davvero. Il messaggio è '
                         . 'stato scritto nel registro del sito.',
        'error_title'   => 'Il modulo non è partito',
        'error_text'    => 'Controlla i campi segnati qui sotto e riprova.',
    ],

    // ---------------------------------------------------------- Privacy
    'privacy' => [
        'seo_title'       => 'Privacy — Arco del Vento',
        'seo_description' => 'Quali dati raccoglie questo sito, perché, per quanto tempo e come si '
                           . 'chiede di cancellarli.',
        'eyebrow' => 'PRIVACY',
        'title'   => 'Che cosa sappiamo di',
        'sign'    => 'te',
        'lead'    => 'Poco, e solo quello che serve per rispondere. Questa pagina lo dice per intero: '
                   . 'senza di essa le spunte nei moduli non vogliono dire niente.',

        'blocks' => [
            'who' => [
                'title' => 'Chi tratta i dati',
                'text'  => 'Il titolare del trattamento è Arco del Vento di Pecetta Daniele, Via Santa '
                         . 'Maria delle Rose 1/A, Assisi. L’indirizzo a cui scrivere per le questioni di '
                         . 'privacy, che può essere diverso da quello generale, va indicato qui.',
            ],
            'what' => [
                'title' => 'Che cosa raccogliamo',
                'text'  => 'Dal modulo dei contatti: nome, e-mail, eventuale telefono e il testo del '
                         . 'messaggio. Dalla richiesta di prenotazione: nome e cognome, e-mail, eventuale '
                         . 'telefono, paese di residenza, date, camera e le note che scrivi. Nient’altro: '
                         . 'non ci sono campi nascosti che raccolgono altro.',
            ],
            'why' => [
                'title' => 'Perché',
                'text'  => 'Per risponderti e per gestire la richiesta di soggiorno. Non usiamo i tuoi '
                         . 'dati per mandarti pubblicità, non li vendiamo e non li passiamo a terzi, '
                         . 'salvo quanto serve a far funzionare il sito e la posta.',
            ],
            'how_long' => [
                'title' => 'Per quanto tempo',
                'text'  => 'Il periodo di conservazione dei messaggi e delle richieste va deciso e '
                         . 'scritto qui. Per gli obblighi fiscali e di registrazione degli ospiti valgono '
                         . 'i termini di legge, che sono più lunghi.',
            ],
            'cookies' => [
                'title' => 'Cookie e statistiche',
                'text'  => 'Questo sito usa un solo cookie, tecnico: tiene la sessione aperta mentre '
                         . 'compili un modulo e serve a proteggerlo dagli invii falsificati. Non ci sono '
                         . 'cookie di profilazione, non c’è Google Analytics, non ci sono pixel di '
                         . 'piattaforme pubblicitarie. I caratteri tipografici e le immagini sono serviti '
                         . 'dal nostro server, quindi nessun dominio terzo vede il tuo indirizzo IP '
                         . 'mentre leggi. Per questo non c’è una finestra di consenso: non ci sarebbe '
                         . 'niente da consentire.',
            ],
            'rights' => [
                'title' => 'I tuoi diritti',
                'text'  => 'Puoi chiedere di vedere i dati che ci hai dato, di correggerli o di '
                         . 'cancellarli, e puoi opporti al trattamento. Basta scrivere all’indirizzo qui '
                         . 'sopra. Se la risposta non ti soddisfa, puoi rivolgerti al Garante per la '
                         . 'protezione dei dati personali.',
            ],
        ],

        'notice_title' => 'Testo da completare',
        'notice_text'  => 'Questa informativa è impostata ma non è un documento legale finito: i punti '
                        . 'segnati vanno completati dal titolare, e conviene farli leggere a chi tiene '
                        . 'la contabilità o a un legale prima della pubblicazione.',
    ],

    // ------------------------------------------------------------- Errori
    'errors' => [
        'required'   => 'Questo campo serve.',
        'email'      => 'Manca la chiocciola o il dominio: controlla l’indirizzo.',
        'too_short'  => 'Scrivi qualche parola in più.',
        'too_long'   => 'Troppo lungo: accorcia il testo.',
        'date'       => 'Questa data non esiste. Usa il calendario del telefono.',
        'range'      => 'Il numero che hai messo non va bene.',
        'accepted'   => 'Serve la spunta per poter procedere.',
        'past'       => 'La data di arrivo è già passata.',
        'order'      => 'La partenza deve venire dopo l’arrivo.',
        'min_nights'   => 'Il soggiorno minimo è di :nights notti.',
        'saturday_min' => 'Il sabato non si prenota da solo: con una notte di sabato il soggiorno '
                        . 'è di almeno :nights notti. Puoi fare venerdì e sabato, oppure sabato e domenica.',
        'max_stay'   => 'Per soggiorni più lunghi di :nights notti scrivici: si concorda a parte.',
        'token'      => 'La pagina è rimasta aperta troppo a lungo. Ricaricala e riprova.',
        'room'       => 'Scegli una delle camere libere.',
        'no_session' => 'Il percorso si è interrotto. Ricominciamo dalle date.',
    ],

    'not_found' => [
        'seo_title' => 'Pagina non trovata — Arco del Vento',
        'eyebrow'   => 'ERRORE 404',
        'title'     => 'Questa pagina non',
        'sign'      => 'esiste',
        'lead'      => 'L’indirizzo che hai seguito non porta da nessuna parte. Le cinque camere, le '
                     . 'informazioni pratiche e i contatti sono tutti a un clic da qui.',
    ],

    // ------------------------------------------------------------- Piede
    'footer' => [
        'where'      => 'DOVE SIAMO',
        'contacts'   => 'CONTATTI',
        'stay'       => 'IL SOGGIORNO',
        'pages'      => 'PAGINE',
        'legal_note' => 'I dati identificativi obbligatori per una struttura ricettiva — CIN, partita IVA — '
                      . 'vanno pubblicati qui.',
        'credits'    => 'Prototipo. Contenuti e fotografie delle camere da fornire.',
        'photo_credits' => 'Fotografie della casa e delle camere: Arco del Vento. '
                         . 'Vedute di Assisi: Niels Baars, Gary Walker-Jones, Alessandro Guarino (Unsplash).',
        'rights'     => '© :year Arco del Vento di Pecetta Daniele',
    ],

    'theme' => [
        'label'  => 'Tema',
        'avorio' => 'Chiaro',
        'notte'  => 'Notte',
    ],
];
