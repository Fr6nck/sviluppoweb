<?php
namespace MHW;

/**
 * I testi e le foto della home (e dell'intestazione di /domande), modificabili da
 * Amministrazione → Sistema → «Testi e foto». Ogni testo ha una chiave, una sezione, un
 * tipo (riga, area, lista) e il valore di serie: quello che c'era scritto nei file. Le righe
 * salvate stanno in site_texts (migrazione 033); un testo uguale a quello di serie non si salva.
 *
 * Nei testi si possono usare **grassetto** e [testo](link): html() li converte in HTML sicuro
 * dopo aver neutralizzato tutto il resto. I segnaposto ({sezioni}, {demo}, {max}…) li
 * sostituisce la pagina che li usa, con valori già sicuri.
 *
 * Le foto (IMMAGINI) si caricano nell'archivio come le foto delle testimonianze (senza riga
 * in media); il riferimento «driver:chiave» sta in site_texts con la chiave img.<nome>.
 */
final class TestiSito
{
    public const RIGA_MAX = 200, AREA_MAX = 3000;

    /** Le sezioni della pagina di amministrazione, nell'ordine della home. */
    public const SEZIONI = [
        'hero'     => ['In cima alla pagina', 'Titolo, sottotitolo e bottoni: la prima cosa che si legge.'],
        'scene'    => ['Le tre scene', 'Le tre foto sotto il titolo: il QR, l\'ospite, l\'host. Le foto si cambiano in fondo a questa pagina.'],
        'prodotto' => ['Cosa trova l\'ospite', ''],
        'perchi'   => ['Per chi è', 'Le quattro schede: casa vacanza, B&B, agriturismo, più strutture.'],
        'tempo'    => ['Il tempo delle risposte', 'I numeri (16, 19 minuti…) restano quelli: qui si cambiano i testi intorno.'],
        'guadagno' => ['La guida che lavora per te', ''],
        'come'     => ['Come funziona', 'I tre passi con le schermate del pannello.'],
        'qr'       => ['Un QR. Tutta la struttura.', ''],
        'voci'     => ['Chi la usa', 'Il blocco compare solo con almeno una testimonianza visibile (Clienti → Testimonianze).'],
        'piani'    => ['Piani e prezzi', 'Prezzi, nomi e voci dei piani si cambiano in Vendite → Piani.'],
        'amico'    => ['Porta un amico', 'Compare solo con gli inviti accesi (Impostazioni). Le percentuali arrivano dal programma: {amico} è lo sconto dell\'amico, {passo} quello per ogni amico, {max} il massimo (per esempio 50%).'],
        'faq'      => ['Domande', 'Le domande e le risposte sono nei file (Faq.php); qui l\'intestazione, anche della pagina /domande.'],
        'chiusura' => ['In fondo: comincia, e chi c\'è dietro', ''],
        'sito'     => ['Anche il tuo sito', 'Il blocco scuro in fondo, con la foto del soggiorno.'],
    ];

    /**
     * Il catalogo: chiave => [sezione, etichetta, tipo, testo di serie, nota].
     * tipo: riga (una riga), area (più righe), lista (una voce per riga).
     * @return array<string,array{0:string,1:string,2:string,3:string,4:string}>
     */
    public static function catalogo(): array
    {
        static $c = null;
        if ($c !== null) return $c;
        $c = [
            // ---- in cima
            'hero.kicker'   => ['hero', 'Riga sopra il titolo', 'riga', 'La reception digitale per la tua struttura ricettiva', ''],
            'hero.titolo'   => ['hero', 'Titolo', 'area', "La casa risponde\nprima che chiedano.", 'Un a capo = una riga nuova.'],
            'hero.sotto'    => ['hero', 'Sottotitolo', 'area', 'La guida digitale per case vacanza, B&B, affittacamere e agriturismi. Check-in, Wi-Fi, parcheggio, regole e i tuoi consigli sulla zona: tutto accessibile tramite QR Code. Senza app da scaricare.', ''],
            'cta.crea'      => ['hero', 'Bottone principale', 'riga', 'Crea gratis la tua guida', 'Lo stesso bottone torna in «La guida che lavora per te», «Porta un amico» e in fondo alla pagina. Chi è già registrato legge «Vai alle tue guide».'],
            'cta.demo'      => ['hero', 'Bottone della demo', 'riga', 'Guarda la demo', ''],
            'hero.garanzie' => ['hero', 'Le tre garanzie sotto i bottoni', 'lista', "Nessuna app da scaricare\nLa crei e la provi gratis\nPaghi solo quando pubblichi", 'Una per riga.'],
            'hero.invito'   => ['hero', 'Invito sotto il telefono', 'riga', 'Scopri come la vedranno i tuoi ospiti', 'Compare solo se c\'è una demo.'],
            // ---- scene
            'scena.qr.titolo'     => ['scene', 'Prima scena · titolo', 'riga', 'Il QR all\'ingresso', ''],
            'scena.qr.testo'      => ['scene', 'Prima scena · testo', 'area', 'Lo stampi una volta: l\'ospite lo inquadra e la guida si apre.', ''],
            'scena.qr.alt'        => ['scene', 'Prima scena · descrizione della foto', 'riga', 'Un ospite inquadra con il telefono il QR in cornice accanto alla porta d\'ingresso', 'Per chi non vede la foto (lettori di schermo) e per i motori.'],
            'scena.ospite.titolo' => ['scene', 'Seconda scena · titolo', 'riga', 'L\'ospite trova tutto', ''],
            'scena.ospite.testo'  => ['scene', 'Seconda scena · testo', 'area', 'Wi-Fi, check-in, consigli: nella sua lingua, sul suo telefono.', ''],
            'scena.ospite.alt'    => ['scene', 'Seconda scena · descrizione della foto', 'riga', 'Un\'ospite al tavolo della casa sfoglia la guida sul telefono: Wi-Fi, check-in, parcheggio, dove mangiare', ''],
            'scena.host.titolo'   => ['scene', 'Terza scena · titolo', 'riga', 'Tu aggiorni quando vuoi', ''],
            'scena.host.testo'    => ['scene', 'Terza scena · testo', 'area', 'Cambi un orario dal pannello e pubblichi: il QR resta lo stesso.', ''],
            'scena.host.alt'      => ['scene', 'Terza scena · descrizione della foto', 'riga', 'L\'host aggiorna la guida dal portatile, con l\'anteprima sul telefono accanto', ''],
            // ---- prodotto
            'prodotto.kicker' => ['prodotto', 'Riga sopra il titolo', 'riga', 'La guida', ''],
            'prodotto.titolo' => ['prodotto', 'Titolo', 'riga', 'Cosa trova l\'ospite.', ''],
            'prodotto.testo'  => ['prodotto', 'Testo', 'area', 'Le informazioni del soggiorno, in ordine e sempre sul telefono. {sezioni} sezioni pronte da compilare, più le sezioni libere per tutto il resto: scegli quelle che servono ai tuoi ospiti.', '{sezioni} diventa il numero di sezioni del catalogo.'],
            'prodotto.link'   => ['prodotto', 'Link alla demo', 'riga', 'Sfoglia la guida di {demo}', '{demo} è il nome della guida dimostrativa.'],
            // ---- per chi è
            'perchi.kicker'   => ['perchi', 'Riga sopra il titolo', 'riga', 'Per chi è', ''],
            'perchi.titolo'   => ['perchi', 'Titolo', 'riga', 'Ogni struttura ha le sue domande.', ''],
            'perchi.1.titolo' => ['perchi', 'Scheda 1 · titolo', 'riga', 'Casa vacanza', ''],
            'perchi.1.testo'  => ['perchi', 'Scheda 1 · testo', 'area', 'Non ci sei quando arrivano? La guida spiega come si entra, dove si parcheggia e come funziona la casa, a qualsiasi ora.', ''],
            'perchi.2.titolo' => ['perchi', 'Scheda 2 · titolo', 'riga', 'B&B e affittacamere', ''],
            'perchi.2.testo'  => ['perchi', 'Scheda 2 · testo', 'area', 'Colazione, orari, regole: un QR Code sempre aggiornato in ogni camera, al posto del foglio plastificato.', ''],
            'perchi.3.titolo' => ['perchi', 'Scheda 3 · titolo', 'riga', 'Agriturismo', ''],
            'perchi.3.testo'  => ['perchi', 'Scheda 3 · testo', 'area', 'Racconta l\'azienda, le degustazioni e i tuoi prodotti. E accompagna gli ospiti tra sagre, sentieri e cantine dei dintorni.', ''],
            'perchi.4.titolo' => ['perchi', 'Scheda 4 · titolo', 'riga', 'Più strutture', ''],
            'perchi.4.testo'  => ['perchi', 'Scheda 4 · testo', 'area', 'Gestisci più strutture? Un QR Code per ognuna, dallo stesso account. Quello che vale per tutte lo scrivi una volta sola e lo incolli.', ''],
            // ---- il tempo
            'tempo.kicker'      => ['tempo', 'Riga sopra il titolo', 'riga', 'Il tempo delle risposte', ''],
            'tempo.titolo'      => ['tempo', 'Titolo', 'area', 'L\'accoglienza resta tua. Rispondere sempre alle stesse domande, no.', ''],
            'tempo.lead'        => ['tempo', 'Testo sotto il titolo', 'area', 'Il contatto con l\'ospite è la parte più bella di questo lavoro, e una parte resta tua. L\'altra è fatta di domande a cui hai già risposto cento volte. Su una prenotazione tipo, i conti sono questi.', ''],
            'tempo.totale'      => ['tempo', 'Sopra la barra', 'riga', 'Una prenotazione: {tot} minuti di messaggi', '{tot} è la somma dei minuti (si aggiorna con i cursori).'],
            'tempo.nota'        => ['tempo', 'Nota accanto alla barra', 'riga', 'gestiti a mano, dalla conferma alla partenza', ''],
            'tempo.nec.titolo'  => ['tempo', 'Colonna sinistra · titolo', 'riga', 'Comunicazioni importanti e necessarie', ''],
            'tempo.nec.desc'    => ['tempo', 'Colonna sinistra · testo', 'riga', 'Restano tue: è qui che si fa l\'accoglienza.', ''],
            'tempo.nec.lista'   => ['tempo', 'Colonna sinistra · elenco', 'lista', "Accordi prima dell'arrivo\nImprevisti al check-in\nRichieste particolari durante il soggiorno\nAccordi per la partenza", 'Una voce per riga.'],
            'tempo.evit.titolo' => ['tempo', 'Colonna destra · titolo', 'riga', 'Domande evitabili con una guida', ''],
            'tempo.evit.desc'   => ['tempo', 'Colonna destra · testo', 'riga', 'Servono all\'ospite, ma non richiedono il tuo tempo se le trova scritte.', ''],
            'tempo.evit.lista'  => ['tempo', 'Colonna destra · elenco', 'lista', "Accesso, parcheggio, Wi-Fi\nLa casa e gli elettrodomestici\nRistoranti, trasporti, cosa vedere\nOrari e istruzioni per la partenza", 'Una voce per riga.'],
            'tempo.conto.titolo' => ['tempo', 'Riquadro del conto · titolo', 'riga', 'Fai il conto sulla tua struttura', ''],
            'tempo.conto.pren'   => ['tempo', 'Riquadro del conto · primo cursore', 'riga', 'Prenotazioni al mese', ''],
            'tempo.conto.ora'    => ['tempo', 'Riquadro del conto · secondo cursore', 'riga', 'Quanto vale un\'ora del tuo tempo', ''],
            'tempo.risultato.kicker'  => ['tempo', 'Riquadro rosso · riga in alto', 'riga', 'Tempo risparmiato in un anno', ''],
            'tempo.risultato.frase'   => ['tempo', 'Riquadro rosso · frase sotto la cifra', 'riga', 'Il tempo che ti restituisce la guida, in un anno.', ''],
            'tempo.risultato.piccolo' => ['tempo', 'Riquadro rosso · nota in piccolo', 'riga', 'Contiamo la metà dei tuoi minuti di domande evitabili: qualche ospite chiederà comunque.', ''],
            'tempo.prova1.num'   => ['tempo', 'Primo dato · numero', 'riga', '60–70%', ''],
            'tempo.prova1.testo' => ['tempo', 'Primo dato · testo', 'riga', 'dei messaggi ricevuti dagli host italiani chiede informazioni ripetitive.', ''],
            'tempo.prova2.num'   => ['tempo', 'Secondo dato · numero', 'riga', '88,7%', ''],
            'tempo.prova2.testo' => ['tempo', 'Secondo dato · testo', 'riga', 'degli ospiti vuole istruzioni dettagliate su accesso e parcheggio prima di arrivare.', ''],
            'tempo.fonti'        => ['tempo', 'Le fonti, in piccolo', 'area', '35 minuti: stima di MyHouse Welcome su una prenotazione tipo, dentro le stime pubblicate nel 2026 (stime pubblicate nel 2026 da aziende del settore, tra cui Guestar su dati StayReply). I riferimenti di 16 e 19 minuti sono la nostra ripartizione di quei 35 minuti, coerente con il 60–70% di messaggi ripetitivi rilevato da Verto AI; il tempo risparmiato è un\'ipotesi prudente. 60–70%: Verto AI, aprile 2026, conversazioni di gestori italiani. 88,7%: sondaggio Touch Stay tra gli ospiti, 2026. Stime di aziende del settore, non statistiche ufficiali.', ''],
            // ---- la guida che lavora per te
            'guadagno.kicker'   => ['guadagno', 'Riga sopra il titolo', 'riga', 'La guida che lavora per te', ''],
            'guadagno.titolo'   => ['guadagno', 'Titolo', 'area', 'Una prenotazione diretta in più all\'anno paga l\'abbonamento.', ''],
            'guadagno.lead'     => ['guadagno', 'Testo sotto il titolo', 'area', 'Alla fine del soggiorno la guida digitale manda due inviti all\'ospite: una recensione sulla piattaforma che preferisci e un codice sconto da usare la prossima volta, direttamente sul tuo sito.', ''],
            'guadagno.1.titolo' => ['guadagno', 'Voce 1 · titolo', 'riga', 'Più recensioni.', ''],
            'guadagno.1.testo'  => ['guadagno', 'Voce 1 · testo', 'area', 'Google, Booking, Airbnb: un pulsante per ognuno, quando il ricordo è fresco.', ''],
            'guadagno.2.titolo' => ['guadagno', 'Voce 2 · titolo', 'riga', 'Prenotazioni dirette.', ''],
            'guadagno.2.testo'  => ['guadagno', 'Voce 2 · testo', 'area', '«La prossima volta prenota da noi», con il link al tuo sito e il codice sconto.', ''],
            'guadagno.3.titolo' => ['guadagno', 'Voce 3 · titolo', 'riga', 'Servizi extra.', ''],
            'guadagno.3.testo'  => ['guadagno', 'Voce 3 · testo', 'area', 'Transfer, colazione, late check-out: l\'ospite li chiede con un tocco su WhatsApp.', ''],
            // ---- come funziona
            'come.kicker'   => ['come', 'Riga sopra il titolo', 'riga', 'Come funziona', ''],
            'come.titolo'   => ['come', 'Titolo', 'riga', 'Inizia in pochi minuti.', ''],
            'come.1.titolo' => ['come', 'Passo 1 · titolo', 'riga', 'Crea la tua guida.', ''],
            'come.1.testo'  => ['come', 'Passo 1 · testo', 'area', 'Inserisci le informazioni della struttura e scegli cosa condividere con gli ospiti.', ''],
            'come.1.alt'    => ['come', 'Passo 1 · descrizione della schermata', 'riga', 'Il pannello: i contenuti della guida, con le sezioni e le statistiche', ''],
            'come.2.titolo' => ['come', 'Passo 2 · titolo', 'riga', 'Personalizza e guarda l\'anteprima.', ''],
            'come.2.testo'  => ['come', 'Passo 2 · testo', 'area', 'Scegli i colori e la copertina, e guarda la guida sul telefono come la vedranno gli ospiti, prima di pubblicarla.', ''],
            'come.2.alt'    => ['come', 'Passo 2 · descrizione della schermata', 'riga', 'Il pannello: l\'aspetto della guida, con palette, tema e copertina', ''],
            'come.3.titolo' => ['come', 'Passo 3 · titolo', 'riga', 'Pubblica e condividi.', ''],
            'come.3.testo'  => ['come', 'Passo 3 · testo', 'area', 'Attivi l\'abbonamento: la guida va online e la condividi con il link o con il QR Code.', ''],
            'come.3.alt'    => ['come', 'Passo 3 · descrizione della schermata', 'riga', 'Il pannello: il QR Code da stampare e il link da condividere', ''],
            // ---- QR
            'qr.titolo'   => ['qr', 'Titolo', 'riga', 'Un QR. Tutta la struttura.', ''],
            'qr.1.titolo' => ['qr', 'Punto 1 · in grassetto', 'riga', 'Un solo QR Code, sempre valido.', ''],
            'qr.1.testo'  => ['qr', 'Punto 1 · testo', 'riga', 'Lo stampi una volta e non lo cambi più.', ''],
            'qr.2.titolo' => ['qr', 'Punto 2 · in grassetto', 'riga', 'Informazioni sempre aggiornabili.', ''],
            'qr.2.testo'  => ['qr', 'Punto 2 · testo', 'riga', 'Modifichi la guida e pubblichi la nuova versione senza cambiare il QR.', ''],
            'qr.3.titolo' => ['qr', 'Punto 3 · in grassetto', 'riga', 'Condividi anche prima dell\'arrivo.', ''],
            'qr.3.testo'  => ['qr', 'Punto 3 · testo', 'riga', 'Mandi il link su WhatsApp o per email, con il messaggio di benvenuto già pronto.', ''],
            // ---- chi la usa
            'voci.kicker' => ['voci', 'Riga sopra il titolo', 'riga', 'Chi la usa', ''],
            'voci.titolo' => ['voci', 'Titolo', 'riga', 'Le parole di chi ospita.', ''],
            // ---- piani
            'piani.kicker' => ['piani', 'Riga sopra il titolo', 'riga', 'Piani e prezzi', ''],
            'piani.titolo' => ['piani', 'Titolo', 'riga', 'Scegli il piano.', ''],
            'piani.lead'   => ['piani', 'Testo accanto al titolo', 'area', 'Crei e provi la guida gratis, con le funzioni del piano che scegli: paghi solo quando la pubblichi. Prezzi IVA esclusa.', ''],
            'piani.nota'   => ['piani', 'Nota sotto le schede', 'area', 'Abbonamento annuale con rinnovo automatico. Puoi disattivare il rinnovo dal tuo account quando vuoi: la guida resta online fino alla fine del periodo già pagato.', ''],
            // ---- porta un amico
            'amico.kicker'   => ['amico', 'Riga sopra il titolo', 'riga', 'Porta un amico', ''],
            'amico.titolo'   => ['amico', 'Titolo', 'area', "Più amici porti, meno paghi.\nIl prossimo anno fino al {max} in meno.", '{max} è lo sconto massimo, in evidenza.'],
            'amico.lead'     => ['amico', 'Testo sotto il titolo', 'area', 'Conosci altri host, B&B o case vacanza? Mandagli il tuo link: loro hanno il {amico} di sconto sul primo anno, tu il {passo} in meno sul rinnovo per ogni amico che si abbona.', ''],
            'amico.1.titolo' => ['amico', 'Passo 1 · in grassetto', 'riga', 'Pubblichi la tua guida.', ''],
            'amico.1.testo'  => ['amico', 'Passo 1 · testo', 'riga', 'In «Invita un amico» trovi il tuo link personale.', ''],
            'amico.2.titolo' => ['amico', 'Passo 2 · in grassetto', 'riga', 'L\'amico si registra dal link.', ''],
            'amico.2.testo'  => ['amico', 'Passo 2 · testo', 'riga', 'Ha il {amico} di sconto sul primo anno, da solo.', ''],
            'amico.3.titolo' => ['amico', 'Passo 3 · in grassetto', 'riga', 'Quando paga, tu risparmi.', ''],
            'amico.3.testo'  => ['amico', 'Passo 3 · testo', 'riga', 'Il tuo rinnovo costa il {passo} in meno per ogni amico, fino al {max}.', ''],
            'amico.bottone'  => ['amico', 'Bottone «Come funziona»', 'riga', 'Come funziona', 'Apre la domanda «Come funziona «Porta un amico»?».'],
            // ---- domande
            'faq.kicker'    => ['faq', 'Riga sopra il titolo (home)', 'riga', 'Domande', ''],
            'faq.titolo'    => ['faq', 'Titolo (home)', 'riga', 'Prima di cominciare.', ''],
            'domande.titolo' => ['faq', 'Titolo della pagina /domande', 'riga', 'Domande frequenti', ''],
            'faq.aiuto'     => ['faq', 'Invito a scrivere', 'riga', 'Non trovi la risposta? Scrivici, ti rispondiamo volentieri.', 'In home e in /domande.'],
            'faq.oppure'    => ['faq', 'Prima dell\'indirizzo email', 'riga', 'Oppure scrivi a', ''],
            'faq.altre'     => ['faq', 'Etichetta «Altre domande»', 'riga', 'Altre domande', 'Dopo, tra parentesi, quante sono.'],
            'faq.tutte'     => ['faq', 'Link a tutte le domande', 'riga', 'Tutte le domande', ''],
            // ---- chiusura
            'chiusura.titolo' => ['chiusura', 'Titolo', 'area', 'La tua struttura ha tanto da raccontare. Mettilo a disposizione dei tuoi ospiti.', ''],
            'chiusura.testo'  => ['chiusura', 'Testo', 'area', 'Crea la tua guida, personalizzala e guarda il risultato. Decidi soltanto dopo se pubblicarla: per cominciare non serve la carta di credito.', ''],
            'chi.titolo'      => ['chiusura', 'Riquadro «chi c\'è dietro» · titolo', 'area', 'Pensata per chi ospita. Sviluppata da chi lavora nel digitale e nell\'ospitalità.', ''],
            'chi.testo'       => ['chiusura', 'Riquadro «chi c\'è dietro» · testo', 'area', 'MyHouse Welcome fa parte delle soluzioni MyHouse di [Blackout Agency](https://blackout.in), dedicate alle esigenze digitali delle strutture ricettive.', ''],
            // ---- anche il tuo sito
            'sito.kicker'   => ['sito', 'Riga sopra il titolo', 'riga', 'Anche il tuo sito', ''],
            'sito.titolo'   => ['sito', 'Titolo', 'area', "Meno commissioni ai portali.\nPiù incasso per te.", ''],
            'sito.accento'  => ['sito', 'Ultima riga del titolo, in arancione', 'riga', 'Più ospiti diretti.', ''],
            'sito.lead'     => ['sito', 'Testo sotto il titolo', 'area', 'Oltre alla guida, realizziamo il sito internet della tua struttura: le tue foto, le date libere, la prenotazione diretta.', ''],
            'sito.1.titolo' => ['sito', 'Scheda 1 · titolo', 'riga', 'Meno commissioni ai portali.', ''],
            'sito.1.testo'  => ['sito', 'Scheda 1 · testo', 'area', 'Sui portali ogni notte venduta ha un costo: una percentuale che va a chi ti ha fatto da intermediario. Sulle prenotazioni che arrivano dal tuo sito l\'intermediario non c\'è, e quella percentuale non la paghi.', ''],
            'sito.2.titolo' => ['sito', 'Scheda 2 · titolo', 'riga', 'Più incasso per te.', ''],
            'sito.2.testo'  => ['sito', 'Scheda 2 · testo', 'area', 'Stessa casa, stessa notte, stesso prezzo. Cambia solo dove viene venduta: se la vendi dal tuo sito, la quota che sarebbe andata al portale resta a te.', ''],
            'sito.3.titolo' => ['sito', 'Scheda 3 · titolo', 'riga', 'Più ospiti diretti.', ''],
            'sito.3.testo'  => ['sito', 'Scheda 3 · testo', 'area', 'Il tuo sito è visibile a chiunque cerchi online un alloggio nella tua zona, anche a chi non ti ha mai sentito nominare. Arriva da te, guarda le foto, vede le date libere e prenota. Senza passare da un portale.', ''],
            'sito.bottone'  => ['sito', 'Bottone', 'riga', 'Chiedici il tuo sito', 'Porta a blackout.in/myhouse.'],
            'sito.nota'     => ['sito', 'Nota accanto ai bottoni', 'riga', 'Ti rispondiamo con una proposta su misura.', 'Dopo, l\'indirizzo email di contatto.'],
        ];
        return $c;
    }

    /** Le foto della home: nome => [etichetta, file di serie in assets/foto, consiglio]. */
    public const IMMAGINI = [
        'telefono'     => ['La schermata nel telefono, in cima', 'borgo-telefono.jpg', 'Orizzontale, 600 × 422 px: è la foto che l\'ospite vede sotto «Benvenuti». Con la vetrina Casa Checco, di serie c\'è la sua.'],
        'scena-qr'     => ['Prima scena: il QR all\'ingresso', 'scena-qr.jpg', '1200 × 750 px. Se manca, la scena mostra un disegno.'],
        'scena-ospite' => ['Seconda scena: l\'ospite', 'scena-ospite.jpg', '1200 × 750 px.'],
        'scena-host'   => ['Terza scena: l\'host', 'scena-host.jpg', '1200 × 750 px.'],
        'pannello-1'   => ['Come funziona · schermata del passo 1', 'pannello-1.webp', '1200 × 750 px. Le tre schermate si vedono solo se ci sono tutte e tre.'],
        'pannello-2'   => ['Come funziona · schermata del passo 2', 'pannello-2.webp', ''],
        'pannello-3'   => ['Come funziona · schermata del passo 3', 'pannello-3.webp', ''],
        'sito'         => ['Lo sfondo di «Anche il tuo sito»', 'sito.jpg', '1600 × 1067 px: sta sotto un velo scuro, va bene una foto d\'ambiente.'],
    ];

    private static ?array $salvate = null;

    public static function disponibili(): bool { return Migrator::tableExists('site_texts'); }

    /** @return array<string,string> le righe salvate (una lettura per richiesta) */
    private static function salvate(): array
    {
        if (self::$salvate === null) {
            self::$salvate = [];
            if (self::disponibili()) foreach (Db::all('SELECT chiave, valore FROM site_texts') as $r) self::$salvate[$r['chiave']] = (string) $r['valore'];
        }
        return self::$salvate;
    }

    public static function reset(): void { self::$salvate = null; }

    /** Il testo grezzo (con i segnaposto e la sintassi): quello salvato, altrimenti quello di serie. */
    public static function get(string $k): string
    {
        $s = self::salvate();
        if (isset($s[$k]) && $s[$k] !== '') return $s[$k];
        return self::catalogo()[$k][3] ?? '';
    }

    public static function serie(string $k): string { return self::catalogo()[$k][3] ?? ''; }

    /** È stato cambiato rispetto al testo di serie? */
    public static function cambiato(string $k): bool { return self::get($k) !== self::serie($k); }

    /** Salva: '' (o uguale al testo di serie) toglie la riga, così torna quello di serie. */
    public static function set(array $coppie): void
    {
        if (!self::disponibili()) return;
        foreach ($coppie as $k => $v) {
            $v = (string) $v;
            $serie = self::catalogo()[$k][3] ?? null;
            if ($v === '' || ($serie !== null && $v === $serie)) { Db::run('DELETE FROM site_texts WHERE chiave = ?', [$k]); continue; }
            if (Db::one('SELECT chiave FROM site_texts WHERE chiave = ?', [$k])) Db::update('site_texts', ['valore' => $v, 'updated_at' => Support::now()], 'chiave = :k', ['k' => $k]);
            else Db::insert('site_texts', ['chiave' => $k, 'valore' => $v, 'updated_at' => Support::now()]);
        }
        self::reset();
    }

    /**
     * Il testo pronto per la pagina: tutto neutralizzato, poi **grassetto** → <b>, [testo](link) → <a>,
     * a capo → <br>. $sost: segnaposto => valore GIÀ sicuro (escapato o HTML voluto).
     */
    public static function html(string $k, array $sost = []): string
    {
        return self::formatta(self::get($k), $sost);
    }

    /** Solo testo, senza sintassi né HTML (per gli attributi alt e title): escapato. */
    public static function testo(string $k, array $sost = []): string
    {
        $t = preg_replace(['/\*\*(.+?)\*\*/s', '/\[([^\]]+)\]\(([^)\s]+)\)/'], ['$1', '$1'], self::get($k));
        $t = Support::e(preg_replace('/\s+/', ' ', (string) $t));
        return strtr($t, self::sostituzioni($sost));
    }

    /** Un testo «lista»: una voce per riga, ognuna già formattata. @return string[] */
    public static function lista(string $k, array $sost = []): array
    {
        $out = [];
        foreach (explode("\n", self::get($k)) as $riga) if (trim($riga) !== '') $out[] = self::formatta(trim($riga), $sost);
        return $out;
    }

    public static function formatta(string $t, array $sost = []): string
    {
        $t = Support::e(trim($t));
        $t = preg_replace('/\*\*(.+?)\*\*/s', '<b>$1</b>', $t);
        $t = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function (array $m): string {
            $u = $m[2];
            if (!preg_match('#^(https?://|mailto:|/|\#)#i', $u)) return $m[0];   // solo link veri: niente javascript: e simili
            $fuori = preg_match('#^https?://#i', $u) === 1;
            return '<a href="' . $u . '"' . ($fuori ? ' rel="noopener" target="_blank"' : '') . '>' . $m[1] . '</a>';
        }, (string) $t);
        $t = nl2br((string) $t, false);
        return strtr($t, self::sostituzioni($sost));
    }

    private static function sostituzioni(array $sost): array
    {
        $out = [];
        foreach ($sost as $k => $v) $out['{' . $k . '}'] = (string) $v;
        return $out;
    }

    // ------------------------------------------------------------------ foto

    /**
     * La foto da mostrare: quella caricata, altrimenti quella di serie in assets/foto (se il file c'è).
     * @return array{url:?string,caricata:bool}
     */
    public static function immagine(string $nome, ?string $serie = null): array
    {
        $rif = self::salvate()['img.' . $nome] ?? '';
        if ($rif !== '' && str_contains($rif, ':')) {
            [$driver, $key] = explode(':', $rif, 2);
            try { return ['url' => Storages::for($driver)->url($key), 'caricata' => true]; }
            catch (\Throwable $e) { Log::exception($e, 'TestiSito::immagine ' . $nome); }
        }
        $file = $serie ?? (self::IMMAGINI[$nome][1] ?? '');
        $cartella = (defined('MHW_PUBLIC') ? MHW_PUBLIC : dirname(__DIR__) . '/public') . '/assets/foto/';
        return ['url' => $file !== '' && is_file($cartella . $file) ? a('/assets/foto/' . $file) : null, 'caricata' => false];
    }

    /** La foto di serie (assets/foto), anche se ce n'è una caricata: per «Torna alla foto di serie» nell'anteprima. */
    public static function immagineSerie(string $nome): ?string
    {
        $file = self::IMMAGINI[$nome][1] ?? '';
        $cartella = (defined('MHW_PUBLIC') ? MHW_PUBLIC : dirname(__DIR__) . '/public') . '/assets/foto/';
        return $file !== '' && is_file($cartella . $file) ? a('/assets/foto/' . $file) : null;
    }

    /** Il riferimento «driver:chiave» della foto caricata, o ''. */
    public static function riferimentoImmagine(string $nome): string { return (string) (self::salvate()['img.' . $nome] ?? ''); }
}
