<?php

declare(strict_types=1);

namespace ArcoDelVento\Admin;

/**
 * Come si presentano i testi del sito nell'area riservata.
 *
 * I testi stanno nei file di lingua divisi come comodo al codice (home,
 * cta, common…). Chi li modifica ragiona invece per pagine e per blocchi:
 * «il titolo in alto nella home», «il testo sul padrone di casa». Questa
 * classe fa da traduttore fra le due cose:
 *
 *   - raggruppa le sezioni in tre famiglie: le pagine del sito, le parti
 *     comuni a tutte le pagine, i testi di servizio che si toccano di rado;
 *   - dentro ogni sezione separa i testi importanti (titoli, introduzioni,
 *     testi che si leggono) da quelli secondari (descrizioni delle foto,
 *     etichette per gli screen reader, messaggi dei moduli) e dai testi per
 *     Google;
 *   - dà a ogni testo un nome leggibile e a ogni blocco il nome che ha sulla
 *     pagina, nell'ordine in cui compare;
 *   - sa quale pagina del sito mostrare nell'anteprima.
 *
 * Non salva niente: il salvataggio resta quello di sempre, chiave per chiave.
 */
final class Testi
{
    public const FAMIGLIE = [
        'pagine'  => ['Le pagine del sito', 'I testi che gli ospiti leggono, pagina per pagina.'],
        'comuni'  => ['Parti comuni a tutte le pagine', 'Menu, pulsanti e piè di pagina: cambiano in ogni pagina insieme.'],
        'servizio' => ['Testi di servizio', 'Messaggi dei moduli, nomi dei servizi, privacy: si toccano di rado.'],
    ];

    /**
     * Le sezioni, nell'ordine in cui si presentano.
     *
     * radici: le parti del file di lingua che la sezione raccoglie
     * pagina: la pagina del sito da mostrare nell'anteprima (nome di Routes)
     *
     * @var array<string, array{nome:string, famiglia:string, descrizione:string, radici:list<string>, pagina:string}>
     */
    public const SEZIONI = [
        'home'      => ['nome' => 'Home', 'famiglia' => 'pagine', 'pagina' => 'home', 'radici' => ['home'],
                        'descrizione' => 'Titolo in alto, fotografie, camere, padrone di casa, come arrivare.'],
        'rooms'     => ['nome' => 'Camere', 'famiglia' => 'pagine', 'pagina' => 'rooms', 'radici' => ['rooms'],
                        'descrizione' => 'La pagina con l’elenco delle cinque camere e le tariffe.'],
        'room'      => ['nome' => 'Scheda di una camera', 'famiglia' => 'pagine', 'pagina' => 'room', 'radici' => ['room'],
                        'descrizione' => 'Le parti fisse della pagina di ogni camera.'],
        'property'  => ['nome' => 'La struttura', 'famiglia' => 'pagine', 'pagina' => 'property', 'radici' => ['property'],
                        'descrizione' => 'La casa, Daniele, l’edificio.'],
        'assisi'    => ['nome' => 'Assisi a piedi', 'famiglia' => 'pagine', 'pagina' => 'assisi', 'radici' => ['assisi', 'places'],
                        'descrizione' => 'I sei luoghi a piedi, San Rufino, come muoversi.'],
        'info'      => ['nome' => 'Informazioni', 'famiglia' => 'pagine', 'pagina' => 'info', 'radici' => ['info'],
                        'descrizione' => 'Orari, arrivo, soggiorno, regole della casa.'],
        'contact'   => ['nome' => 'Contatti', 'famiglia' => 'pagine', 'pagina' => 'contact', 'radici' => ['contact'],
                        'descrizione' => 'Il modulo per scrivere e dove siamo.'],
        'book'      => ['nome' => 'Prenotazione', 'famiglia' => 'pagine', 'pagina' => 'book', 'radici' => ['book'],
                        'descrizione' => 'Il percorso di prenotazione, passo per passo, e le e-mail.'],
        'nav'       => ['nome' => 'Menu', 'famiglia' => 'comuni', 'pagina' => 'home', 'radici' => ['nav'],
                        'descrizione' => 'Le voci del menu e la barra in cima.'],
        'cta'       => ['nome' => 'Pulsanti e inviti', 'famiglia' => 'comuni', 'pagina' => 'home', 'radici' => ['cta'],
                        'descrizione' => '«Prenota ora», «Scopri le camere» e gli altri pulsanti.'],
        'footer'    => ['nome' => 'Piè di pagina', 'famiglia' => 'comuni', 'pagina' => 'home', 'radici' => ['footer'],
                        'descrizione' => 'La fascia in fondo a ogni pagina.'],
        'common'    => ['nome' => 'Parole ricorrenti', 'famiglia' => 'servizio', 'pagina' => 'home', 'radici' => ['common'],
                        'descrizione' => '«a notte», «ospiti», «da confermare»…'],
        'errors'    => ['nome' => 'Errori dei moduli', 'famiglia' => 'servizio', 'pagina' => 'contact', 'radici' => ['errors'],
                        'descrizione' => 'I messaggi quando un campo non va.'],
        'privacy'   => ['nome' => 'Privacy', 'famiglia' => 'servizio', 'pagina' => 'privacy', 'radici' => ['privacy'],
                        'descrizione' => 'L’informativa sui dati.'],
        'not_found' => ['nome' => 'Pagina non trovata', 'famiglia' => 'servizio', 'pagina' => 'home', 'radici' => ['not_found'],
                        'descrizione' => 'Quando un indirizzo non esiste.'],
        'amenities' => ['nome' => 'Servizi delle camere', 'famiglia' => 'servizio', 'pagina' => 'room', 'radici' => ['amenities', 'bathroom'],
                        'descrizione' => 'Wi-Fi, bollitore, bagno privato…'],
        'room_types' => ['nome' => 'Tipologie, letti e viste', 'famiglia' => 'servizio', 'pagina' => 'rooms', 'radici' => ['room_types', 'layouts', 'views'],
                        'descrizione' => 'Matrimoniale, tripla, «Sui vicoli di Assisi»…'],
    ];

    /** I vecchi indirizzi delle sezioni confluite in un'altra. */
    public const ALIAS = ['places' => 'assisi', 'bathroom' => 'amenities', 'layouts' => 'room_types', 'views' => 'room_types', 'beds' => 'room_types'];

    /** I nomi dei blocchi, come compaiono sulla pagina. */
    private const BLOCCHI = [
        'home.hero' => 'In alto: il titolo della pagina',
        'home.slides' => 'Le fotografie che si alternano',
        'home.trust' => 'La riga sotto la barra di prenotazione',
        'home.manifesto' => 'La frase sotto le fotografie',
        'home.rooms' => 'Le camere',
        'home.position' => 'Il centro storico, a piedi',
        'home.reviews' => 'Le recensioni',
        'home.host' => 'Il padrone di casa',
        'home.arrive' => 'Come arrivare (la mappa)',
        'home.walk' => 'La fotografia di San Francesco',
        'home.faq' => 'Le domande frequenti (il titolo)',
        'home.closing' => 'L’invito a prenotare, in fondo a ogni pagina',
        'rooms.table' => 'La tabella delle tariffe',
        'rooms.card' => 'Le schede delle camere',
        'rooms.status' => 'Lo stato della camera nella ricerca',
        'room.meta' => 'Le caratteristiche',
        'info.sections' => 'I titoli dei riquadri',
        'info.items' => 'Le etichette delle righe',
        'info.known' => 'Le risposte: quello che c’è scritto accanto a ogni etichetta',
        'book.steps' => 'I quattro passaggi',
        'book.search' => 'La ricerca delle date',
        'book.results' => 'Le camere libere',
        'book.details' => 'I dati di chi prenota',
        'book.done' => 'Richiesta inviata',
        'book.pay' => 'Il pagamento online',
        'book.mail' => 'L’e-mail di conferma all’ospite',
        'book.summary' => 'Il riepilogo',
        'contact.form' => 'Il modulo',
        'nav.topbar' => 'La barra scura in cima',
        'privacy.blocks' => 'I paragrafi dell’informativa',
        'places' => 'I sei luoghi a piedi',
    ];

    /** I nomi delle parole finali di una chiave, come li direbbe chi scrive. */
    private const NOMI = [
        'eyebrow' => 'Occhiello (la riga piccola sopra il titolo)',
        'title' => 'Titolo', 'sign' => 'Parola in corsivo, alla fine del titolo',
        'lead' => 'Testo introduttivo', 'text' => 'Testo', 'author' => 'Firma',
        'image_alt' => 'Descrizione della fotografia (per chi non vede)',
        'image_credit' => 'Crediti della fotografia', 'frame_alt' => 'Descrizione della fotografia (per chi non vede)',
        'corridor_alt' => 'Descrizione della foto del corridoio', 'corridor_caption' => 'Didascalia della foto del corridoio',
        'caption' => 'Didascalia', 'note' => 'Nota', 'label' => 'Etichetta per gli screen reader',
        'name' => 'Nome', 'time' => 'Tempo, al posto dei minuti', 'help' => 'Aiuto sotto il campo',
        'seo_title' => 'Titolo nella scheda del browser e su Google',
        'seo_description' => 'Descrizione su Google', 'seo_description_pay' => 'Descrizione su Google (con il pagamento online)',
        'prev' => 'Pulsante «indietro» (per gli screen reader)', 'next' => 'Pulsante «avanti» (per gli screen reader)',
        'pause' => 'Pulsante pausa (per gli screen reader)', 'play' => 'Pulsante riprendi (per gli screen reader)',
        'scroll' => 'Freccia verso il basso (per gli screen reader)', 'new_tab' => 'Avviso «si apre in una nuova scheda»',
        'vertical' => 'Scritta verticale accanto alla foto', 'chip' => 'Etichetta sulla foto',
        'tagline' => 'Frase breve', 'discover' => 'Link «tutte»', 'map' => 'Pulsante della mappa',
        'place' => 'Luogo', 'lead_pay' => 'Testo introduttivo (con il pagamento online)',
        'submit' => 'Pulsante di invio', 'legend' => 'Titolo del modulo', 'privacy' => 'Spunta della privacy',
        'subject' => 'Oggetto', 'message' => 'Messaggio', 'message_help' => 'Aiuto sotto il messaggio',
        'success_title' => 'Messaggio inviato: titolo', 'success_text' => 'Messaggio inviato: testo',
        'error_title' => 'Errore: titolo', 'error_text' => 'Errore: testo',
        'demo_title' => 'Avviso di prova: titolo', 'demo_text' => 'Avviso di prova: testo', 'demo_note' => 'Avviso di prova',
        'notice_title' => 'Avviso: titolo', 'notice_text' => 'Avviso: testo',
        'places_title' => 'Titolo dell’elenco dei luoghi', 'places_note' => 'Nota sotto l’elenco dei luoghi',
        'rufino_title' => 'Titolo su San Rufino', 'rufino_text' => 'Testo su San Rufino',
        'moving_title' => 'Titolo «Muoversi»', 'moving_text' => 'Testo «Muoversi» (anche nella pagina Contatti)',
        'host_title' => 'Titolo su Daniele', 'host_text' => 'Testo su Daniele', 'host_role' => 'Ruolo, sotto il nome',
        'host_bio' => 'Biografia nella scheda di Daniele', 'what_title' => 'Titolo «Che cosa vuol dire affittacamere»',
        'what_text' => 'Testo «Che cosa vuol dire affittacamere»', 'city_title' => 'Titolo «Stare dentro la città»',
        'city_text' => 'Testo «Stare dentro la città»', 'building_title' => 'Titolo «L’edificio»',
        'building_text' => 'Testo «L’edificio»', 'where_title' => 'Titolo «Dove siamo»', 'how_title' => 'Titolo «Come raggiungerci»',
        'form_title' => 'Titolo del modulo', 'list_title' => 'Titolo dell’elenco', 'list_sign' => 'Parola in corsivo dell’elenco',
        'rates_title' => 'Titolo delle tariffe', 'rates_hidden' => 'Testo al posto delle tariffe',
        'rates_note' => 'Nota sulle tariffe', 'rates_caption' => 'Didascalia della tabella',
        'price_on_dates' => '«Tariffa con le date» (nelle schede)', 'price_on_dates_long' => '«Tariffa con le date» (nella camera)',
        'rights' => 'Copyright', 'credits' => 'Crediti', 'photo_credits' => 'Crediti delle fotografie',
        'back_top' => 'Link «Torna su»', 'legal_note' => 'Nota sui dati di legge',
        'parking_label' => 'Parcheggio: etichetta', 'parking_value' => 'Parcheggio: testo',
        'train_label' => 'Treno: etichetta', 'train_value' => 'Treno: testo',
        'plane_label' => 'Aereo: etichetta', 'plane_value' => 'Aereo: testo',
        'navigator_label' => 'Navigatore: etichetta', 'map_label' => 'Nome della mappa (per gli screen reader)',
        'links_title' => 'Titolo dei link utili', 'link_parking' => 'Link al parcheggio', 'link_bus' => 'Link agli autobus',
        'link_airlink' => 'Link all’Airlink',
    ];

    /** Le parti di una sezione che sono tutte dettagli di funzionamento. */
    private const SECONDARI = ['slides', 'table', 'card', 'status', 'steps', 'search', 'results', 'details',
        'done', 'pay', 'mail', 'summary', 'form', 'meta', 'reviews', 'items', 'sections'];

    /** Le ultime parole di chiave che indicano un dettaglio. */
    private const DETTAGLI = ['label', 'prev', 'next', 'pause', 'play', 'scroll', 'new_tab', 'vertical', 'chip',
        'image_alt', 'frame_alt', 'corridor_alt', 'image_credit', 'demo_title', 'demo_text', 'demo_note',
        'notice_title', 'notice_text', 'step_of', 'name_to_confirm', 'not_found_title', 'not_found_text',
        'photo_pending', 'map_label', 'skip', 'menu_open', 'menu_close',
        'loading', 'close', 'back', 'demo_asset', 'to_confirm', 'price_to_confirm', 'legal_note', 'credits',
        'back_top', 'discover', 'tagline'];

    public static function sezione(string $chiave): ?array
    {
        $chiave = self::ALIAS[$chiave] ?? $chiave;

        return isset(self::SEZIONI[$chiave]) ? self::SEZIONI[$chiave] + ['chiave' => $chiave] : null;
    }

    /**
     * Importante, Google o secondario.
     *
     * @return 'importanti'|'google'|'secondari'
     */
    public static function livello(string $chiave): string
    {
        $pezzi  = explode('.', $chiave);
        $ultima = (string) end($pezzi);
        $radice = $pezzi[0];

        if (str_starts_with($ultima, 'seo_')) {
            return 'google';
        }
        // Le sezioni di servizio sono secondarie per intero.
        $sezione = self::SEZIONI[self::ALIAS[$radice] ?? $radice] ?? null;
        if ($sezione !== null && $sezione['famiglia'] === 'servizio') {
            return 'secondari';
        }
        if (in_array($ultima, self::DETTAGLI, true) || str_ends_with($ultima, '_alt')) {
            return 'secondari';
        }
        // Le risposte della pagina Informazioni sono il contenuto della pagina.
        if (str_starts_with($chiave, 'info.known.') || $radice === 'places') {
            return 'importanti';
        }
        if (count($pezzi) > 2 && in_array($pezzi[1], self::SECONDARI, true)) {
            return 'secondari';
        }
        // Menu, pulsanti e piè di pagina: sono testi che si vedono.
        if (in_array($radice, ['nav', 'cta', 'footer'], true)) {
            return 'importanti';
        }
        if (in_array($ultima, ['eyebrow', 'title', 'sign', 'lead', 'text', 'author', 'place'], true)
            || str_ends_with($ultima, '_title') || str_ends_with($ultima, '_text')
            || str_ends_with($ultima, '_value') || str_ends_with($ultima, '_bio') || str_ends_with($ultima, '_role')
            || str_ends_with($ultima, '_caption') || $ultima === 'map') {
            return 'importanti';
        }

        return 'secondari';
    }

    /** Il blocco della pagina in cui sta un testo. */
    public static function blocco(string $chiave): string
    {
        $pezzi = explode('.', $chiave);
        if ($pezzi[0] === 'places') {
            return self::BLOCCHI['places'];
        }
        if (count($pezzi) > 2) {
            $gruppo = $pezzi[0] . '.' . $pezzi[1];
            if (isset(self::BLOCCHI[$gruppo])) {
                return self::BLOCCHI[$gruppo];
            }
            if ($pezzi[0] === 'privacy' && $pezzi[1] === 'blocks') {
                return self::BLOCCHI['privacy.blocks'];
            }

            return ucfirst(str_replace('_', ' ', $pezzi[1]));
        }

        return 'Intestazione e testi della pagina';
    }

    /** Il nome leggibile di un testo. */
    public static function nome(string $chiave): string
    {
        $pezzi  = explode('.', $chiave);
        $ultima = (string) end($pezzi);
        if ($pezzi[0] === 'places' && count($pezzi) === 3) {
            return ($ultima === 'name' ? 'Nome' : ($ultima === 'time' ? 'Tempo' : 'Nota')) . ' — ' . str_replace('-', ' ', $pezzi[1]);
        }
        if ($pezzi[0] === 'privacy' && ($pezzi[1] ?? '') === 'blocks' && count($pezzi) === 4) {
            return ($ultima === 'title' ? 'Titolo' : 'Testo') . ' — ' . str_replace('_', ' ', $pezzi[2]);
        }
        if (isset(self::NOMI[$ultima])) {
            return self::NOMI[$ultima];
        }

        return ucfirst(str_replace(['_', '-'], ' ', $ultima));
    }
}
