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
        'topbar' => [
            'center'  => 'Nel centro storico di Assisi',
            'host'    => 'Ti accoglie il titolare',
            'parking' => 'Parcheggio a :metres m',
            'since'   => 'Aperto dal 2002',
        ],
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
        'all_rooms'    => 'Scopri le camere',
        'see_assisi'   => 'Cosa vedere a piedi',
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

        // La riga sotto la barra di prenotazione.
        'trust' => [
            'Prenotazione diretta',
            'Nessuna commissione di piattaforma',
            'Ti risponde Daniele',
        ],

        // «|» nei titoli e nei testi brevi è un a capo.
        'hero' => [
            'eyebrow' => 'AFFITTACAMERE DAL 2002',
            'title'   => 'Cinque camere,|una casa di',
            'sign'    => 'Assisi.',
            'lead'    => 'Nel centro storico, accanto a San Rufino.|Cinque stanze tenute di persona da Daniele.',
            'place'   => 'Assisi, Umbria',
            'scroll'  => 'Vai alle camere',
            'image_alt' => 'Un vicolo in salita del centro di Assisi, con un campanile in pietra sullo sfondo.',
            'image_credit' => 'Assisi, centro storico — fotografia Niels Baars, Unsplash',
        ],

        'slides' => [
            'label'     => 'Fotografie di Assisi e della casa',
            'prev'      => 'Fotografia precedente',
            'next'      => 'Fotografia successiva',
            'pause'     => 'Ferma le fotografie',
            'play'      => 'Riprendi le fotografie',
            'valle'     => 'LA VALLE, VISTA DA ASSISI.',
            'vicolo'    => 'IL CENTRO STORICO, A PIEDI.',
            'basilica'  => 'SAN FRANCESCO, AL TRAMONTO.',
            'corridoio' => 'LA ROSA DEI VENTI, NEL CORRIDOIO DI CASA.',
            'camera'    => 'UNA DELLE CINQUE CAMERE.',
        ],

        'manifesto' => [
            'text'   => 'Cinque camere.|Una sola persona a darti le chiavi,',
            'sign'   => 'dal 2002.',
            'author' => 'AFFITTACAMERE|NEL CENTRO DI ASSISI',
        ],

        'rooms' => [
            'eyebrow'  => 'LE CAMERE DELLA CASA',
            'title'    => 'Le cinque camere|della',
            'sign'     => 'casa.',
            'lead'     => 'Cinque camere con bagno privato, affacciate su Piazza San Rufino o sui vicoli '
                        . 'di Assisi. Matrimoniali, doppie e una tripla, per viaggiare in due o in famiglia.',
            'discover' => 'Tutte le camere',
            'prev'     => 'Camere precedenti',
            'next'     => 'Camere successive',
            'tagline'  => 'CINQUE CAMERE, UNA SOLA CASA.',
        ],

        'position' => [
            'eyebrow'  => 'IL CENTRO STORICO, A PIEDI',
            'title'    => 'La città comincia|fuori dalla',
            'sign'     => 'porta.',
            'lead'     => 'La casa sta in Via Santa Maria delle Rose, nel centro storico. Da qui non serve '
                        . 'l’auto: tutto quello che si viene a vedere si raggiunge camminando.',
            'rufino_text' => 'San Rufino è la chiesa del quartiere ed è la cattedrale della città: nel suo '
                           . 'fonte battesimale sono stati battezzati Francesco e Chiara. È il riferimento '
                           . 'più vicino alla casa, e il primo campanile che si impara a riconoscere.',
            'chip'     => 'Il centro storico',
            'vertical' => 'ASSISI, UN PASSO ALLA VOLTA',
            'new_tab'  => 'su Google Maps, in una nuova scheda',
            'frame_alt'     => 'La valle umbra vista dall’alto, con i campi coltivati e un borgo in lontananza.',
        ],

        'reviews' => [
            'title'   => 'Chi è già stato qui',
            'booking' => 'Booking',
            'google'  => 'Google',
            'count'   => ':count recensioni su :platform',
        ],

        'host' => [
            'eyebrow' => 'CHI TIENE APERTO',
            'title'   => 'Una casa,|un padrone di',
            'sign'    => 'casa.',
            'lead'    => 'Qui non c’è una reception, c’è un padrone di casa. Daniele accoglie i suoi ospiti '
                       . 'dal 2002: ti consegna le chiavi, ti racconta Assisi e resta raggiungibile per '
                       . 'tutto il soggiorno.',
            'numbers' => [
                ['value' => '05',   'name' => 'CAMERE'],
                ['value' => '2002', 'name' => 'APERTO DAL'],
                ['value' => '01',   'name' => 'PERSONA A GESTIRLA'],
            ],
        ],

        'arrive' => [
            'eyebrow'         => 'COME ARRIVARE',
            'map'             => 'Apri in Google Maps',
            'map_label'       => 'Mappa: dove si trova la casa',
            'navigator_label' => 'Nel navigatore',
            'parking_label'   => 'Parcheggio',
            'parking_value'   => 'Piazza Matteotti, :metres m',
            'train_label'     => 'In treno',
            'train_value'     => 'Stazione di Assisi, poi l’autobus',
            'plane_label'     => 'In aereo',
            'plane_value'     => 'Aeroporto di Perugia, poi Airlink',
        ],

        'walk' => [
            'image_alt' => 'La Basilica di San Francesco al tramonto, con la facciata in pietra chiara illuminata di lato.',
            'image_credit' => 'Basilica di San Francesco — fotografia Alessandro Guarino, Unsplash',
        ],

        'faq' => [
            'eyebrow' => 'LE DOMANDE DI SEMPRE',
            'title'   => 'Quello che si chiede',
            'sign'    => 'prima.',
        ],

        'closing' => [
            'eyebrow' => 'PRENOTAZIONE DIRETTA',
            'title'   => 'Chiedi le date|che ti',
            'sign'    => 'servono.',
            'lead'    => 'La richiesta arriva direttamente a Daniele, senza intermediari e senza commissioni '
                       . 'di piattaforma. E la risposta arriva da Daniele.',
        ],
    ],

    // ---------------------------------------------------------------- Camere
    'rooms' => [
        'seo_title'       => 'Le cinque camere — Arco del Vento, Assisi',
        'seo_description' => 'Le cinque camere di Arco del Vento, affittacamere in Via Santa Maria delle Rose, '
                           . 'ad Assisi. Letti, occupazione, servizi e tariffe.',
        'eyebrow'   => 'LE CAMERE',
        'title'     => 'Cinque stanze,|una sola',
        'sign'      => 'casa.',
        'lead'      => 'Sono cinque e stanno tutte nello stesso edificio. Qui sotto trovi letti, occupazione '
                     . 'massima e servizi di ciascuna; la disponibilità si verifica con le date.',
        'image_alt' => 'Segnaposto: la fotografia della casa non è ancora disponibile.',
        'corridor_alt' => 'Il corridoio della casa, con il pavimento in parquet e una rosa dei venti '
                        . 'intarsiata nel legno; alle pareti, vedute di Assisi incorniciate.',
        'corridor_caption' => 'La rosa dei venti nel pavimento del corridoio. È la stessa del marchio, '
                            . 'ed era lì prima.',

        'list_title'    => 'Una per',
        'list_sign'     => 'una.',
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
        'card' => [
            'layouts'      => 'Configurazioni',
            'rate'         => 'Tariffa',
            'photo_soon'   => 'Fotografia in arrivo',
            'guests_one'   => '1 ospite',
            'guests_up_to' => 'Fino a :count ospiti',
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
        'vicoli'     => 'Sui vicoli di Assisi',
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
        'title'   => 'Una casa, cinque camere,|una',
        'sign'    => 'persona.',
        'lead'    => 'Arco del Vento è un affittacamere: cinque camere in una casa di Via Santa Maria delle '
                   . 'Rose, nel centro storico di Assisi, con l’accoglienza di chi la abita e la cura di '
                   . 'chi la apre ai suoi ospiti da più di vent’anni.',
        'image_alt' => 'Segnaposto: la fotografia della casa non è ancora disponibile.',

        'what_title' => 'Che cosa vuol dire affittacamere',
        'what_text'  => 'Vuol dire poche camere dentro una casa vera, e la stessa persona che ti apre la '
                      . 'porta, ti consiglia dove mangiare e si ricorda di te l’anno dopo. Quello che c’è '
                      . 'e quello che non c’è — la colazione, l’ascensore — lo trovi scritto con chiarezza '
                      . 'nelle informazioni pratiche.',

        'host_title' => 'Daniele',
        'host_text'  => 'Daniele Pecetta ha aperto Arco del Vento nel 2002 e da allora lo gestisce lui. '
                      . 'Risponde al telefono, consegna le chiavi, tiene in ordine le cinque stanze. '
                      . 'Quando scrivi a questo indirizzo, è lui che legge.',
        'host_role'  => 'TITOLARE · DAL 2002',
        'host_bio'   => 'Ha aperto l’affittacamere nel 2002 in Via Santa Maria delle Rose e da allora '
                      . 'accoglie di persona gli ospiti delle cinque camere.',

        'building_title' => 'L’edificio',
        'building_text'  => 'Le camere sono al secondo piano di una casa del centro storico, a due passi '
                          . 'dalla cattedrale di San Rufino: ci si arriva per due rampe di scale. Nel '
                          . 'corridoio, intarsiata nel parquet, c’è una rosa dei venti: era lì prima di noi, '
                          . 'ed è diventata il segno di Arco del Vento.',

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
        'title'   => 'Tutto quello che c’è,|senza salire in',
        'sign'    => 'auto.',
        'lead'    => 'La casa è dentro il centro storico di Assisi: da lì si va a piedi, in salita e in '
                   . 'discesa, e la distanza si misura in minuti, non in chilometri.',
        'image_alt' => 'Un vicolo in salita del centro di Assisi, con un campanile in pietra sullo sfondo.',

        'places_title' => 'I sei posti che si raggiungono camminando',
        'places_note'  => 'Tempi indicativi a passo tranquillo, dalla porta di casa.',
        'walk_label'   => 'a piedi',

        'rufino_title' => 'Perché San Rufino viene prima',
        'rufino_text'  => 'Perché è la cattedrale della città e la chiesa di questo quartiere, e perché è '
                        . 'la meno affollata delle tre grandi: si entra, si guarda il fonte battesimale dove '
                        . 'sono stati battezzati Francesco e Chiara, e si esce in una piazza dove ci si può '
                        . 'ancora sedere.',

        'moving_title' => 'Muoversi',
        'moving_text'  => 'Il centro storico si visita interamente a piedi. In auto si parcheggia in Piazza '
                        . 'Matteotti, a pagamento, a circa 200 metri dalla casa. In treno si scende alla '
                        . 'stazione di Assisi, a Santa Maria degli Angeli, e si sale in centro con l’autobus '
                        . 'fino a Piazza Matteotti, oppure in taxi.',
    ],

    'places' => [
        'san-rufino'   => ['name' => 'Cattedrale di San Rufino',   'note' => 'la cattedrale, a pochi passi da casa'],
        'santa-chiara' => ['name' => 'Basilica di Santa Chiara',    'note' => 'in fondo alla discesa, verso sud'],
        'comune'       => ['name' => 'Piazza del Comune',           'note' => 'il tempio di Minerva e i caffè'],
        'spoliazione'  => ['name' => 'Santuario della Spoliazione',  'note' => 'accanto al vescovado'],
        'san-francesco' => ['name' => 'Basilica di San Francesco',  'note' => 'all’estremità opposta del centro'],
        'carceri'      => ['name' => 'Eremo delle Carceri',         'note' => 'sul Subasio, circa 4 km in salita',
                           'time' => 'oltre un’ora a piedi, 10 minuti in taxi'],
    ],

    // ------------------------------------------------------- Informazioni
    'info' => [
        'seo_title'       => 'Informazioni pratiche — Arco del Vento, Assisi',
        'seo_description' => 'Check-in, arrivo, parcheggio, scale, Wi-Fi, animali e regole della casa. '
                           . 'Le informazioni pratiche di Arco del Vento, affittacamere ad Assisi.',
        'eyebrow' => 'INFORMAZIONI',
        'title'   => 'Quello che significa|stare',
        'sign'    => 'qui.',
        'lead'    => 'Tutto quello che serve sapere prima di partire: orari, arrivo, parcheggio e '
                   . 'regole della casa.',

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
            'payment'       => 'Pagamento',
            'cancellation'  => 'Disdetta',
            'languages'     => 'Lingue parlate',
            'accessibility' => 'Accessibilità',
        ],

        'known' => [
            'welcome'       => 'Daniele, di persona: consegna le chiavi e mostra la casa.',
            'documents'     => 'Al check-in, oppure mandali prima su WhatsApp e facciamo più in fretta.',
            'min_nights'    => 'Un soggiorno che comprende un sabato dura almeno :nights notti.',
            'payment_online' => 'Online, con carta, al momento della prenotazione: si paga l’intero soggiorno su una pagina sicura di SumUp. Nessuna caparra.',
            'children'      => 'Benvenuti. Sotto i :age anni non si paga la tassa di soggiorno.',
            'navigator'     => 'Imposta :place, non l’indirizzo: la via è senza uscita. A piedi l’indirizzo preciso funziona.',
            'car'           => 'In auto non si arriva fino alla porta: si lascia la macchina e si fa l’ultimo tratto a piedi.',
            'train'         => 'Stazione di Assisi, poi l’autobus fino a Piazza Matteotti, oppure il taxi.',
            'plane'         => 'Aeroporto di Perugia, poi il bus Umbria Airlink fino a Piazza Matteotti.',
            'taxi'          => 'Fatti portare a :place.',
            'links_title'   => 'Orari e tariffe aggiornati',
            'link_parking'  => 'Parcheggio Piazza Matteotti — tariffe e orari',
            'link_bus'      => 'Autobus urbani di Assisi — linee e orari',
            'link_airlink'  => 'Umbria Airlink — dall’aeroporto di Perugia',
            'new_tab'       => 'si apre in una nuova scheda',
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
        'title'   => 'Le date, e poi|tutto il',
        'sign'    => 'resto.',
        'lead'    => 'Quattro passaggi: le date, la camera, i tuoi dati, la richiesta. Non si paga nulla '
                   . 'online e non serve un account.',
        // Con il pagamento online acceso, al posto delle due righe sopra.
        'lead_pay' => 'Quattro passaggi: le date, la camera, nome ed e-mail, il pagamento su una pagina '
                    . 'sicura di SumUp. Non serve un account.',
        'seo_description_pay' => 'Verifica le date, scegli la camera e prenota pagando online con SumUp: '
                               . 'Arco del Vento, affittacamere in Via Santa Maria delle Rose, ad Assisi.',

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

        // Il pagamento online (PAYMENT_PROVIDER=sumup): si chiedono solo nome
        // ed e-mail, e si paga su una pagina di SumUp.
        'pay' => [
            'legend'        => 'A nome di chi',
            'name'          => 'Nome e cognome',
            'email'         => 'E-mail',
            'email_help'    => 'Qui arriva la conferma della prenotazione.',
            'accept'        => 'Ho letto le condizioni qui accanto e come vengono trattati i miei dati:',
            'submit'        => 'Paga :amount con SumUp',
            'secure'        => 'Il pagamento avviene su una pagina sicura di SumUp: i dati della carta non passano da questo sito.',
            'conditions'    => 'Condizioni',
            'full_amount'   => 'Si paga adesso l’intero soggiorno.',
            'city_tax'      => 'Tassa di soggiorno esclusa: € :amount a persona per notte, per le prime :nights notti, esenti sotto i :age anni. Si paga all’arrivo.',
            'cancellation'  => 'Cancellazione',
            'going_title'   => 'Ti portiamo al pagamento',
            'going_text'    => 'Stai per passare alla pagina di SumUp per pagare :amount. Se non si apre da sola, usa il pulsante.',
            'going_button'  => 'Vai al pagamento',
            'going_note'    => 'La pagina resta valida trenta minuti. Finché è aperta, la camera è tenuta per te.',
            'paid_title'    => 'Pagamento ricevuto: la camera è prenotata',
            'paid_text'     => 'La conferma è partita per e-mail. Qui sotto c’è il riepilogo: conviene tenerlo.',
            'pending_title' => 'Il pagamento non risulta ancora completato',
            'pending_text'  => 'Se hai appena pagato, aspetta qualche secondo e aggiorna la pagina. Se hai chiuso la pagina di SumUp prima di pagare, puoi riprovare.',
            'failed_title'  => 'Il pagamento non è andato a buon fine',
            'failed_text'   => 'Non è stato addebitato niente. Puoi riprovare, anche con un’altra carta.',
            'expired_title' => 'La pagina di pagamento è scaduta',
            'expired_text'  => 'La pagina di SumUp resta aperta trenta minuti. Non è stato addebitato niente: puoi riprovare.',
            'check_title'   => 'Pagamento ricevuto, da verificare',
            'check_text'    => 'L’importo ricevuto non coincide con quello della prenotazione. Ti scriviamo noi per sistemare.',
            'unknown_title' => 'Prenotazione non trovata',
            'unknown_text'  => 'Il riferimento non corrisponde a nessuna prenotazione. Se hai pagato, scrivici: :email.',
            'retry'         => 'Riprova il pagamento',
            'reload'        => 'Aggiorna la pagina',
            'taken_title'   => 'La camera non è più libera',
            'error_title'   => 'Il pagamento non è partito',
            'taken'         => 'Nel frattempo questa camera è stata prenotata per le stesse date. Non è stato addebitato niente: scegli un’altra camera o altre date.',
            'error'         => 'Il pagamento non si è potuto avviare. Riprova fra qualche minuto, oppure scrivici: :email.',
            'paid_amount'   => 'Pagato',
            'step'          => 'Pagamento',
        ],

        // L'e-mail di conferma all'ospite, nella sua lingua.
        'mail' => [
            'subject'   => 'Prenotazione confermata — Arco del Vento (:ref)',
            'hello'     => 'Buongiorno :name,',
            'intro'     => 'grazie: il pagamento è andato a buon fine e la camera è prenotata.',
            'reference' => 'Riferimento',
            'room'      => 'Camera',
            'arrival'   => 'Arrivo',
            'departure' => 'Partenza',
            'nights'    => 'Notti',
            'guests'    => 'Ospiti',
            'paid'      => 'Pagato',
            'check_in'  => 'check-in dalle :from alle :to',
            'check_out' => 'check-out entro le :time',
            'address'   => 'Indirizzo',
            'contact'   => 'Per qualsiasi cosa rispondi a questa e-mail, oppure chiama il :phone.',
            'sign'      => 'Arco del Vento, Assisi',
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
        // Con il pagamento online il totale è quello che si paga, per intero.
        'total_pay'  => 'Totale da pagare',
        'total_paid' => 'Totale pagato',
        'city_tax_note_pay' => ':amount a persona per notte, per le prime :nights notti. Esenti i minori '
                             . 'di :age anni. Non è compresa: si paga al check-in.',
        ],
    ],

    // ---------------------------------------------------------- Contatti
    'contact' => [
        'seo_title'       => 'Contatti — Arco del Vento, Assisi',
        'seo_description' => 'Scrivi ad Arco del Vento, affittacamere in Via Santa Maria delle Rose, ad '
                           . 'Assisi. Risponde Daniele Pecetta.',
        'eyebrow' => 'CONTATTI',
        'title'   => 'Scrivi,|risponde una',
        'sign'    => 'persona.',
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
    // La pagina del codice d'accesso, finché il sito è in anteprima.
    'gate' => [
        'page_title' => 'Arco del Vento — anteprima',
        'eyebrow'    => 'Anteprima',
        'title'      => 'Il sito sta per aprire',
        'lead'       => 'Per vederlo adesso serve il codice d’accesso.',
        'label'      => 'Codice d’accesso',
        'submit'     => 'Entra',
        'note'       => 'Su questo browser il codice vale trenta giorni.',
        'wrong'      => 'Il codice non è giusto. Controlla e riprova.',
        'empty'      => 'Scrivi il codice d’accesso.',
        'too_many'   => 'Troppi tentativi. Riprova fra un quarto d’ora.',
        'expired'    => 'La pagina era rimasta aperta a lungo: scrivi di nuovo il codice.',
        'other_lang' => 'English',
    ],

    'privacy' => [
        'seo_title'       => 'Privacy — Arco del Vento',
        'seo_description' => 'Quali dati raccoglie questo sito, perché, per quanto tempo e come si '
                           . 'chiede di cancellarli.',
        'eyebrow' => 'PRIVACY',
        'title'   => 'Che cosa|sappiamo di',
        'sign'    => 'te.',
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
            'payment' => [
                'title' => 'Il pagamento',
                'text'  => 'Se paghi la prenotazione online, paghi su una pagina di SumUp, che tratta i dati '
                         . 'della carta secondo la propria informativa. Questo sito non vede e non conserva i '
                         . 'dati della carta: da SumUp riceve solo l’esito, l’importo e il codice della '
                         . 'transazione, e li tiene con la prenotazione.',
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
                         . 'dal nostro server. L’unica eccezione è la mappa della home: le sue immagini '
                         . 'arrivano da OpenStreetMap, che per mostrarle riceve il tuo indirizzo IP, senza '
                         . 'cookie. Per questo non c’è una finestra di consenso: non ci sarebbe niente da '
                         . 'consentire.',
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
        'pay_limit'  => 'Troppe pagine di pagamento aperte da questa connessione. Riprova fra un’ora, oppure scrivici: :email.',
        'pay_error'  => 'Il pagamento non si è potuto avviare. Riprova fra qualche minuto, oppure scrivici: :email.',
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
        'title'     => 'Questa pagina|non',
        'sign'      => 'esiste.',
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
        'tagline'    => 'Cinque camere nel centro storico.|Una persona a tenerle aperte.',
        'back_top'   => 'Torna su',
    ],

];
