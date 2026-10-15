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
        'soddisfatti' => ['Più sanno, meglio stanno', 'Informazioni → recensioni → prenotazioni (8G): tre punti e la scheda con i quattro passi, tra le scene e «Cosa trova l\'ospite».'],
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
        'consenso' => ['Cookie e consenso', 'Il banner compare solo se in SEO e GEO c\'è Google Analytics o Clarity. Con il solo Matomo (senza cookie) non serve.'],
        'cookie'   => ['Informativa: il paragrafo «Cookie»', 'Nella pagina /privacy. I paragrafi di Matomo, Google Analytics e Clarity compaiono solo se sono configurati in SEO e GEO.'],
        'sito'     => ['Anche il tuo sito', 'Il blocco del colore di MyHouse in fondo: un percorso a due domande, poi il contatto (8A). Gli stessi testi nel pannello.'],
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
            // ---- 8G · Più sanno, meglio stanno
            'soddisfatti.titolo'   => ['soddisfatti', 'Titolo', 'riga', 'Più sanno, meglio stanno.', ''],
            'soddisfatti.1.titolo' => ['soddisfatti', 'Punto 1 · in grassetto', 'riga', 'Nessun dubbio, nessuno stress.', ''],
            'soddisfatti.1.testo'  => ['soddisfatti', 'Punto 1 · testo', 'riga', 'Tutte le risposte, prima ancora di chiederle.', ''],
            'soddisfatti.2.titolo' => ['soddisfatti', 'Punto 2 · in grassetto', 'riga', 'Ospite sereno, recensione a 5 stelle.', ''],
            'soddisfatti.2.testo'  => ['soddisfatti', 'Punto 2 · testo', 'riga', 'Chi si sente accolto lo racconta.', ''],
            'soddisfatti.3.titolo' => ['soddisfatti', 'Punto 3 · in grassetto', 'riga', 'Più stelle, più prenotazioni.', ''],
            'soddisfatti.3.testo'  => ['soddisfatti', 'Punto 3 · testo', 'riga', 'Su Booking, Airbnb e Google la differenza si vede.', ''],
            'soddisfatti.passo.1'  => ['soddisfatti', 'Scheda · primo passo', 'riga', 'Informazioni chiare', ''],
            'soddisfatti.passo.2'  => ['soddisfatti', 'Scheda · secondo passo', 'riga', 'Ospite sereno', ''],
            'soddisfatti.passo.3'  => ['soddisfatti', 'Scheda · terzo passo (con le cinque stelle)', 'riga', 'Recensione a 5 stelle', ''],
            'soddisfatti.passo.4'  => ['soddisfatti', 'Scheda · ultimo passo (nel colore d\'accento)', 'riga', 'Nuove prenotazioni', ''],
            'soddisfatti.didascalia' => ['soddisfatti', 'Didascalia sotto la scheda', 'riga', 'Il circolo che riempie il calendario.', ''],
            'prodotto.kicker' => ['prodotto', 'Riga sopra il titolo', 'riga', 'La guida', ''],
            'prodotto.titolo' => ['prodotto', 'Titolo', 'riga', 'Cosa trova l\'ospite.', ''],
            'prodotto.testo'  => ['prodotto', 'Testo', 'area', 'Le informazioni del soggiorno, in ordine e sempre sul telefono, nella lingua dell\'ospite. {sezioni} sezioni pronte da compilare, più le sezioni libere per tutto il resto: scegli quelle che servono ai tuoi ospiti.', '{sezioni} diventa il numero di sezioni del catalogo.'],
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
            'tempo.risultato.piccolo' => ['tempo', 'Riquadro rosso · nota in piccolo', 'riga', 'Contiamo la metà dei tuoi minuti di domande evitabili: qualche ospite chiederà comunque.', ''],
            // 8A: le frasi dei cursori e del riquadro arancione (prima scritte nel file e nello script).
            'tempo2.rif'      => ['tempo', 'Colonne · riferimento', 'riga', 'Riferimento: **{min} min** a prenotazione', '{min}: i minuti di riferimento (16 e 19).'],
            'tempo2.tuo'      => ['tempo', 'Colonne · cursore', 'riga', 'Il tuo tempo', ''],
            'tempo2.minuti'   => ['tempo', 'Colonne · minuti', 'riga', '{n} min', '{n}: i minuti scelti col cursore.'],
            'tempo2.aria.nec' => ['tempo', 'Colonna sinistra · cursore (per chi non vede)', 'riga', 'Il tuo tempo per le comunicazioni necessarie, in minuti per prenotazione', ''],
            'tempo2.aria.evit' => ['tempo', 'Colonna destra · cursore (per chi non vede)', 'riga', 'Il tuo tempo per le domande evitabili, in minuti per prenotazione', ''],
            'tempo2.contesto' => ['tempo', 'Riquadro del conto · riga in fondo', 'area', 'Oggi, in un anno: **{ore} ore** di messaggi, di cui {evit} di domande evitabili.', '{ore} e {evit}: le ore calcolate.'],
            'tempo2.ore'      => ['tempo', 'Riquadro arancione · il tempo in grande', 'riga', '{ore} ore', '{ore}: le ore risparmiate.'],
            'tempo2.giornate' => ['tempo', 'Riquadro arancione · le giornate', 'riga', 'cioè {n} giornate di lavoro', '{n}: le giornate di 8 ore.'],
            'tempo2.giornata' => ['tempo', 'Riquadro arancione · una giornata', 'riga', 'cioè {n} giornata di lavoro', ''],
            'tempo2.meno'     => ['tempo', 'Riquadro arancione · meno di una giornata', 'riga', 'cioè meno di una giornata di lavoro', ''],
            'tempo2.valgono'  => ['tempo', 'Riquadro arancione · il valore, in grassetto', 'riga', 'Valgono {euro} del tuo tempo, a {ora} l\'ora.', '{euro}: il valore in un anno; {ora}: quanto vale un\'ora.'],
            'tempo2.formato'  => ['tempo', 'Formato dei numeri', 'riga', 'it-IT', 'it-IT: 1.234 € e 1,5. Non cambiarlo.'],
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
            'guadagno.2.testo'  => ['guadagno', 'Voce 2 · testo', 'area', '«Tornerai da queste parti?», con il link al tuo sito e il codice sconto.', ''],
            'guadagno.3.titolo' => ['guadagno', 'Voce 3 · titolo', 'riga', 'Servizi extra.', ''],
            'guadagno.3.testo'  => ['guadagno', 'Voce 3 · testo', 'area', 'Transfer, colazione, late check-out: l\'ospite li chiede con un tocco su WhatsApp.', ''],
            // ---- come funziona
            'come.kicker'   => ['come', 'Riga sopra il titolo', 'riga', 'Come funziona', ''],
            'come.titolo'   => ['come', 'Titolo', 'riga', 'Inizia in pochi minuti.', ''],
            'come.1.titolo' => ['come', 'Passo 1 · titolo', 'riga', 'Crea la tua guida.', ''],
            'come.1.testo'  => ['come', 'Passo 1 · testo', 'area', 'Inserisci le informazioni della struttura, scegli la lingua in cui scrivi (non per forza l\'italiano) e cosa condividere con gli ospiti.', ''],
            'come.1.alt'    => ['come', 'Passo 1 · descrizione della schermata', 'riga', 'Il pannello: i contenuti della guida, con le sezioni e le statistiche', ''],
            'come.2.titolo' => ['come', 'Passo 2 · titolo', 'riga', 'Personalizza e guarda l\'anteprima.', ''],
            'come.2.testo'  => ['come', 'Passo 2 · testo', 'area', 'Scegli i colori e la copertina, aggiungi le lingue degli ospiti (con Plus le traduzioni te le suggeriamo noi, partendo dalla tua lingua) e guarda la guida sul telefono come la vedranno gli ospiti, prima di pubblicarla.', ''],
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
            // 8A: «Anche il tuo sito» diventa un percorso a domande (chiavi nuove: i testi del blocco di prima non servono più).
            'sito2.kicker'   => ['sito', 'Riga sopra il titolo', 'riga', 'Un altro servizio, dallo stesso team', ''],
            'sito2.titolo'   => ['sito', 'Titolo', 'riga', 'La guida accoglie gli ospiti.', ''],
            'sito2.accento'  => ['sito', 'Seconda riga del titolo, in arancione', 'riga', 'Il tuo sito li porta da te.', ''],
            'sito2.lead'     => ['sito', 'Testo sotto il titolo', 'area', 'Meno commissioni ai portali, più incasso per notte, più ospiti che tornano a prenotare direttamente. Lo realizziamo noi, con la prenotazione diretta.', ''],
            'sito2.avviso'   => ['sito', 'Riquadro «servizio a parte»', 'area', '**MyHouse è un servizio a parte** di Blackout Agency: non è compreso nell\'abbonamento della guida e si fa su misura.', ''],
            'sito2.logo'     => ['sito', 'Logo MyHouse · testo alternativo', 'riga', 'MyHouse — Il tuo sito, le tue prenotazioni', 'Lo legge chi non vede l\'immagine.'],
            'sito2.passo1'   => ['sito', 'Passo 1 · riga in alto', 'riga', 'Domanda 1 di 2', ''],
            'sito2.domanda1' => ['sito', 'Passo 1 · domanda', 'riga', 'Hai già un sito per la tua struttura?', ''],
            'sito2.si.titolo' => ['sito', 'Passo 1 · scelta Sì', 'riga', 'Sì, ce l\'ho', ''],
            'sito2.si.sotto' => ['sito', 'Passo 1 · scelta Sì, sotto', 'riga', 'Vorrei migliorarlo', ''],
            'sito2.no.titolo' => ['sito', 'Passo 1 · scelta No', 'riga', 'No, non ancora', ''],
            'sito2.no.sotto' => ['sito', 'Passo 1 · scelta No, sotto', 'riga', 'Vorrei crearlo', ''],
            'sito2.subito'   => ['sito', 'Passo 1 · riga in piccolo', 'riga', 'Oppure scrivici subito:', 'Dopo, l\'email e il WhatsApp di contatto.'],
            'sito2.passo2'   => ['sito', 'Passo 2 · riga in alto', 'riga', 'Domanda 2 di 2', ''],
            'sito2.domanda2si' => ['sito', 'Passo 2 (Sì) · domanda', 'riga', 'Cosa vorresti migliorare?', ''],
            'sito2.b.aspetto' => ['sito', 'Passo 2 (Sì) · scelta 1', 'riga', 'Un aspetto più bello', 'Entra nel messaggio con l\'iniziale minuscola.'],
            'sito2.b.google' => ['sito', 'Passo 2 (Sì) · scelta 2', 'riga', 'Farmi trovare su Google', ''],
            'sito2.b.dirette' => ['sito', 'Passo 2 (Sì) · scelta 3', 'riga', 'Più prenotazioni dirette', ''],
            'sito2.b.altro'  => ['sito', 'Passo 2 (Sì) · scelta 4', 'riga', 'Altro', ''],
            'sito2.altro'    => ['sito', 'Passo 2 (Sì) · campo di «Altro»', 'riga', 'Raccontaci in due parole', ''],
            'sito2.continua' => ['sito', 'Passo 2 (Sì) · bottone', 'riga', 'Continua →', ''],
            'sito2.domanda2no' => ['sito', 'Passo 2 (No) · titolo', 'riga', 'Il sito della tua struttura, pronto per le prenotazioni dirette.', ''],
            'sito2.punti'    => ['sito', 'Passo 2 (No) · i tre punti', 'lista', "Foto, camere e disponibilità aggiornate\nPrenotazione e pagamento sul tuo sito, senza commissioni\nVisibile su Google per chi cerca nella tua zona", 'Una voce per riga.'],
            'sito2.proposta' => ['sito', 'Passo 2 (No) · bottone', 'riga', 'Voglio una proposta →', ''],
            'sito2.indietro' => ['sito', 'Bottone per tornare indietro', 'riga', '← Indietro', ''],
            'sito2.passo3'   => ['sito', 'Passo 3 · riga in alto', 'riga', 'Ultimo passo', ''],
            'sito2.domanda3' => ['sito', 'Passo 3 · domanda', 'riga', 'Come preferisci sentirci?', ''],
            'sito2.scopri'   => ['sito', 'Passo 3 · bottone del sito', 'riga', 'Scopri MyHouse', 'Apre blackout.in/myhouse in una scheda nuova, con le risposte.'],
            'sito2.whatsapp' => ['sito', 'Passo 3 · bottone WhatsApp', 'riga', 'WhatsApp', ''],
            'sito2.email'    => ['sito', 'Passo 3 · bottone Email', 'riga', 'Email', ''],
            'sito2.risposta' => ['sito', 'Passo 3 · nota in fondo', 'riga', 'Ti rispondiamo con una proposta su misura, senza impegno.', ''],
            'sito2.oggetto'  => ['sito', 'Oggetto dell\'email', 'riga', 'Il sito per la mia struttura', ''],
            'sito2.msg.home' => ['sito', 'Messaggio · prima frase (home)', 'riga', 'Ciao, ho una struttura ricettiva.', ''],
            'sito2.msg.pannello' => ['sito', 'Messaggio · prima frase (pannello)', 'riga', 'Ciao, ho una guida MyHouse Welcome per {struttura}.', '{struttura}: il nome della struttura.'],
            'sito2.msg.si'   => ['sito', 'Messaggio · chi ha il sito', 'riga', 'Ho già un sito e vorrei migliorarlo{bisogni}.', '{bisogni}: «: farmi trovare su Google, …» con le scelte, o niente.'],
            'sito2.msg.no'   => ['sito', 'Messaggio · chi non ha il sito', 'riga', 'Non ho ancora un sito per la mia struttura e vorrei crearlo.', ''],
            'sito2.msg.fine' => ['sito', 'Messaggio · ultima frase', 'riga', 'Mi date una proposta?', ''],
            // ---- 8H · il banner del consenso (solo con Google Analytics o Clarity in SEO e GEO)
            'consenso.testo'     => ['consenso', 'Testo del banner', 'area', 'Usiamo cookie tecnici e, solo con il tuo consenso, {strumenti} per migliorare il sito.', '{strumenti} diventa da solo «strumenti di statistica (Google Analytics) e di analisi dell\'uso (Microsoft Clarity)», secondo quelli configurati.'],
            'consenso.link'      => ['consenso', 'Link all\'informativa', 'riga', 'Cookie e privacy', ''],
            'consenso.accetta'   => ['consenso', 'Bottone «Accetta»', 'riga', 'Accetta', ''],
            'consenso.rifiuta'   => ['consenso', 'Bottone «Rifiuta»', 'riga', 'Rifiuta', ''],
            'consenso.scegli'    => ['consenso', 'Bottone «Scegli»', 'riga', 'Scegli', ''],
            'consenso.salva'     => ['consenso', 'Bottone per salvare le scelte', 'riga', 'Salva le scelte', ''],
            'consenso.chiudi'    => ['consenso', 'La X (per chi usa un lettore di schermo)', 'riga', 'Chiudi: vale come rifiuto', ''],
            'consenso.statistiche'      => ['consenso', 'Interruttore · Statistiche', 'riga', 'Statistiche', ''],
            'consenso.statistiche.nota' => ['consenso', 'Interruttore · Statistiche · spiegazione', 'riga', 'Google Analytics: quante persone visitano il sito e da dove arrivano.', ''],
            'consenso.analisi'          => ['consenso', 'Interruttore · Analisi dell\'uso', 'riga', 'Analisi dell\'uso', ''],
            'consenso.analisi.nota'     => ['consenso', 'Interruttore · Analisi dell\'uso · spiegazione', 'riga', 'Microsoft Clarity: mappe di calore e registrazioni delle visite, per capire cosa non è chiaro.', ''],
            'consenso.preferenze' => ['consenso', 'Link nel piè di pagina', 'riga', 'Preferenze cookie', 'Riapre il banner per cambiare idea.'],
            // ---- 8H · l'informativa: il paragrafo «Cookie» della pagina /privacy
            'cookie.tecnici' => ['cookie', 'Cookie tecnici', 'area', 'Il sito usa solo cookie tecnici, che non richiedono il consenso: quello della sessione (mhw_sess), che tiene aperto l\'accesso al pannello e protegge i moduli, e quello che ricorda la tua scelta sui cookie (mhw_consenso, 6 mesi).', ''],
            'cookie.matomo'  => ['cookie', 'Matomo (compare solo se è configurato)', 'area', 'Per contare le visite usiamo Matomo, installato sul nostro server: non usa cookie, l\'indirizzo IP viene anonimizzato (gli ultimi due byte) e i dati restano da noi, senza passare a terzi.', ''],
            'cookie.ga4'     => ['cookie', 'Google Analytics (compare solo se è configurato)', 'area', '**Google Analytics 4** (Google Ireland Ltd), solo con il tuo consenso alle «Statistiche»: misura le visite in forma aggregata, senza segnali pubblicitari né personalizzazione degli annunci. Cookie _ga e _ga_*, durata fino a 2 anni. [Informativa di Google](https://policies.google.com/privacy).', ''],
            'cookie.clarity' => ['cookie', 'Microsoft Clarity (compare solo se è configurato)', 'area', '**Microsoft Clarity** (Microsoft Ireland Operations Ltd), solo con il tuo consenso all\'«Analisi dell\'uso»: mappe di calore e registrazioni delle visite per capire dove il sito non è chiaro; i campi dei moduli sono mascherati. Cookie _clck (1 anno) e _clsk (1 giorno). [Informativa di Microsoft](https://privacy.microsoft.com/privacystatement).', ''],
            'cookie.revoca'  => ['cookie', 'Come cambiare idea (compare con Google Analytics o Clarity)', 'area', 'Puoi cambiare idea quando vuoi da «Preferenze cookie», in fondo alle pagine del sito: se revochi il consenso, i cookie di Google Analytics e di Clarity vengono cancellati.', ''],
            'cookie.guide'   => ['cookie', 'Le guide degli ospiti', 'area', 'Le guide degli ospiti non usano cookie né statistiche di terzi: contano solo eventi anonimi per l\'host, come scritto sopra.', ''],
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

    /**
     * 8E: la lingua dei testi. In inglese (/en) un testo cambiato dall'amministratore si salva con la chiave
     * «en:» + chiave (site_texts ha la chiave primaria su «chiave»: niente colonne nuove); quelli di serie
     * stanno in lang/sito-en.php. Una chiave che lì manca resta in italiano.
     */
    private static function lingua(?string $loc): string { return ($loc ?? LinguaSito::get()) === 'en' ? 'en' : 'it'; }

    private static function chiave(string $k, string $loc): string { return $loc === 'en' ? 'en:' . $k : $k; }

    /** @return array<string,string> i testi inglesi di serie */
    private static function serieEn(): array
    {
        static $en = null;
        if ($en === null) { $f = MHW_APP . '/lang/sito-en.php'; $en = is_file($f) ? (array) require $f : []; }
        return $en;
    }

    /** Il testo grezzo (con i segnaposto e la sintassi): quello salvato, altrimenti quello di serie. */
    public static function get(string $k, ?string $loc = null): string
    {
        $loc = self::lingua($loc);
        $s = self::salvate();
        $kk = self::chiave($k, $loc);
        if (isset($s[$kk]) && $s[$kk] !== '') return $s[$kk];
        return self::serie($k, $loc);
    }

    public static function serie(string $k, ?string $loc = null): string
    {
        if (self::lingua($loc) === 'en' && isset(self::serieEn()[$k])) return (string) self::serieEn()[$k];
        return self::catalogo()[$k][3] ?? '';
    }

    /** È stato cambiato rispetto al testo di serie? */
    public static function cambiato(string $k, ?string $loc = null): bool { return self::get($k, $loc) !== self::serie($k, $loc); }

    /** Salva: '' (o uguale al testo di serie) toglie la riga, così torna quello di serie. $loc: 'it' o 'en'. */
    public static function set(array $coppie, string $loc = 'it'): void
    {
        if (!self::disponibili()) return;
        $loc = $loc === 'en' ? 'en' : 'it';
        foreach ($coppie as $k => $v) {
            $v = (string) $v;
            $serie = isset(self::catalogo()[$k]) ? self::serie($k, $loc) : null;
            $kk = self::chiave((string) $k, $loc);
            if ($v === '' || ($serie !== null && $v === $serie)) { Db::run('DELETE FROM site_texts WHERE chiave = ?', [$kk]); continue; }
            if (Db::one('SELECT chiave FROM site_texts WHERE chiave = ?', [$kk])) Db::update('site_texts', ['valore' => $v, 'updated_at' => Support::now()], 'chiave = :k', ['k' => $kk]);
            else Db::insert('site_texts', ['chiave' => $kk, 'valore' => $v, 'updated_at' => Support::now()]);
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
