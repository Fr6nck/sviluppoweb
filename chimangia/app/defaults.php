<?php
declare(strict_types=1);

defined('CM_ROOT') || exit;

/**
 * Contenuti iniziali, ripresi alla lettera dal mockup design/sito-onepage.dc.html.
 * Vengono caricati nel database dalla procedura d'installazione.
 *
 * Nei testi brevi si possono usare i segnaposto:
 *   {sconto}    percentuale dell'offerta di lancio (es. "30%")
 *   {prezzo_da} canone mensile più basso tra i pacchetti visibili (es. "29 €")
 *
 * @return array<string, array<string, mixed>>
 */
function default_content(): array
{
    return [
        'header' => [
            'nav' => [
                ['id' => 'n1', 'label' => 'Come funziona', 'href' => '#come-funziona'],
                ['id' => 'n2', 'label' => 'Funzioni', 'href' => '#funzioni'],
                ['id' => 'n3', 'label' => 'Prezzi', 'href' => '#prezzi'],
                ['id' => 'n4', 'label' => 'Domande', 'href' => '#domande'],
            ],
            'cta_text' => 'Prova gratis 7 giorni',
            'cta_href' => '#contatti',
        ],

        'hero' => [
            'visible' => true,
            'badge' => 'Offerta di lancio: −{sconto} per i primi 10 clienti',
            'title' => 'Sai chi mangia, prima di *accendere i fornelli.*',
            'text' => 'I tuoi clienti prenotano pranzo e cena dal telefono e scelgono i piatti dal menù del giorno. Tu trovi i conteggi già pronti per la cucina. Niente più telefonate, messaggi e fogli.',
            'btn1_text' => 'Prova gratis 7 giorni',
            'btn1_href' => '#contatti',
            'btn2_text' => 'Guarda i prezzi',
            'btn2_href' => '#prezzi',
            'note' => 'Da {prezzo_da} al mese. Nessuna app da scaricare dagli store, nessun vincolo.',
            'card_label' => 'Cucina · oggi a pranzo',
            'card_number' => '38',
            'card_number_label' => 'coperti confermati',
            'card_groups' => [
                ['id' => 'g1', 'title' => 'Primi', 'items' => "Penne all'arrabbiata | 17\nRisotto ai funghi | 12\nMinestrone | 6"],
                ['id' => 'g2', 'title' => 'Secondi', 'items' => "Arrosto di vitello | 21\nFrittata di verdure | 9"],
            ],
            'card_tags' => "3 senza glutine\n2 vegetariani",
            'card_caption' => 'Esempio di schermata',
            'layout' => 'full',
            'photo' => 'brigata',
            'photo_alt' => '',
            'photo2' => 'sala',
            'photo3' => 'reception',
            'photo4' => 'camera',
            'photo5' => '',
            'photo6' => '',
        ],

        'problema' => [
            'visible' => true,
            'title' => 'Ogni mattina la stessa domanda: *quanti siamo oggi?*',
            'cards' => [
                ['id' => 'p1', 'icon' => 'telefono', 'title' => 'Telefonate e messaggi', 'text' => 'Prenotazioni e disdette arrivano a tutte le ore, su canali diversi, e qualcuna si perde.'],
                ['id' => 'p2', 'icon' => 'cestino', 'title' => 'Cibo sprecato', 'text' => 'Senza numeri certi si cucina a occhio: troppo, oppure troppo poco.'],
                ['id' => 'p3', 'icon' => 'calcolatrice', 'title' => 'Conti a fine mese', 'text' => 'Ricostruire chi ha mangiato cosa, azienda per azienda, porta via ore.'],
            ],
            'closing' => 'ChiMangia risponde a quella domanda prima che tu la faccia.',
            'photo' => 'bancone',
            'photo_alt' => '',
        ],

        'come_funziona' => [
            'visible' => true,
            'title' => 'Come *funziona*',
            'subtitle' => 'Tre passaggi, ogni giorno.',
            'cards' => [
                ['id' => 'c1', 'title' => 'Pubblichi il menù', 'text' => 'Scrivi i piatti del giorno in un minuto, oppure carichi la foto del menù. Decidi tu entro che ora si prenota.'],
                ['id' => 'c2', 'title' => 'I clienti scelgono dal telefono', 'text' => "Ricevono l'avviso, scelgono primo, secondo e contorno con un tocco e confermano. Chi non può, disdice in tempo."],
                ['id' => 'c3', 'title' => 'La cucina ha i numeri', 'text' => 'Quanti coperti, quanti piatti di ogni tipo, chi ha diete o intolleranze. Sul telefono o stampato su un foglio.'],
            ],
        ],

        'giornata' => [
            'visible' => true,
            'title' => 'Una giornata con *ChiMangia*',
            'intro' => 'Chi cucina per tanti lo sa: il lavoro vero non è ai fornelli, è capire in tempo per quante persone cucinare. Ecco cosa cambia, dal menù della sera prima ai conti di fine mese.',
            'cards' => [
                [
                    'id' => 'd1',
                    'kicker' => 'La sera prima',
                    'title' => 'Il menù *parte da solo*',
                    'body' => '<p>Scrivi i piatti di domani, oppure fotografi il foglio che hai già preparato. Scegli entro che ora si può prenotare e premi Pubblica.</p>'
                        . "<p>In quel momento ogni cliente riceve l'avviso sul telefono. Non devi chiamare nessuno, né rispondere a venti messaggi diversi: chi vuole mangiare sceglie i suoi piatti, chi non c'è lo dice con un tocco.</p>",
                    'illustration' => 'foto-sera',
                    'image' => 0,
                    'alt' => 'Il titolare di una trattoria, a sala chiusa, pubblica il menù dal telefono',
                ],
                [
                    'id' => 'd2',
                    'kicker' => 'La mattina',
                    'title' => 'Apri il telefono e *trovi la risposta*',
                    'body' => '<p>Quando arrivi in cucina i numeri sono già lì: i coperti di pranzo e di cena, quanti piatti preparare di ogni tipo, chi è senza glutine e chi non mangia maiale.</p>'
                        . "<p>Fai la spesa giusta e cucini il giusto. Chi non ha confermato entro l'orario è stato disdetto in automatico, quindi nessun piatto resta sul passe ad aspettare qualcuno che non arriva.</p>",
                    'illustration' => 'foto-mattina',
                    'image' => 0,
                    'alt' => 'Un giovane cuoco in grembiule blu guarda soddisfatto il telefono in cucina, accanto alle cassette di verdura fresca',
                ],
                [
                    'id' => 'd3',
                    'kicker' => 'A fine mese',
                    'title' => 'I conti sono *già fatti*',
                    'body' => '<p>Ogni pasto resta registrato con il nome del cliente, la sua azienda e la formula scelta. Quando è ora di fare i conti apri il riepilogo: quanti pasti, per quale azienda, in quali giorni.</p>'
                        . "<p>Quello che prima richiedeva una serata con fogli e calcolatrice diventa una stampa. E se un'azienda chiede il dettaglio, lo hai in un minuto.</p>",
                    'illustration' => 'foto-fine-mese',
                    'image' => 0,
                    'alt' => 'La gestrice di una trattoria guarda soddisfatta il riepilogo stampato, con il portatile aperto',
                ],
            ],
        ],

        'funzioni' => [
            'visible' => true,
            'title' => 'Tutto quello che serve, *niente di più*',
            'subtitle' => 'Ogni funzione è inclusa in tutti i piani. Quelle che non usi si spengono.',
            'cards' => [
                ['id' => 'f1', 'icon' => 'posate', 'title' => 'Menù a scelta o in foto', 'text' => 'Alternative tra primi, secondi e contorni, con dolce, caffè e ammazzacaffè facoltativi.'],
                ['id' => 'f2', 'icon' => 'etichetta', 'title' => 'Formule a prezzo fisso', 'text' => 'Base, completo, con o senza bevanda: le formule del tuo pranzo di lavoro, come le fai già.'],
                ['id' => 'f3', 'icon' => 'cuore', 'title' => 'Diete e intolleranze', 'text' => 'Ogni cliente le indica una volta sola. La cucina le vede accanto al nome, ogni giorno.'],
                ['id' => 'f4', 'icon' => 'orologio', 'title' => 'Orario limite e disdette', 'text' => "Fissi tu l'orario. Chi non conferma in tempo viene disdetto in automatico: niente pasti a vuoto."],
                ['id' => 'f5', 'icon' => 'edificio', 'title' => 'Aziende e riepilogo mensile', 'text' => 'Ogni cliente indica la sua azienda. A fine mese hai il riepilogo dei pasti, azienda per azienda.'],
                ['id' => 'f6', 'icon' => 'campanella', 'title' => 'Avvisi automatici', 'text' => 'Notifiche sul telefono ed email quando esce il menù e quando una richiesta viene approvata.'],
                ['id' => 'f7', 'icon' => 'matita', 'title' => 'Varianti su richiesta', 'text' => 'Il cliente chiede una pasta in bianco al posto del sugo: tu approvi o rifiuti con un tocco.'],
                ['id' => 'f8', 'icon' => 'stampante', 'title' => 'Stampe pronte', 'text' => 'Il foglio per la cucina con pranzo e cena, la situazione per azienda e per singolo cliente.'],
                ['id' => 'f9', 'icon' => 'smartphone', 'title' => "Funziona come un'app", 'text' => 'Si apre dal browser e si aggiunge alla schermata del telefono. Con il tuo logo e i tuoi colori.'],
            ],
        ],

        'per_chi' => [
            'visible' => true,
            'title' => 'Per *chi è*',
            'cards' => [
                ['id' => 'w1', 'title' => 'Trattorie e ristoranti', 'text' => 'Con pranzo di lavoro e aziende convenzionate.'],
                ['id' => 'w2', 'title' => 'Mense aziendali', 'text' => 'Piccole e medie, gestite in proprio.'],
                ['id' => 'w3', 'title' => 'Convitti e residenze', 'text' => 'Per studenti e lavoratori, con presenze ai pasti ogni giorno.'],
                ['id' => 'w4', 'title' => 'Strutture con ristorante', 'text' => 'Che ospitano squadre di lavoratori in trasferta.'],
            ],
        ],

        'prezzi' => [
            'visible' => true,
            'title' => 'Prezzi chiari, in base a *quanti clienti servi*',
            'intro' => 'Tutte le funzioni sono incluse in ogni piano. Paghi solo per i clienti che prenotano almeno un pasto nel mese.',
            'offer_active' => true,
            'offer_percent' => 30,
            'offer_end' => '',
            'offer_band' => 'Offerta di lancio per i primi 10 clienti, su canone e attivazione. Prezzo bloccato per 24 mesi.',
            'step1_title' => 'I pacchetti',
            'step1_text' => "Scegli il pacchetto: canone mensile o annuale, più l'attivazione una tantum.",
            'toggle_label' => 'Mostra i prezzi',
            'toggle_monthly' => 'Mensile',
            'toggle_annual' => 'Annuale',
            'per_month' => 'al mese',
            'per_year' => "all'anno",
            'alt_prefix' => 'Oppure',
            'annual_suffix' => ': due mesi li offriamo noi.',
            'monthly_suffix' => 'con il piano mensile.',
            'activation_label' => '+ Attivazione, una volta sola',
            'packages' => [
                [
                    'id' => 'k1', 'name' => 'Fino a 40 clienti', 'clients' => 40,
                    'monthly_full' => 41, 'monthly_launch' => 29,
                    'annual_full' => 410, 'annual_launch' => 290,
                    'activation_full' => 270, 'activation_launch' => 190,
                    'note' => '', 'featured' => false, 'badge' => '',
                    'cta_text' => 'Inizia la prova', 'cta_href' => '#contatti', 'visible' => true,
                ],
                [
                    'id' => 'k2', 'name' => 'Fino a 80 clienti', 'clients' => 80,
                    'monthly_full' => 70, 'monthly_launch' => 49,
                    'annual_full' => 700, 'annual_launch' => 490,
                    'activation_full' => 410, 'activation_launch' => 290,
                    'note' => '', 'featured' => true, 'badge' => 'Consigliato',
                    'cta_text' => 'Inizia la prova', 'cta_href' => '#contatti', 'visible' => true,
                ],
                [
                    'id' => 'k3', 'name' => 'Oltre 80 clienti', 'clients' => 150,
                    'monthly_full' => 99, 'monthly_launch' => 69,
                    'annual_full' => 990, 'annual_launch' => 690,
                    'activation_full' => 560, 'activation_launch' => 390,
                    'note' => 'Fino a 150 clienti; oltre, 10 € al mese ogni 50 in più.', 'featured' => false, 'badge' => '',
                    'cta_text' => 'Inizia la prova', 'cta_href' => '#contatti', 'visible' => true,
                ],
            ],
            'step2_title' => "L'attivazione",
            'step2_text' => "Una volta sola, all'avvio. L'importo è in ogni pacchetto qui sopra.",
            'activation_title' => 'Partiamo insieme, con tutto già pronto',
            'activation_list' => '<ul><li>Configurazione con il tuo logo e i tuoi colori</li><li>Caricamento di menù, formule e orari</li><li>Formazione del personale di sala e di cucina</li></ul>',
            'activation_text' => "L'importo dipende dal piano ed è indicato in ogni pacchetto, sotto il canone.",
            'activation_offer_text' => "Con l'offerta di lancio è scontato del {sconto}, come il canone.",
            'example' => '<p><strong>Un esempio.</strong> Fino a 80 clienti con piano annuale: il primo anno 490 € di canone + 290 € di attivazione = 780 €. Dal secondo anno solo il canone.</p>',
            'trial_days' => 7,
            'trial' => '<p><strong>Prova gratuita di 7 giorni.</strong> La provi con dati di esempio, senza carta e senza attivazione: la paghi solo se decidi di continuare.</p>',
            'hotel_visible' => true,
            'hotel_full' => 29,
            'hotel_launch' => 20,
            'hotel_text' => 'Hai anche le camere? Con il modulo Hotel aggiungi notti e assegnazione delle camere:',
            'vat_note' => 'Prezzi IVA esclusa.',
            'vat_offer_note' => "Il prezzo pieno si applica al termine dell'offerta di lancio.",
        ],

        'faq' => [
            'visible' => true,
            'title' => 'Domande *frequenti*',
            'items' => [
                ['id' => 'q1', 'visible' => true, 'question' => "I miei clienti devono scaricare un'app?", 'answer' => '<p>No. Aprono un link dal telefono e, se vogliono, lo aggiungono alla schermata iniziale. Accedono con numero di cellulare e password.</p>'],
                ['id' => 'q2', 'visible' => true, 'question' => 'E se un cliente non è pratico di tecnologia?', 'answer' => '<p>Bastano pochi tocchi su pulsanti grandi. In ogni caso puoi inserire tu la prenotazione al posto suo dal pannello.</p>'],
                ['id' => 'q3', 'visible' => true, 'question' => 'Devo mostrare i prezzi ai clienti?', 'answer' => '<p>Decidi tu: nessun prezzo, prezzo per piatto oppure formule a prezzo fisso.</p>'],
                ['id' => 'q4', 'visible' => true, 'question' => 'Come si contano i clienti?', 'answer' => '<p>Conta solo chi prenota almeno un pasto nel mese. Chi è registrato ma non mangia non si paga.</p>'],
                ['id' => 'q5', 'visible' => true, 'question' => 'Chi vede diete e intolleranze?', 'answer' => "<p>Solo la cucina e l'amministrazione. Il cliente dà il consenso quando si registra.</p>"],
                ['id' => 'q6', 'visible' => true, 'question' => 'Posso disdire quando voglio?', 'answer' => "<p>Sì. Con il piano mensile ti fermi quando vuoi; con l'annuale arrivi alla scadenza dell'anno pagato.</p>"],
            ],
        ],

        'contatti' => [
            'visible' => true,
            'title' => 'Domani mattina, *sai già chi mangia.*',
            'text' => 'Provalo gratis per 7 giorni. Te lo configuriamo noi, con il tuo menù.',
            'label_name' => 'Nome e cognome',
            'label_business' => 'Attività',
            'label_email' => 'Email',
            'label_phone' => 'Telefono',
            'label_clients' => 'Numero indicativo di clienti',
            'clients_options' => "Fino a 40\nDa 41 a 80\nDa 81 a 150\nOltre 150\nNon lo so ancora",
            'label_message' => 'Messaggio',
            'consent_text' => "Ho letto l'{privacy} e acconsento a essere ricontattato per questa richiesta.",
            'consent_link_text' => 'informativa privacy',
            'submit_text' => 'Invia la richiesta',
            'success_text' => 'Grazie, abbiamo ricevuto la tua richiesta. Ti ricontatteremo al più presto.',
            'email_label' => 'Scrivici:',
            'phone_label' => 'Chiamaci:',
            'offer_line' => 'Offerta di lancio per i primi 10 clienti: −{sconto} bloccato per 24 mesi.',
            'photo' => 'esterno',
            'photo_alt' => '',
        ],

        'azienda' => [
            'company_name' => 'City Up Srl',
            'address' => 'Via Ariodante Fabretti 15-17, 06123 Perugia (PG)',
            'vat_number' => '03793880547',
            'email' => '[EMAIL DEL SITO]',
            'phone' => '+39 392 006 1600',
            'facebook' => '',
            'instagram' => '',
            'linkedin' => '',
            'youtube' => '',
            'privacy_label' => 'Privacy',
            'cookie_label' => 'Cookie',
        ],

        'seo' => [
            'title' => 'ChiMangia: prenotazione pasti per ristoranti e mense',
            'description' => 'I tuoi clienti prenotano pranzo e cena dal telefono e scelgono i piatti dal menù del giorno. Tu trovi i conteggi già pronti per la cucina.',
            'og_image' => 0,
            'app_name' => 'ChiMangia',
            'app_description' => 'Piattaforma di prenotazione pasti per ristoranti, trattorie e mense: i clienti prenotano dal telefono e scelgono dal menù del giorno, la cucina trova i conteggi già pronti.',
            'app_category' => 'BusinessApplication',
        ],

        'privacy' => [
            'title' => 'Informativa privacy',
            'body' => '<p><strong>[TESTO DA COMPLETARE] Questa è una bozza segnaposto: va sostituita con l\'informativa redatta o verificata dal consulente privacy prima della messa online.</strong></p>'
                . '<p><strong>Titolare del trattamento</strong><br>[Ragione sociale, indirizzo, partita IVA, email di contatto]</p>'
                . '<p><strong>Dati raccolti</strong><br>[Dati inseriti nel modulo contatti: nome, attività, email, telefono, numero indicativo di clienti, messaggio]</p>'
                . '<p><strong>Finalità e base giuridica</strong><br>[Rispondere alla richiesta di informazioni o di prova gratuita]</p>'
                . '<p><strong>Conservazione</strong><br>[Per quanto tempo vengono conservati i dati]</p>'
                . '<p><strong>Diritti dell\'interessato</strong><br>[Accesso, rettifica, cancellazione, opposizione, reclamo al Garante]</p>',
        ],

        'cookie' => [
            'title' => 'Cookie',
            'body' => '<p><strong>[TESTO DA COMPLETARE] Questa è una bozza segnaposto: va verificata prima della messa online.</strong></p>'
                . '<p>[Il sito pubblico non usa cookie di profilazione né strumenti di terze parti. L\'unico cookie è quello tecnico di sessione del pannello di amministrazione, usato solo dal personale autorizzato.]</p>',
        ],
    ];
}

/** Impostazioni iniziali (non soggette a bozza). */
function default_settings(): array
{
    return [
        'mail_transport' => 'mail',
        'mail_from_email' => '',
        'mail_from_name' => 'ChiMangia',
        'smtp_host' => '',
        'smtp_port' => 587,
        'smtp_encryption' => 'tls',
        'smtp_user' => '',
        'smtp_pass' => '',
        'notify_recipients' => '',
        'retention_months' => 12,
        'maintenance' => false,
        'maintenance_message' => 'Stiamo aggiornando il sito. Torna a trovarci tra poco.',
        'contact_min_seconds' => 4,
        'contact_max_per_hour' => 5,
        'preview_token' => random_token(24),
        'last_publish_at' => '',
    ];
}
