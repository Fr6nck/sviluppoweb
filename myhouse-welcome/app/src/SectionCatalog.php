<?php
namespace MHW;

/**
 * Il catalogo delle sezioni. Ogni tipo dichiara i suoi campi, e da qui nascono
 * sia l'editor dell'host sia la pagina dell'ospite: niente mini-sintassi da
 * imparare, niente dati strutturati nascosti dentro un campo di testo.
 *
 * Tipi di campo:
 *   steps     — passaggi numerati, da tradurre
 *   list      — elenco di voci, da tradurre
 *   text      — una riga, da tradurre
 *   textarea  — un paragrafo, da tradurre
 *   plain     — una riga uguale in ogni lingua (nome rete, indirizzo)
 *   url       — un indirizzo web, uguale in ogni lingua
 *   secret    — come plain, ma si copia con un tocco (password Wi-Fi)
 *   choice    — una scelta tra opzioni fisse, uguale in ogni lingua (etichette nei file lang)
 *   repeater  — righe con sottocampi (più reti Wi-Fi, più contatti…). Ogni riga ha
 *               un id stabile: i sottocampi uguali in ogni lingua stanno in
 *               sections.data[campo], quelli da tradurre in section_translations.data[campo],
 *               e le due parti si uniscono per id. Così riordinare o togliere una riga
 *               non mescola le traduzioni.
 *               Sottotipi: text, textarea (tradotti); plain, secret, url, tel, time,
 *               choice, days, check, image, pdf, money, date (uguali in ogni lingua; image e pdf
 *               sono id di media, caricati riga per riga; money è un importo in euro,
 *               salvato con la virgola: «1,50»).
 *   checks    — più spunte da un elenco fisso (le dotazioni), uguale in ogni lingua
 *   toggles   — interruttori sì / no / non indicato (fumo, animali…), uguale in ogni lingua
 *   time      — un orario (hh:mm), uguale in ogni lingua
 *
 * Nei repeater ogni sottocampo può dire quanto è largo nella riga: 'w' => 2|3|4|5|6|8|12
 * colonne su 12 (predefinito 6; textarea, foto, PDF e giorni 12). 'item' è il nome
 * di una riga («Parcheggio 1»). Un campo con 'se' => altro_campo si vede solo con
 * quell'interruttore acceso, e al salvataggio si svuota se è spento.
 * Nelle righe: 'pillole' => true mostra una scelta come pillole; 'nascosto_con' =>
 * [campo, [valori]] nasconde il sottocampo (e lo svuota al salvataggio) quando l'altro
 * campo della riga ha uno di quei valori; 'cifre' => true accetta solo un numero intero.
 * Fase 6G: 'solo_con' => [campo, [valori]] mostra il sottocampo solo con quei valori (non lo
 * svuota); 'aiuto_con' => [campo, [valori], testo] un aiuto che compare solo con quei valori;
 * 'etichetta_se' => [campo, [valore => etichetta]] cambia l'etichetta; 'default' vale per la
 * riga nuova; 'locandina' => sottocampo PDF: una zona sola per immagine o PDF ('nascosto' su
 * quel sottocampo: non si disegna da solo). Si mostrano e nascondono con cms.js; senza
 * JavaScript tutto resta visibile.
 *
 * 'group' (fase 6C) dice in quale gruppo sta la sezione nella home: casa, arrivo, territorio.
 *
 * Ogni sezione ha due testi per l'host, scritti tutti allo stesso modo:
 *   'breve' — una riga sotto il nome, nel catalogo «Aggiungi una sezione»: che cosa contiene;
 *   'intro' — il riquadro sotto il titolo, nell'editor: prima a cosa serve all'ospite, poi come
 *             compilarla o che cosa va invece in un'altra sezione. Due frasi, tre al massimo.
 * Negli aiuti dei campi, «Facoltativo.» in testa diventa «(facoltativo)» accanto all'etichetta
 * (vedi facoltativo()): l'aiuto resta per l'esempio.
 *
 * `places: true` vuol dire che la sezione contiene schede di luoghi.
 * Check-in & Check-out è il nucleo: c'è sempre, non si disattiva e non conta
 * nel limite delle sezioni del piano.
 */
final class SectionCatalog
{
    private const K = [
        'checkin' => [
            'icon' => 'home', 'core' => true, 'group' => 'casa',
            'intro' => 'Come si entra, cosa serve all\'arrivo e cosa fare prima di partire: è la sezione che l\'ospite apre per prima, ed è sempre inclusa. Non scrivere qui i codici di porte o cassette delle chiavi: mandali all\'ospite in privato.',
            // La partenza era fatta di cinque caselle fisse (checkout_keys, _waste, _lights,
            // _climate, _windows): dalla 009 è una lista ordinabile. I vecchi campi restano
            // nel JSON ma non si leggono più (Conversione::sezione li porta nella lista).
            'fields' => [
                'arrival_mode'    => ['choice', 'Come si entra', '', 'options' => ['self' => 'Self check-in', 'accoglienza' => 'Ti accolgo io', 'cassetta' => 'Cassetta delle chiavi']],
                'checkin_steps'   => ['steps', 'Passaggi per entrare', 'Un passaggio per riga: dove sono le chiavi, come si apre il portone, a che piano si sale.'],
                'late_arrival'    => ['textarea', 'Arrivo tardivo', 'Facoltativo. Cosa fare se si arriva tardi, per esempio dopo le 21.'],
                'documents'       => ['textarea', 'Documenti da mostrare', 'Facoltativo. Per esempio: un documento d\'identità per ogni ospite, per la registrazione obbligatoria.'],
                'tax_amount'      => ['plain', 'Imposta di soggiorno — importo per notte', 'Facoltativo. Per esempio: 2,00 € a persona.'],
                'tax_max_nights'  => ['plain', 'Imposta di soggiorno — per quante notti al massimo', 'Facoltativo. Per esempio: 5.', 'cifre' => true],
                'tax_notes'       => ['textarea', 'Imposta di soggiorno — esenzioni e pagamento', 'Facoltativo. Per esempio: sotto i 14 anni esenti; in contanti all\'arrivo.'],
                'checkin_note'    => ['textarea', 'Nota importante', 'Facoltativa. Nella guida compare in evidenza, in un riquadro.'],
                'checkout_steps'  => ['steps', 'Prima di partire', 'Una voce per riga. Tocca un suggerimento per aggiungerlo, poi modificalo come preferisci.',
                                      'suggest' => ['keys', 'waste', 'lights', 'climate', 'windows', 'dishwasher', 'towels']],
                'checkout_notes'  => ['textarea', 'Saluto finale', 'Facoltativo. Un grazie, un\'ultima raccomandazione: l\'ospite lo legge quando sta per partire.'],
            ],
        ],
        'wifi' => [
            'icon' => 'wifi', 'group' => 'casa',
            'breve' => 'Nome della rete e password, da copiare con un tocco.',
            'intro' => 'Il nome della rete e la password: l\'ospite la copia con un tocco, senza chiedertela. Se hai più reti, aggiungi una riga per ognuna e indica la zona che copre.',
            // Più reti (dalla 010): una riga per rete. La rete singola di prima
            // (network, password) diventa la prima riga.
            'fields' => [
                'networks'        => ['repeater', 'Reti Wi-Fi', 'Una riga per rete: 2,4 e 5 GHz, piano di sopra, dependance…',
                                      'add' => 'Aggiungi una rete', 'item' => 'Rete', 'max' => 8, 'sub' => [
                                          'ssid'     => ['plain', 'Nome della rete', '', 'w' => 6],
                                          'password' => ['secret', 'Password', '', 'w' => 6],
                                          // La zona serve solo con due o più reti.
                                          'zone'     => ['text', 'Zona', 'Facoltativa. Per esempio: Casa principale, Dependance.', 'w' => 12, 'solo_piu' => true],
                                      ]],
                'instructions'    => ['textarea', 'Istruzioni', 'Facoltative. Cosa fare se la rete non si vede.'],
                'router_location' => ['text', 'Dove si trova il router', 'Facoltativo. Serve se bisogna spegnerlo e riaccenderlo.'],
            ],
        ],
        // Servizi (dalla 011): dotazioni da spuntare e istruzioni con foto e PDF. La vecchia
        // lista resta com'era, come «Altre dotazioni».
        'services' => [
            'icon' => 'washer', 'group' => 'casa',
            'breve' => 'Le dotazioni e le istruzioni per usarle.',
            'intro' => 'Quello che gli ospiti trovano nella struttura e come si usa: spunta le dotazioni e aggiungi le istruzioni per caldaia, lavatrice, piano cottura. I servizi che offri a parte, di solito a pagamento, vanno in «Servizi extra».',
            'fields' => [
                // Le opzioni arrivano da Tassonomie::DOTAZIONI (fase 6), con le etichette amen_<chiave>.
                'amenities' => ['checks', 'Dotazioni', 'Spunta quello che gli ospiti trovano nella struttura.', 'options' => [], 'tassonomia' => 'dotazioni'],
                // Le dotazioni scritte a mano: nella guida stanno nello stesso elenco di quelle spuntate.
                'items' => ['list', 'Altre dotazioni', 'Quelle che non trovi nell\'elenco qui sopra: nella guida compaiono insieme alle altre.', 'pillole' => true, 'add' => 'Aggiungi una dotazione'],
                'manuals' => ['repeater', 'Istruzioni', 'Una riga per ogni cosa da spiegare: come si accende la caldaia, come funziona la lavatrice. Titolo, passaggi, una foto, un PDF.',
                              'add' => 'Aggiungi un\'istruzione', 'item' => 'Istruzione', 'max' => 10, 'sub' => [
                                  'title' => ['text', 'Titolo', 'Per esempio: La caldaia.', 'w' => 12],
                                  'steps' => ['textarea', 'Passaggi', 'Un passaggio per riga.', 'lines' => true],
                                  'photo' => ['image', 'Foto', 'Facoltativa.'],
                                  'pdf'   => ['pdf', 'PDF', 'Facoltativo. Per esempio: il manuale.'],
                              ]],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        // Servizi extra (fase 5): quello che l'host vende in più — transfer, colazione,
        // late check-out. Ogni riga ha «Richiedi su WhatsApp» nella guida.
        'extras' => [
            'icon' => 'euro', 'group' => 'casa',
            'breve' => 'Transfer, colazione, late check-out: i servizi a richiesta.',
            'intro' => 'I servizi che offri in più, di solito a pagamento: transfer, colazione, late check-out. Nella guida ogni servizio ha il pulsante «Richiedi su WhatsApp»: perché compaia, in Impostazioni serve un contatto che risponde su WhatsApp.',
            'fields' => [
                // Il prezzo (fase 6C): importo e unità uguali in ogni lingua, più una nota tradotta.
                // Il vecchio «price» scritto a mano resta nel JSON (Conversione::prezziExtra).
                'items' => ['repeater', 'Servizi extra', 'Una riga per servizio: titolo, prezzo, due righe di descrizione, una foto.',
                            'add' => 'Aggiungi un servizio', 'item' => 'Servizio', 'max' => 12, 'sub' => [
                                'title'       => ['text', 'Titolo', 'Per esempio: Transfer dalla stazione.', 'w' => 6],
                                'amount'      => ['money', 'Prezzo', 'Facoltativo.', 'w' => 2],
                                'unit'        => ['choice', 'Unità del prezzo', '', 'w' => 4, 'options' => [], 'tassonomia' => 'unita'],
                                'description' => ['textarea', 'Descrizione', 'Facoltativa.'],
                                'price_note'  => ['text', 'Nota sul prezzo', 'Facoltativa. Per esempio: gratis sotto i 3 anni.', 'w' => 12],
                                'photo'       => ['image', 'Foto', 'Facoltativa.'],
                            ]],
                'note' => ['textarea', 'Nota', 'Facoltativa. Per esempio: da richiedere con un giorno di anticipo.'],
            ],
        ],
        // Regole (dalla 011): interruttori standard, tradotti da soli; la vecchia lista
        // resta com'era, come «Regole aggiuntive».
        'rules' => [
            'icon' => 'doc', 'group' => 'casa',
            'breve' => 'Fumo, animali, feste, orario del silenzio.',
            'intro' => 'Le regole della struttura, dette una volta e con chiarezza: evitano equivoci durante il soggiorno. Scegli le principali tra quelle pronte, che nella guida si traducono da sole, e aggiungi le tue.',
            'fields' => [
                'flags' => ['toggles', 'Le regole principali', 'Scegli la frase che l\'ospite leggerà nella guida. Con «Non indicato» la regola non compare.', 'options' => [
                    'smoking' => 'Fumo', 'pets' => 'Animali', 'parties' => 'Feste', 'visitors' => 'Visitatori esterni']],
                // Orario del silenzio (fase 6): un interruttore; spento, i due orari si svuotano.
                // Sulle sezioni salvate prima vale acceso se c'è almeno un orario (si calcola in lettura).
                'quiet_on'   => ['check', 'Indica un orario del silenzio', 'Per esempio dalle 22:00 alle 08:00. Se lo spegni, nella guida non compare.'],
                'quiet_from' => ['time', 'Dalle', '', 'se' => 'quiet_on', 'default' => '22:00'],
                'quiet_to'   => ['time', 'Alle', '', 'se' => 'quiet_on', 'default' => '08:00'],
                'items' => ['list', 'Regole aggiuntive', 'Una regola per riga.'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        // Come arrivare (dalla 011): una scheda per mezzo. I vecchi passaggi diventano la prima scheda.
        'arrival' => [
            'icon' => 'pin', 'group' => 'arrivo',
            'breve' => 'Indirizzo, mappa e indicazioni per ogni mezzo.',
            'intro' => 'Il viaggio fino alla porta: da dove arriva l\'ospite (autostrada, stazione, aeroporto) e come raggiunge la struttura. Gli spostamenti durante il soggiorno vanno in «Muoversi in zona».',
            'fields' => [
                'address'  => ['plain', 'Indirizzo', ''],
                'maps_url' => ['url', 'Link a Google Maps', 'Facoltativo. Se manca, la mappa si apre sull\'indirizzo.'],
                'routes'   => ['repeater', 'Indicazioni per mezzo', 'Una riga per mezzo: in auto, in treno, in aereo, in autobus. L\'ospite legge solo quella che gli serve.',
                               'add' => 'Aggiungi un mezzo', 'item' => 'Mezzo', 'max' => 6, 'sub' => [
                                   'mode'  => ['choice', 'Mezzo', '', 'w' => 4, 'options' => ['' => 'Indicazioni', 'auto' => 'In auto', 'treno' => 'In treno', 'aereo' => 'In aereo', 'autobus' => 'In autobus']],
                                   'steps' => ['textarea', 'Passaggi', 'Un passaggio per riga.', 'lines' => true, 'w' => 8],
                               ]],
                'note'     => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        // Muoversi in zona (fase 6C): una scheda per modo. Il vecchio elenco «uno per riga»
        // resta nel JSON e diventa schede di tipo «Altro» (Conversione::muoversi).
        'transport' => [
            'icon' => 'bus', 'group' => 'arrivo',
            'breve' => 'Autobus, taxi, noleggi e navette durante il soggiorno.',
            'intro' => 'Come ci si sposta durante il soggiorno: autobus, taxi, noleggi, navette, scale mobili. Le indicazioni per raggiungere la struttura vanno in «Come arrivare».',
            'fields' => [
                'options' => ['repeater', 'Come muoversi', 'Una riga per ogni modo di muoversi: la linea dell\'autobus, il taxi, chi noleggia le bici.',
                              'add' => 'Aggiungi una voce', 'item' => 'Voce', 'max' => 12,
                              // Righe pronte: il tipo è già scelto, il nome si scrive (niente nome precompilato).
                              'presets' => ['move.taxi' => ['type' => 'taxi'], 'move.bus' => ['type' => 'bus'], 'move.bike_rental' => ['type' => 'bike_rental']], 'preset_nome' => false,
                              'sub' => [
                                  'type'  => ['choice', 'Tipo', '', 'w' => 12, 'pillole' => true, 'options' => [], 'tassonomia' => 'muoversi'],
                                  'name'  => ['text', 'Nome', 'Per esempio: la linea per il centro.', 'w' => 6],
                                  'phone' => ['tel', 'Telefono', 'Facoltativo.', 'w' => 3],
                                  'url'   => ['url', 'Sito web', 'Facoltativo.', 'w' => 3],
                                  'where' => ['text', 'Dove si prende', 'Facoltativo. Per esempio: la fermata davanti alla farmacia.', 'w' => 12],
                                  'note'  => ['textarea', 'Orari, biglietti, costi', 'Facoltativo.'],
                              ]],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        // Parcheggio (dalla 011): più possibilità, una riga ciascuna, e la ZTL a parte.
        // Il parcheggio di prima (tipo, indirizzo, link, istruzioni, costo) diventa la prima riga.
        'parking' => [
            'icon' => 'car', 'group' => 'arrivo',
            'breve' => 'Dove lasciare l\'auto, quanto costa, la ZTL.',
            'intro' => 'Dove lasciare l\'auto: una riga per ogni possibilità, con il costo e i minuti a piedi dalla struttura. Se c\'è una ZTL, scrivi orari e varchi: eviti le multe ai tuoi ospiti.',
            'fields' => [
                'options' => ['repeater', 'Dove parcheggiare', 'Una riga per ogni possibilità: posto privato, parcheggio pubblico, garage…',
                              'add' => 'Aggiungi un parcheggio', 'item' => 'Parcheggio', 'max' => 8, 'sub' => [
                                  'type'         => ['choice', 'Tipo', '', 'w' => 12, 'pillole' => true, 'options' => ['' => 'Non indicato', 'privato' => 'Privato', 'pubblico' => 'Pubblico gratuito',
                                                                                         'pagamento' => 'A pagamento', 'garage' => 'Garage', 'strada' => 'In strada']],
                                  'name'         => ['text', 'Nome o descrizione', 'Per esempio: posto riservato in cortile.', 'w' => 6],
                                  'address'      => ['plain', 'Indirizzo', '', 'w' => 6],
                                  'maps_url'     => ['url', 'Link a Google Maps', 'Facoltativo.', 'w' => 6],
                                  // Fase 6C: il costo scritto a mano diventa due importi e una nota (Conversione::costiParcheggio).
                                  'cost_hour'    => ['money', 'Costo all\'ora', '', 'w' => 3, 'nascosto_con' => ['type', ['privato', 'pubblico']]],
                                  'cost_day'     => ['money', 'Costo al giorno', '', 'w' => 3, 'nascosto_con' => ['type', ['privato', 'pubblico']]],
                                  'cost_note'    => ['text', 'Nota sul costo', 'Facoltativa. Per esempio: gratis la domenica.', 'w' => 8],
                                  'walk_minutes' => ['plain', 'Minuti a piedi', 'Dalla struttura.', 'w' => 4, 'cifre' => true],
                                  'instructions' => ['textarea', 'Istruzioni', 'Facoltative. Per esempio: il cancello si apre con il telecomando che trovi all\'ingresso.'],
                                  'photo'        => ['image', 'Foto', 'Facoltativa.'],
                              ]],
                'ztl' => ['textarea', 'ZTL', 'Facoltativo. Orari e varchi della zona a traffico limitato.'],
            ],
        ],
        // Rifiuti (dalla 011): una riga per tipo, con i giorni, il colore del bidone e dove si
        // trova. Le vecchie voci «una per riga» diventano righe col testo nella descrizione.
        'waste' => [
            'icon' => 'bin', 'group' => 'casa',
            'breve' => 'Giorni di raccolta, colore dei bidoni, dove si trovano.',
            'intro' => 'Come funziona la raccolta differenziata da te: cosa va dove, in quali giorni e in quale bidone. Chi arriva da fuori non conosce le regole del tuo Comune: qui le trova già pronte.',
            'fields' => [
                'bins' => ['repeater', 'Raccolta differenziata', 'Una riga per tipo di rifiuto: i giorni di raccolta, il colore del bidone, dove si trova.',
                           'add' => 'Aggiungi un tipo di rifiuto', 'item' => 'Rifiuto', 'max' => 12, 'sub' => [
                               'type'  => ['choice', 'Tipo', '', 'w' => 4, 'options' => ['altro' => 'Altro', 'umido' => 'Umido', 'carta' => 'Carta', 'plastica' => 'Plastica e metalli',
                                                                              'vetro' => 'Vetro', 'indifferenziato' => 'Indifferenziato']],
                               'label' => ['text', 'Descrizione', 'Facoltativa. Per esempio: lattine insieme alla plastica.', 'w' => 8],
                               'color' => ['choice', 'Colore del bidone', '', 'w' => 4, 'options' => ['' => 'Non indicato', 'marrone' => 'Marrone', 'giallo' => 'Giallo', 'blu' => 'Blu',
                                                                                          'verde' => 'Verde', 'grigio' => 'Grigio', 'bianco' => 'Bianco', 'rosso' => 'Rosso', 'arancione' => 'Arancione']],
                               'where' => ['text', 'Dove si trova', 'Facoltativo.', 'w' => 8],
                               'days'  => ['days', 'Giorni di raccolta', ''],
                           ]],
                'note'  => ['textarea', 'Nota', 'Facoltativa. Per esempio: dove sono i bidoni del condominio.'],
            ],
        ],
        'eat' => [
            'icon' => 'fork', 'places' => true, 'group' => 'territorio',
            'breve' => 'Ristoranti, bar e gelaterie che consigli.',
            'intro' => 'I posti dove mandi i tuoi ospiti a mangiare e a bere. Per ognuno bastano il link di Google Maps e due righe sul perché ti piace: sono i consigli che una ricerca online non dà.',
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', 'Facoltativa. Una frase che presenta i tuoi consigli.'],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo. Nella guida compare firmato con il tuo nome.'],
            ],
        ],
        'visit' => [
            'icon' => 'monument', 'places' => true, 'group' => 'territorio',
            'breve' => 'Monumenti, borghi, musei e panorami.',
            'intro' => 'I luoghi da vedere: monumenti, borghi, musei, panorami. Per ognuno bastano il link di Google Maps e due righe sul perché vale la visita. Le attività (escursioni, degustazioni, terme) vanno in «Cosa fare».',
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', 'Facoltativa. Una frase che presenta i tuoi consigli.'],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo. Nella guida compare firmato con il tuo nome.'],
            ],
        ],
        'todo' => [
            'icon' => 'compass', 'places' => true, 'group' => 'territorio',
            'breve' => 'Escursioni, degustazioni, terme, attività.',
            'intro' => 'Le esperienze da vivere in zona: escursioni, degustazioni, terme, attività per i bambini. Per ognuna bastano il link di Google Maps e due righe sul perché la consigli. I luoghi da visitare vanno in «Cosa consigliamo di visitare».',
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', 'Facoltativa. Una frase che presenta i tuoi consigli.'],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo. Nella guida compare firmato con il tuo nome.'],
            ],
        ],
        // Negozi e spesa (fase 6B): alimentari, forno, mercato, farmacia, bancomat.
        'shop' => [
            'icon' => 'bag', 'places' => true, 'group' => 'territorio',
            'breve' => 'Alimentari, forno, mercato, farmacia, bancomat.',
            'intro' => 'Dove fare la spesa e trovare quello che serve ogni giorno: alimentari, forno, mercato, farmacia, bancomat. Con un\'etichetta segnali chi è aperto la domenica o fino a tardi.',
            'fields' => [
                'intro'     => ['textarea', 'Introduzione', 'Facoltativa. Una frase che presenta i tuoi consigli.'],
                'host_note' => ['textarea', 'Il tuo consiglio personale', 'Facoltativo. Nella guida compare firmato con il tuo nome.'],
            ],
        ],
        'emergency' => [
            'icon' => 'phone', 'group' => 'arrivo',
            'breve' => 'I numeri utili, da chiamare con un tocco.',
            'intro' => 'I numeri da avere sotto mano se qualcosa va storto: l\'ospite li chiama con un tocco. Parti dal 112 e aggiungi guardia medica, farmacia e il tuo numero per le urgenze.',
            'fields' => [
                'emergency_number' => ['plain', 'Numero unico di emergenza', 'In Italia è il 112.', 'tastiera' => 'tel'],
                // Dalla 011: righe nome · telefono · nota, con un «Chiama» per riga nella guida.
                'contacts'         => ['repeater', 'Contatti utili', 'Una riga per contatto: guardia medica, farmacia di turno, il tuo numero per le urgenze.',
                                       'add' => 'Aggiungi un contatto', 'item' => 'Contatto', 'max' => 15,
                                       'presets' => ['emergency_number' => ['phone' => '112'], 'preset_guardia' => [], 'preset_farmacia' => [], 'preset_veterinario' => []],
                                       'sub' => [
                                           'name'  => ['text', 'Nome', '', 'w' => 5],
                                           'phone' => ['tel', 'Telefono', '', 'w' => 3],
                                           'note'  => ['text', 'Nota', 'Facoltativa. Orari, indirizzo…', 'w' => 4],
                                       ]],
                'note'             => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
        // Eventi (fase 6G): sagre, mercati, concerti. La logica delle date è in Eventi; la guida
        // filtra quando si mostra (gli eventi passati spariscono da soli). La locandina è una
        // zona sola, immagine o PDF: l'immagine va in `poster`, il PDF in `poster_pdf`.
        'events' => [
            'icon' => 'calendar', 'group' => 'territorio',
            'breve' => 'Sagre, mercati e concerti, con le date.',
            'intro' => 'Quello che succede in zona: sagre, mercati, concerti, con la data e la locandina. Gli eventi passati spariscono da soli dalla guida e restano qui finché non li togli; quelli che tornano ogni anno li riproponi con un clic.',
            'fields' => [
                'intro'  => ['textarea', 'Introduzione', 'Facoltativa.'],
                'events' => ['repeater', 'Eventi', 'Una riga per evento: sagre, mercati, concerti. Con la locandina, se c\'è.',
                             'add' => 'Aggiungi un evento', 'item' => 'Evento', 'max' => 40, 'eventi' => true, 'sub' => [
                    'name'        => ['text', 'Nome', '', 'w' => 8],
                    'cat'         => ['choice', 'Categoria', '', 'w' => 4, 'options' => [], 'tassonomia' => 'eventi'],
                    'when'        => ['choice', 'Quando', '', 'w' => 12, 'pillole' => true, 'default' => 'day', 'options' => ['day' => 'Un giorno', 'range' => 'Più giorni', 'weekly' => 'Ogni settimana', 'other' => 'Altro']],   // le chiavi di Eventi::QUANDO
                    'date_from'   => ['date', 'Dal', '', 'w' => 4, 'etichetta_se' => ['when', ['day' => 'Giorno']],
                                      'aiuto_con' => ['when', ['weekly', 'other'], 'Facoltativo: da quando a quando.']],
                    'date_to'     => ['date', 'Al', '', 'w' => 4, 'nascosto_con' => ['when', ['day']],
                                      'aiuto_con' => ['when', ['weekly', 'other'], 'Facoltativo: da quando a quando.']],
                    'time_from'   => ['time', 'Dalle', '', 'w' => 2],
                    'time_to'     => ['time', 'Alle', 'Facoltativo.', 'w' => 2],
                    'days'        => ['days', 'Giorni', '', 'solo_con' => ['when', ['weekly']]],
                    'when_text'   => ['text', 'Quando, a parole', 'Per esempio: la seconda domenica del mese.', 'w' => 12, 'solo_con' => ['when', ['other']]],
                    'yearly'      => ['check', 'Si ripete ogni anno nello stesso periodo', '', 'w' => 12, 'solo_con' => ['when', ['day', 'range']]],
                    'place'       => ['plain', 'Luogo', '', 'w' => 8],
                    'dist_min'    => ['plain', 'Distanza (min)', '', 'w' => 2, 'cifre' => true],
                    'dist_mode'   => ['choice', 'Mezzo', '', 'w' => 2, 'options' => ['walk' => 'a piedi', 'car' => 'in auto']],
                    'price_kind'  => ['choice', 'Ingresso', '', 'w' => 6, 'options' => ['' => 'Non indicato', 'free' => 'Gratis', 'paid' => 'A pagamento']],
                    'price'       => ['plain', 'Quanto costa', 'Per esempio: 5 €.', 'w' => 6, 'solo_con' => ['price_kind', ['paid']]],
                    'url'         => ['url', 'Sito o biglietti', 'Facoltativo.', 'w' => 12],
                    'description' => ['textarea', 'Descrizione', 'Facoltativa.'],
                    'poster'      => ['image', 'Locandina', 'Un\'immagine o un PDF.', 'locandina' => 'poster_pdf'],
                    'poster_pdf'  => ['pdf', 'Locandina in PDF', '', 'nascosto' => true],
                    'recommended' => ['check', 'Lo consiglio io', '', 'w' => 12],
                ]],
            ],
        ],
        // Sezione libera (6D · S1): titolo e icona li sceglie l'host, e si aggiunge più volte
        // ('multipla'). Foto e PDF sono quelli di ogni sezione. Nella home non si conta.
        'custom' => [
            'icon' => 'star', 'group' => 'casa', 'multipla' => true, 'nome' => 'Sezione libera',
            'breve' => 'Titolo, icona e testo li scegli tu.',
            'intro' => 'Per quello che non sta nelle altre sezioni: la piscina, il giardino, la storia del posto. Scegli il titolo e l\'icona; puoi aggiungerne quante ne vuoi.',
            'fields' => [
                'icona' => ['choice', 'Icona', 'Compare nella guida, sulla casella della sezione.', 'icone' => true, 'options' => [
                    'star' => 'Stella', 'info' => 'Informazione', 'book' => 'Libro', 'key' => 'Chiave', 'sun' => 'Sole', 'moon' => 'Luna',
                    'coffee' => 'Caffè', 'grill' => 'Barbecue', 'paw' => 'Animali', 'music' => 'Musica', 'people' => 'Persone', 'compass' => 'Bussola']],
                'text'  => ['textarea', 'Testo', 'Quello che vuoi raccontare.'],
                'items' => ['list', 'Elenco', 'Facoltativo. Una voce per riga.'],
            ],
        ],
        'info' => [
            'icon' => 'info', 'group' => 'arrivo',
            'breve' => 'Avvisi pratici che non stanno altrove.',
            'intro' => 'Le cose pratiche che è meglio sapere e che non stanno nelle altre sezioni: l\'acqua del rubinetto, il giorno di mercato, il contatore che scatta. Una voce per riga, breve.',
            'fields' => [
                'items' => ['list', 'Cose da sapere', 'Una voce per riga.'],
                'note'  => ['textarea', 'Nota', 'Facoltativa.'],
            ],
        ],
    ];

    /** Tipi che non si traducono: vivono in sections.data. */
    private const PLAIN = ['plain', 'url', 'secret', 'choice', 'tel', 'time', 'days', 'check', 'checks', 'toggles', 'image', 'pdf', 'money', 'date'];

    /** I gruppi della home, nell'ordine in cui si mostrano. */
    public const GRUPPI = ['casa' => 'La casa', 'arrivo' => 'Arrivare e muoversi', 'territorio' => 'Il territorio'];

    /** I tipi di ogni gruppo, nell'ordine del catalogo (la sezione libera no: non è «pronta»). @return array<string,list<string>> */
    public static function gruppi(): array
    {
        $out = array_fill_keys(array_keys(self::GRUPPI), []);
        foreach (self::K as $k => $d) if (empty($d['multipla'])) $out[$d['group'] ?? 'casa'][] = $k;
        return $out;
    }

    /** La riga di presentazione nel catalogo «Aggiungi una sezione». */
    public static function breve(string $kind): string { return (string) (self::get($kind)['breve'] ?? ''); }

    /**
     * Un aiuto che comincia con «Facoltativo.» (o «Facoltativa.», «Facoltative.»…) si divide in due:
     * la parola va accanto all'etichetta, tra parentesi, e l'aiuto resta per l'esempio.
     * @return array{0:string,1:string} [«facoltativo» concordato oppure '', il resto dell'aiuto]
     */
    public static function facoltativo(string $aiuto): array
    {
        if (preg_match('/^(Facoltativ[oaie])\.\s*(.*)$/su', $aiuto, $m)) return [mb_strtolower($m[1]), $m[2]];
        return ['', $aiuto];
    }

    /** Si può aggiungere più volte alla stessa struttura? (la sezione libera) */
    public static function multipla(string $kind): bool { return !empty(self::get($kind)['multipla']); }

    /** Il nome nel pannello («Sezione libera»); per gli altri è il titolo italiano. */
    public static function nome(string $kind): string { return self::get($kind)['nome'] ?? self::title($kind, 'it'); }

    /** L'icona di una sezione: quella scelta dall'host nella sezione libera, altrimenti quella del tipo. */
    public static function iconaDi(string $kind, mixed $data = []): string
    {
        if (is_string($data)) $data = json_decode($data, true) ?: [];
        $scelta = (string) (((array) $data)['icona'] ?? '');
        $opz = self::K[$kind]['fields']['icona']['options'] ?? [];
        return $scelta !== '' && isset($opz[$scelta]) ? $scelta : self::icon($kind);
    }

    public static function kinds(): array { return array_keys(self::K); }

    /** I tipi selezionabili, cioè tutti tranne il nucleo. */
    public static function selectable(): array
    {
        return array_values(array_filter(self::kinds(), fn($k) => empty(self::K[$k]['core'])));
    }

    public static function exists(string $kind): bool { return isset(self::K[$kind]); }

    public static function get(string $kind): array { return self::K[$kind] ?? self::K['info']; }

    /** Quanto è largo un sottocampo nella riga, su 12 colonne. */
    public static function larghezza(array $sd): int
    {
        if (isset($sd['w'])) return (int) $sd['w'];
        return in_array($sd[0], ['textarea', 'image', 'pdf', 'days'], true) ? 12 : 6;
    }

    public static function icon(string $kind): string { return self::get($kind)['icon']; }

    public static function hasPlaces(string $kind): bool { return !empty(self::get($kind)['places']); }

    public static function isCore(string $kind): bool { return !empty(self::get($kind)['core']); }

    /** Il titolo predefinito nella lingua dell'ospite. */
    public static function title(string $kind, string $loc): string
    {
        return I18n::t($loc, 'kind.' . $kind);
    }

    /** @return array<string,array{0:string,1:string,2:string}> campo => [tipo, etichetta, aiuto] */
    public static function fields(string $kind): array
    {
        $f = self::get($kind)['fields'];
        // Le opzioni che vengono dalle tassonomie (fase 6), con le etichette italiane del pannello.
        $opzioni = function (array $d): array {
            return match ($d['tassonomia'] ?? '') {
                'dotazioni' => array_combine(Tassonomie::dotazioni(), array_map(fn($x) => I18n::t('it', 'amen_' . $x), Tassonomie::dotazioni())),
                'eventi' => array_combine(Eventi::CATEGORIE, array_map(fn($x) => I18n::t('it', 'evcat.' . $x), Eventi::CATEGORIE)),
                'muoversi' => array_combine(Tassonomie::MUOVERSI, array_map(fn($x) => I18n::t('it', 'move.' . $x), Tassonomie::MUOVERSI)),
                // Senza unità il prezzo resta «25 €».
                'unita' => ['' => 'Nessuna'] + array_combine(Tassonomie::UNITA, array_map(fn($x) => I18n::t('it', 'unit.' . $x), Tassonomie::UNITA)),
                default => $d['options'] ?? [],
            };
        };
        foreach ($f as $n => $d) {
            if (isset($d['tassonomia'])) $f[$n]['options'] = $opzioni($d);
            foreach ($d['sub'] ?? [] as $sn => $sd) if (isset($sd['tassonomia'])) $f[$n]['sub'][$sn]['options'] = $opzioni($sd);
        }
        return $f;
    }

    /** Un campo (o sottocampo) da tradurre. Il repeater è misto: si guarda sotto. */
    public static function isTranslated(string $type): bool { return !in_array($type, self::PLAIN, true) && $type !== 'repeater'; }

    /** La definizione completa di un campo (con 'sub', 'options', 'suggest'…). */
    public static function field(string $kind, string $name): ?array { return self::fields($kind)[$name] ?? null; }

    /** Gli id dei media (foto e PDF) dentro le righe di una sezione: da duplicare, da cancellare. */
    public static function mediaIds(string $kind, array $data): array
    {
        $ids = [];
        foreach (self::fields($kind) as $name => $def) {
            if ($def[0] !== 'repeater') continue;
            foreach ($def['sub'] as $sn => $sd) {
                if (!in_array($sd[0], ['image', 'pdf'], true)) continue;
                foreach ((array) ($data[$name] ?? []) as $r) if (is_array($r) && (int) ($r[$sn] ?? 0) > 0) $ids[] = (int) $r[$sn];
            }
        }
        return array_values(array_unique($ids));
    }

    /** Un id di riga breve e stabile. */
    public static function newId(): string { return 'r' . bin2hex(random_bytes(4)); }

    /** Il valore pulito di un (sotto)campo, secondo il tipo. */
    public static function clean(array $def, mixed $raw): mixed
    {
        $type = $def[0];
        return match ($type) {
            'url' => Support::safeUrl(mb_substr(trim((string) $raw), 0, 500)),
            'textarea' => mb_substr(trim((string) $raw), 0, 2000),
            'choice' => isset($def['options'][(string) $raw]) ? (string) $raw : '',
            'tel' => mb_substr(Telefono::normalizza((string) $raw), 0, 40),
            'time' => preg_match('/^([01]?\d|2[0-3])[:.][0-5]\d$/', trim((string) $raw)) ? str_pad(str_replace('.', ':', trim((string) $raw)), 5, '0', STR_PAD_LEFT) : '',
            'days' => array_values(array_unique(array_filter(array_map('intval', (array) $raw), fn($d) => $d >= 1 && $d <= 7))),
            'check' => !empty($raw) && $raw !== '0' ? '1' : '',
            'checks' => array_values(array_intersect(array_keys($def['options']), array_map('strval', (array) $raw))),
            'toggles' => array_filter(array_intersect_key(array_map(fn($v) => in_array($v, ['si', 'no'], true) ? $v : '', (array) $raw), $def['options'])),
            'image', 'pdf' => (int) $raw > 0 ? (int) $raw : '',
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $raw)) && checkdate((int) substr(trim((string) $raw), 5, 2), (int) substr(trim((string) $raw), 8, 2), (int) substr(trim((string) $raw), 0, 4)) ? trim((string) $raw) : '',
            'money' => Conversione::importo(trim(str_replace(['€', ' '], '', (string) $raw))),
            'plain', 'secret' => !empty($def['cifre']) ? substr(preg_replace('/\D/', '', (string) $raw), 0, 3) : mb_substr(trim((string) $raw), 0, 200),
            default => mb_substr(trim((string) $raw), 0, 300),
        };
    }

    /**
     * Le righe di un repeater pronte da mostrare: la parte comune nell'ordine
     * salvato, più i testi della lingua chiesta, e dove mancano quelli della
     * lingua principale. Le righe tradotte che non esistono più si ignorano.
     */
    public static function rows(array $def, mixed $comuni, mixed $principale, mixed $lingua = []): array
    {
        $perId = function (mixed $righe): array {
            $out = [];
            foreach ((array) $righe as $r) if (is_array($r) && isset($r['id'])) $out[(string) $r['id']] = $r;
            return $out;
        };
        $base = $perId($principale); $tr = $perId($lingua);
        $out = [];
        foreach ((array) $comuni as $r) {
            if (!is_array($r) || !isset($r['id'])) continue;
            $id = (string) $r['id']; $riga = ['id' => $id];
            foreach ($def['sub'] as $sn => $sd) {
                if (self::isTranslated($sd[0])) {
                    $v = trim((string) ($tr[$id][$sn] ?? ''));
                    $riga[$sn] = $v !== '' ? $v : trim((string) ($base[$id][$sn] ?? ''));
                } else {
                    $riga[$sn] = $r[$sn] ?? ($sd[0] === 'days' ? [] : '');
                }
            }
            $out[] = $riga;
        }
        return $out;
    }

    /**
     * Legge un modulo inviato e separa i campi uguali in ogni lingua da quelli
     * da tradurre. Solo i campi dichiarati passano: niente chiavi arbitrarie.
     * Solo i campi presenti nel modulo: quelli assenti restano come sono.
     *
     * @return array{0:array,1:array} [dati comuni, dati tradotti]
     */
    public static function fromInput(string $kind, array $in, bool $withPlain = true): array
    {
        $comuni = []; $tradotti = [];
        foreach (self::fields($kind) as $name => [$type]) {
            // Un campo che il modulo non ha mandato non si tocca: così un invio
            // parziale (una foto, un salvataggio automatico) non cancella il resto.
            if (!array_key_exists($name, $in)) continue;
            $raw = $in[$name];
            $def = self::field($kind, $name);
            if ($type === 'repeater') {
                // Le righe nell'ordine del modulo. Nella lingua principale una riga
                // tutta vuota si scarta; nelle traduzioni si tengono tutte le righe.
                $comune = []; $testi = [];
                foreach (array_values(is_array($raw) ? $raw : []) as $r) {
                    if (!is_array($r)) continue;
                    $id = preg_match('/^r[0-9a-f]{4,16}$/', (string) ($r['id'] ?? '')) ? (string) $r['id'] : self::newId();
                    $rc = ['id' => $id]; $rt = ['id' => $id]; $piena = false;
                    foreach ($def['sub'] as $sn => $sd) {
                        if (!array_key_exists($sn, $r) && !in_array($sd[0], ['check', 'days'], true)) continue;
                        $v = self::clean($sd, $r[$sn] ?? '');
                        if (self::isTranslated($sd[0])) $rt[$sn] = $v; else $rc[$sn] = $v;
                        // Una scelta lasciata sulla prima opzione (il valore di partenza) o una
                        // spunta da sola non bastano a fare una riga.
                        if ($sd[0] === 'choice') { if ($v !== '' && $v !== (string) array_key_first($sd['options'])) $piena = true; }
                        elseif ($sd[0] !== 'check' && $v !== '' && $v !== []) $piena = true;
                    }
                    // Un sottocampo nascosto dall'altro campo della riga (i costi di un parcheggio privato) si svuota.
                    foreach ($def['sub'] as $sn => $sd) {
                        if (!isset($sd['nascosto_con'], $rc[$sn])) continue;
                        [$altro, $valori] = $sd['nascosto_con'];
                        if (in_array((string) ($rc[$altro] ?? ''), $valori, true)) $rc[$sn] = '';
                    }
                    if ($withPlain && !$piena) continue;
                    $comune[] = $rc; $testi[] = $rt;
                    if (count($comune) >= ($def['max'] ?? 30)) break;
                }
                if ($withPlain) $comuni[$name] = $comune;
                $tradotti[$name] = $testi;
            } elseif (in_array($type, ['choice', 'checks', 'toggles', 'time', 'check'], true)) {
                if ($withPlain) $comuni[$name] = self::clean($def, is_array($raw) && $type === 'check' ? end($raw) : $raw);
            } elseif (in_array($type, ['steps', 'list'], true)) {
                $righe = is_array($raw) ? $raw : preg_split('/\R/', (string) $raw);
                $righe = array_values(array_filter(array_map(fn($r) => mb_substr(trim((string) $r), 0, 600), $righe ?: []), fn($r) => $r !== ''));
                $tradotti[$name] = array_slice($righe, 0, 30);
            } elseif ($type === 'url') {
                if ($withPlain) $comuni[$name] = Support::safeUrl(mb_substr((string) $raw, 0, 500));
            } elseif (in_array($type, self::PLAIN, true)) {
                if ($withPlain) $comuni[$name] = !empty($def['cifre']) ? substr(preg_replace('/\D/', '', (string) $raw), 0, 3)
                    : mb_substr(($def['tastiera'] ?? '') === 'tel' ? Telefono::normalizza((string) $raw) : trim((string) $raw), 0, 200);
            } elseif ($type === 'textarea') {
                $tradotti[$name] = mb_substr(trim((string) $raw), 0, 2000);
            } else {
                $tradotti[$name] = mb_substr(trim((string) $raw), 0, 300);
            }
        }
        // Un campo legato a un interruttore spento si svuota (l'orario del silenzio).
        foreach (self::fields($kind) as $name => $def) {
            $se = $def['se'] ?? null;
            if ($se !== null && array_key_exists($se, $comuni) && $comuni[$se] === '' && array_key_exists($name, $comuni)) $comuni[$name] = '';
        }
        return [$comuni, $tradotti];
    }

    /** Un interruttore è acceso? Sulle sezioni di prima (senza l'interruttore) vale acceso se c'è almeno uno dei campi legati. */
    public static function acceso(string $kind, string $check, array $data): bool
    {
        if (array_key_exists($check, $data)) return (string) $data[$check] === '1';
        foreach (self::fields($kind) as $name => $def) {
            if (($def['se'] ?? null) === $check && trim((string) ($data[$name] ?? '')) !== '') return true;
        }
        return false;
    }

    /** Una sezione ha qualcosa da mostrare? (per l'anteprima e la pubblicazione) */
    public static function isEmpty(string $kind, array $data, array $tdata, int $places = 0): bool
    {
        if (self::hasPlaces($kind) && $places > 0) return false;
        unset($data['icona']);   // l'icona da sola non fa una sezione piena
        foreach ($data + $tdata as $v) {
            if (is_array($v) ? count($v) > 0 : trim((string) $v) !== '') return false;
        }
        return true;
    }
}
