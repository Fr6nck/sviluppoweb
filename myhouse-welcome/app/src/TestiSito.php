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
    public const RIGA_MAX = 200, AREA_MAX = 3000, LUNGO_MAX = 8000;   // lungo: i paragrafi dei documenti legali (8J)

    /** 8J: le sezioni dei documenti legali, nella linguetta «Documenti legali» di Testi e foto (le altre sono della home). */
    public const SEZIONI_LEGALI = ['termini', 'privacy', 'cookie', 'dpa', 'legale'];

    /** Le sezioni della pagina di amministrazione, nell'ordine della home. */
    public const SEZIONI = [
        'hero'     => ['In cima alla pagina', 'Titolo, sottotitolo e bottoni: la prima cosa che si legge.'],
        'scene'    => ['Le tre scene', 'Le tre foto sotto il titolo: il QR, l\'ospite, l\'host. Le foto si cambiano in fondo a questa pagina.'],
        'soddisfatti' => ['Più sanno, meglio stanno', 'Informazioni → recensioni → prenotazioni (8G): tre punti e la scheda con i quattro passi, tra le scene e «Cosa trova l\'ospite».'],
        'prodotto' => ['Cosa trova l\'ospite', ''],
        'dicono'   => ['Le guide dei nostri host e «Giudizio con sconto»', '9L: le schede di chi ha aderito al programma, accese in Vendite → Giudizi e vetrina; la sezione compare solo se ce n\'è almeno una. La nota in fondo è obbligatoria: si può cambiare, non togliere (vuota, torna quella di serie). Qui anche il testo dell\'unica casella del programma, uguale al pagamento e nel pannello.'],
        'perchi'   => ['Per chi è', 'Le sei schede (9K): casa vacanza, B&B, affittacamere, agriturismo, più strutture, property manager. Ognuna porta alla sua pagina; le foto si cambiano in fondo a questa pagina («Per chi è · …»).'],
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
        // 8J · i documenti legali (lang/legale.php). Una riga vuota = un paragrafo nuovo; «- » = elenco; «| Dati | …» = riga di tabella.
        'termini'  => ['Termini e condizioni', 'La pagina /termini (e /en/terms). Quando cambi un testo in modo sostanziale, cambia anche la versione in Impostazioni → Titolare e documenti legali: i clienti ricevono l\'avviso e accettano di nuovo. Segnaposto: {titolare}, {titolare_completo}, {sede}, {email}, {telefono}, {sito_titolare}, {link_privacy}, {link_dpa}, {link_modulo}, {amico}, {passo}, {max}, {amici}.'],
        'privacy'  => ['Informativa sulla privacy', 'La pagina /privacy (e /en/privacy). Ogni trattamento è una tabella: una riga per voce, «| Voce | testo». Segnaposto in più: {posta} (il fornitore della posta, dall\'impostazione), {regione_traduzioni}, {giorni_registri}.'],
        'cookie'   => ['Informativa: il paragrafo «Cookie»', 'Nella pagina /privacy. I paragrafi di Matomo, Google Analytics e Clarity compaiono solo se sono configurati in SEO e GEO.'],
        'dpa'      => ['Accordo sul trattamento dei dati (DPA)', 'La pagina /dpa (e /en/dpa): l\'Allegato A dei Termini, che l\'host accetta alla registrazione.'],
        'legale'   => ['Aggiornamenti dei documenti', 'Le novità in tre righe: nella finestra «Abbiamo aggiornato i Termini e l\'Informativa» del pannello e nell\'email di avviso. Solo in italiano (il pannello è in italiano).'],
        'sito'     => ['Anche il tuo sito', 'Il blocco del colore di MyHouse in fondo: un percorso a due domande, poi il contatto (8A). Gli stessi testi nel pannello.'],
        'questionario' => ['Pagine per tipologia: «Qual è il piano giusto per te?»', 'Il questionario a tre domande (9K) nelle pagine /case-vacanza, /bed-and-breakfast, /affittacamere e /agriturismi. Solo in italiano, come quelle pagine. I numeri si compilano dal listino: {sezioni} (tutte le sezioni), {sezioni_essential}, {lingue} (le lingue di Plus), {piano}; nella riga dello sconto {totale}, {amici}, {passo}, {max}, {giudizio}.'],
        // 9F · i testi del pannello che il titolare vuole poter cambiare: il riquadro «Una lingua in più» (Lingue)
        'pannello' => ['Pannello: una lingua in più', 'Il riquadro sopra la scelta delle lingue (Lingue, e il primo passo della procedura). Le cinque lingue di base e la lingua in più: a cosa serve, con due esempi.'],
        // 9D · il messaggio pronto dei codici omaggio (Vendite → Codici omaggio: WhatsApp, email, copia)
        'omaggio'  => ['Codici omaggio', 'Il messaggio pronto da mandare con un codice omaggio (Vendite → Codici omaggio). Segnaposto: {mesi}, {link}, {codice}, {scadenza}.'],
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
            // 9K · sei schede, ognuna è un link alla sua pagina (le foto in «Foto», perchi-…)
            'perchi.casa.titolo'   => ['perchi', 'Casa vacanza · titolo', 'riga', 'Casa vacanza', ''],
            'perchi.casa.testo'    => ['perchi', 'Casa vacanza · testo', 'area', 'Non ci sei quando arrivano? La guida spiega come si entra, dove si parcheggia e come funziona la casa, a qualsiasi ora.', ''],
            'perchi.bnb.titolo'    => ['perchi', 'Bed & Breakfast · titolo', 'riga', 'Bed & Breakfast', ''],
            'perchi.bnb.testo'     => ['perchi', 'Bed & Breakfast · testo', 'area', 'Colazione, orari, regole e consigli: un QR in ogni camera al posto del foglio plastificato.', ''],
            'perchi.affitta.titolo' => ['perchi', 'Affittacamere · titolo', 'riga', 'Affittacamere', ''],
            'perchi.affitta.testo' => ['perchi', 'Affittacamere · testo', 'area', 'Più camere, stesse domande: Wi-Fi, chiavi, check-out. Le scrivi una volta e valgono per tutti.', ''],
            'perchi.agri.titolo'   => ['perchi', 'Agriturismo · titolo', 'riga', 'Agriturismo', ''],
            'perchi.agri.testo'    => ['perchi', 'Agriturismo · testo', 'area', 'Racconta l\'azienda, le degustazioni e i tuoi prodotti, e accompagna gli ospiti tra sagre, sentieri e cantine.', ''],
            'perchi.piu.titolo'    => ['perchi', 'Più strutture · titolo', 'riga', 'Più strutture', ''],
            'perchi.piu.testo'     => ['perchi', 'Più strutture · testo', 'area', 'Una guida e un QR per ogni casa, un solo account. Quello che vale per tutte lo scrivi una volta.', ''],
            'perchi.pm.titolo'     => ['perchi', 'Property manager · titolo', 'riga', 'Property manager', ''],
            'perchi.pm.testo'      => ['perchi', 'Property manager · testo', 'area', 'Gestisci case per conto dei proprietari? Diventa partner: offri la guida ai tuoi clienti e guadagni anche tu.', ''],
            'perchi.scopri'        => ['perchi', 'In fondo a ogni scheda', 'riga', 'Scopri', 'La freccia la aggiunge il sito.'],
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
            // ---- cosa dicono di noi (9B)
            'dicono.kicker' => ['dicono', 'Riga sopra il titolo', 'riga', 'Le guide dei nostri host', ''],
            'dicono.titolo' => ['dicono', 'Titolo', 'riga', 'Host veri, ospiti più sereni.', ''],
            'dicono.sotto'  => ['dicono', 'Testo sotto il titolo', 'riga', 'Le strutture che usano MyHouse Welcome ogni giorno, e le parole di chi ha già lasciato il suo giudizio.', ''],
            // ---- 9K · il questionario delle pagine per tipologia
            'questionario.kicker'  => ['questionario', 'Riga sopra il titolo', 'riga', 'Qual è il piano giusto per te?', ''],
            'questionario.apri.domanda' => ['questionario', 'Home · accanto a «Confronta tutti i piani»', 'riga', 'Non sai quale scegliere?', '9L: apre la finestra con il questionario.'],
            'questionario.apri.bottone' => ['questionario', 'Home · il bottone', 'riga', 'Rispondi a 3 domande', 'La freccia la aggiunge il sito.'],
            'questionario.titolo'  => ['questionario', 'Titolo', 'riga', 'Tre domande, una risposta.', ''],
            'questionario.sub'     => ['questionario', 'Sotto il titolo', 'riga', 'Rispondi in 20 secondi: ti diciamo quale piano ti basta, senza pagare per quello che non usi.', ''],
            'questionario.d1'      => ['questionario', 'Domanda 1', 'riga', 'Ti bastano due lingue, scritte da te?', ''],
            'questionario.d1.aiuto' => ['questionario', 'Domanda 1 · spiegazione', 'riga', 'Per esempio italiano e inglese. Con due lingue la guida la scrivi tu, in tutte e due.', ''],
            'questionario.d1.si'   => ['questionario', 'Domanda 1 · risposta sì', 'riga', 'Sì, mi bastano', ''],
            'questionario.d1.si.aiuto' => ['questionario', 'Domanda 1 · sotto il sì', 'riga', 'Scrivo io tutti i testi, in tutte e due le lingue', ''],
            'questionario.d1.no'   => ['questionario', 'Domanda 1 · risposta no', 'riga', 'No, voglio la traduzione automatica', ''],
            'questionario.d1.no.aiuto' => ['questionario', 'Domanda 1 · sotto il no', 'riga', 'Scrivo solo in italiano, la guida si traduce da sola e ne approfitto per avere più lingue, fino a {lingue}', ''],
            'questionario.d1.breve' => ['questionario', 'Domanda 1 · nel riepilogo', 'riga', 'Due lingue senza traduzione', ''],
            'questionario.d2'      => ['questionario', 'Domanda 2', 'riga', 'Ti bastano {sezioni_essential} sezioni?', ''],
            'questionario.d2.aiuto' => ['questionario', 'Domanda 2 · spiegazione', 'riga', 'Oltre al check-in: per esempio Wi-Fi, regole, parcheggio, dove mangiare, rifiuti, emergenze.', ''],
            'questionario.d2.si'   => ['questionario', 'Domanda 2 · risposta sì', 'riga', 'Sì, l\'essenziale', ''],
            'questionario.d2.si.aiuto' => ['questionario', 'Domanda 2 · sotto il sì', 'riga', 'Le informazioni pratiche della casa', ''],
            'questionario.d2.no'   => ['questionario', 'Domanda 2 · risposta no', 'riga', 'No, voglio raccontare di più', ''],
            'questionario.d2.no.aiuto' => ['questionario', 'Domanda 2 · sotto il no', 'riga', 'Zona, eventi, servizi extra, foto e PDF', ''],
            'questionario.d2.breve' => ['questionario', 'Domanda 2 · nel riepilogo', 'riga', '{sezioni_essential} sezioni', ''],
            'questionario.d3'      => ['questionario', 'Domanda 3', 'riga', 'Hai una sola struttura?', ''],
            'questionario.d3.aiuto' => ['questionario', 'Domanda 3 · spiegazione', 'riga', 'Una casa, un B&B o un agriturismo con un solo indirizzo e un solo CIN.', ''],
            'questionario.d3.si'   => ['questionario', 'Domanda 3 · risposta sì', 'riga', 'Sì, una sola', ''],
            'questionario.d3.si.aiuto' => ['questionario', 'Domanda 3 · sotto il sì', 'riga', 'Un indirizzo, un CIN', ''],
            'questionario.d3.no'   => ['questionario', 'Domanda 3 · risposta no', 'riga', 'No, ne ho più di una', ''],
            'questionario.d3.no.aiuto' => ['questionario', 'Domanda 3 · sotto il no', 'riga', 'Due o più case, anche in città diverse', ''],
            'questionario.d3.breve' => ['questionario', 'Domanda 3 · nel riepilogo', 'riga', 'Una struttura', ''],
            'questionario.vedi'    => ['questionario', 'Bottone senza JavaScript', 'riga', 'Vedi il piano giusto', 'Compare solo a chi naviga senza JavaScript: con JavaScript le domande scorrono da sole.'],
            'questionario.badge'   => ['questionario', 'Sopra il piano', 'riga', 'Il piano per te', ''],
            'questionario.perche'  => ['questionario', 'Titolo del riquadro a destra', 'riga', 'Perché questo piano', ''],
            'questionario.essential.punti' => ['questionario', 'Essential · i punti', 'lista', "1 struttura\nCheck-in & Check-out + {sezioni_essential} sezioni a scelta\n2 lingue: la tua e una a scelta", 'Una voce per riga.'],
            'questionario.essential.perche' => ['questionario', 'Essential · perché', 'area', 'Ti bastano le informazioni essenziali in due lingue: non paghi funzioni che non useresti. Se un giorno ti servono più sezioni o lingue, passi a Plus pagando solo la differenza.', ''],
            'questionario.plus.punti' => ['questionario', 'Plus · i punti', 'lista', "1 struttura\nTutte le {sezioni} sezioni\nTraduzione automatica in {lingue} lingue, inclusa\nFoto e PDF accanto ai testi", 'Una voce per riga.'],
            'questionario.plus.perche' => ['questionario', 'Plus · perché', 'area', 'Vuoi raccontare di più o parlare a ospiti di tante lingue: con Plus hai tutte le sezioni e la traduzione automatica in {lingue} lingue. Scrivi una volta, la guida parla la lingua dei tuoi ospiti.', ''],
            'questionario.portfolio.punti' => ['questionario', 'Portfolio · i punti', 'lista', "Una guida e un QR per ogni struttura\nTutto quello che c'è in Plus, traduzione automatica compresa\nSezioni da copiare da una casa all'altra\nStatistiche per struttura", 'Una voce per riga.'],
            'questionario.portfolio.perche' => ['questionario', 'Portfolio · perché', 'area', 'Hai più strutture: ognuna ha la sua guida con i suoi dati, ma il lavoro lo fai una volta sola. Più strutture aggiungi, meno costa ognuna.', ''],
            'questionario.crea'    => ['questionario', 'Bottone principale', 'riga', 'Crea gratis con {piano}', 'La freccia la aggiunge il sito.'],
            'questionario.rifai'   => ['questionario', 'Bottone «rifai»', 'riga', 'Rifai il test', ''],
            'questionario.rinnovo.nota' => ['questionario', 'Sotto la tabella del rinnovo', 'riga', 'Prezzi + IVA, all\'anno. Lo sconto vale sul rinnovo successivo agli inviti andati a buon fine.', ''],
            'omaggio.messaggio' => ['omaggio', 'Messaggio con il codice omaggio', 'area', 'Ciao! Ti abbiamo riservato {mesi} mesi gratis di MyHouse Welcome, la guida digitale per i tuoi ospiti. Vai su {link}, crea la guida e inserisci la carta: oggi non paghi nulla, il primo addebito è tra {mesi} mesi, e prima ti avvisiamo. Il codice {codice} vale fino al {scadenza}.', 'Lo manda l\'amministrazione su WhatsApp o per email: {mesi}, {link}, {codice}, {scadenza} ed {email} (l\'email commerciale) si compilano da soli. Se il testo non contiene l\'email commerciale, in fondo si aggiunge la firma «MyHouse Welcome · commerciale@…».'],
            'giudizio.casella' => ['dicono', 'La casella del programma (pagamento e pannello)', 'area', '**Aderisco al programma «Giudizio con sconto» e ho il {giudizio} di sconto.** La mia guida compare tra «Le guide dei nostri host» su welcomemyhouse.it **da quando la pubblico**, con il nome della struttura, la copertina, il logo, il mio nome (senza cognome) e la città. Nessun link alla guida, nessuna mia foto. Dopo 30 giorni lascio un voto da 1 a 5 con un commento, che può comparire sulla stessa scheda. Lo sconto non dipende dal voto. Posso ritirarmi quando voglio da Impostazioni → Giudizio con sconto.', 'Una chiave sola: è il testo della casella al pagamento e nel pannello, e la prova dell\'adesione. {giudizio} è la percentuale. Se cambi le regole, cambia anche i Termini.'],
            'giudizio.email.aderito' => ['dicono', 'Email di benvenuto · la riga per chi ha aderito', 'area', 'Hai aderito a «Giudizio con sconto»: dopo un nostro controllo la tua guida compare tra «Le guide dei nostri host» su welcomemyhouse.it, con il nome, la copertina e il logo (mai una tua foto, mai un link alla guida). Tra 30 giorni ti chiediamo un voto da 1 a 5: lo sconto non dipende dal voto.', 'Nell\'email che parte quando la prima guida va online. Testo semplice: il grassetto non si vede.'],
            'giudizio.email.invito' => ['dicono', 'Email di benvenuto · la riga per chi può ancora aderire', 'area', 'Vuoi il {giudizio} di sconto sul rinnovo? Aderisci a «Giudizio con sconto»: la tua guida compare tra «Le guide dei nostri host» su welcomemyhouse.it e dopo 30 giorni ci lasci un voto da 1 a 5. Lo sconto non dipende dal voto. Si aderisce dal pannello: {link}', '{giudizio} è la percentuale, {link} la pagina del programma nel pannello. Non parte a chi ha un codice omaggio da usare o se il programma è spento.'],
            'dicono.nota'   => ['dicono', 'Nota in fondo (obbligatoria)', 'area', 'Guide di host che hanno aderito al programma “Giudizio con sconto”: ricevono il {giudizio}% sul rinnovo, qualunque voto diano. Pubblicate con il loro accordo, senza link alle guide.', '{giudizio} è la percentuale dello sconto (Impostazioni). Non si può togliere.'],
            // ---- piani
            'piani.kicker' => ['piani', 'Riga sopra il titolo', 'riga', 'Piani e prezzi', ''],
            'piani.titolo' => ['piani', 'Titolo', 'riga', 'Scegli il piano.', ''],
            'piani.lead'   => ['piani', 'Testo accanto al titolo', 'area', 'Crei e provi la guida gratis, con le funzioni del piano che scegli: paghi solo quando la pubblichi. Prezzi IVA esclusa.', ''],
            'piani.nota'   => ['piani', 'Nota sotto le schede', 'area', 'Abbonamento annuale con rinnovo automatico. Puoi disattivare il rinnovo dal tuo account quando vuoi: la guida resta online fino alla fine del periodo già pagato.', ''],
            // ---- porta un amico
            'amico.kicker'   => ['amico', 'Riga sopra il titolo', 'riga', 'Porta un amico', ''],
            'amico.titolo'   => ['amico', 'Titolo', 'area', "Più amici porti, meno paghi.\nIl prossimo anno fino al {totale} in meno.", '{totale} è lo sconto massimo sul rinnovo (inviti più «Giudizio con sconto», 9L: 55%), in evidenza; {max} quello dei soli inviti.'],
            'amico.lead'     => ['amico', 'Testo sotto il titolo', 'area', 'Conosci altri host, B&B o case vacanza? Mandagli il tuo link: loro hanno il {amico} di sconto sul primo anno, tu il {passo} in meno sul rinnovo per ogni amico che si abbona.', ''],
            // 9F · «Una lingua in più, oltre alle cinque di base» (Lingue): {bottoni} = le lingue con i bottoni tradotti (nederlands, português e polski)
            'lingue.extra.titolo'   => ['pannello', 'Titolo', 'riga', 'Una lingua in più, oltre alle cinque di base', ''],
            'lingue.extra.testo'    => ['pannello', 'Testo', 'area', 'Le cinque lingue di base sono italiano, inglese, francese, tedesco e spagnolo. La lingua in più serve in due casi: **se i tuoi ospiti parlano un\'altra lingua** (per esempio olandese o polacco), la aggiungi qui e la guida la mostra; **se tu scrivi la guida in una lingua diversa dall\'italiano**, la tua è la lingua principale e le cinque di base restano tutte.', ''],
            'lingue.extra.esempio1' => ['pannello', 'Esempio 1', 'area', "Scrivi in italiano, ospiti dall'Olanda\nLingua principale: Italiano · Lingua in più: Nederlands. La guida esce in 6 lingue.", 'La prima riga è il titolo dell\'esempio.'],
            'lingue.extra.esempio2' => ['pannello', 'Esempio 2', 'area', "Scrivi in polacco\nLingua principale: Polski · Lingua in più: nessuna. Le cinque di base ci sono già, tradotte dal polacco.", 'La prima riga è il titolo dell\'esempio.'],
            'lingue.extra.nota'     => ['pannello', 'Riga finale', 'riga', 'L\'interfaccia della guida (bottoni e titoli) è tradotta per {bottoni}; per le altre lingue in più i bottoni sono in inglese e i testi nella lingua scelta.', '{bottoni} = le lingue con i bottoni tradotti.'],
            'lingue.extra.essential' => ['pannello', 'Con Essential', 'riga', 'Con Essential hai 2 lingue: la tua e una a scelta. Con Plus e Portfolio le cinque di base e una lingua in più.', ''],
            // 9F · il testo di partenza del messaggio degli inviti nel pannello (WhatsApp, email, «Invita per email»): {amico} = la percentuale
            'inviti.messaggio_partenza' => ['amico', 'Messaggio di partenza degli inviti (pannello)', 'area', 'Ciao! Uso MyHouse Welcome per la guida digitale della mia struttura e mi trovo bene. Con questo invito hai il {amico} di sconto sul primo anno: crei la guida gratis e paghi solo quando la pubblichi.', 'Lo vede l\'host in «Invita un amico», modificabile prima di mandarlo. {amico} diventa la percentuale dell\'amico (per esempio 5%).'],
            'amico.1.titolo' => ['amico', 'Passo 1 · in grassetto', 'riga', 'Pubblichi la tua guida.', ''],
            'amico.1.testo'  => ['amico', 'Passo 1 · testo', 'riga', 'In «Invita un amico» trovi il tuo link personale.', ''],
            'amico.2.titolo' => ['amico', 'Passo 2 · in grassetto', 'riga', 'L\'amico si registra dal link.', ''],
            'amico.2.testo'  => ['amico', 'Passo 2 · testo', 'riga', 'Ha il {amico} di sconto sul primo anno, da solo.', ''],
            'amico.3.titolo' => ['amico', 'Passo 3 · in grassetto', 'riga', 'Quando paga, tu risparmi.', ''],
            'amico.3.testo'  => ['amico', 'Passo 3 · testo', 'riga', 'Il tuo rinnovo costa il {passo} in meno per ogni amico, fino al {max}.', ''],
            'amico.bottone'  => ['amico', 'Bottone «Come funziona»', 'riga', 'Come funziona', 'Apre la domanda «Come funziona «Porta un amico»?».'],
            // 9L · Lo sconto massimo sul rinnovo, con il numero grande: home (Porta un amico, Le guide dei nostri host), inviti, questionario.
            'risparmio.testo'    => ['amico', 'Sconto massimo · inviti e giudizio', 'area', '**Fino al {totale} di sconto a partire dal prossimo anno.** Porta un amico: {passo} a testa sul rinnovo, fino al {max} con {amici} amici. Aderisci al programma “Giudizio con sconto”, con la tua guida in questa pagina: un altro {giudizio}.', 'Con gli inviti e il programma accesi. {totale} = {max} + {giudizio} (di serie 55%).'],
            'risparmio.inviti'   => ['amico', 'Sconto massimo · solo inviti', 'area', '**Fino al {max} di sconto a partire dal prossimo anno.** Porta un amico: {passo} a testa sul rinnovo, fino al {max} con {amici} amici.', 'Con il programma «Giudizio con sconto» spento.'],
            'risparmio.giudizio' => ['amico', 'Sconto massimo · solo giudizio', 'area', '**Il {giudizio} di sconto.** Aderisci al programma “Giudizio con sconto”, con la tua guida in questa pagina.', 'Con gli inviti spenti.'],
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
            'chi.testo'       => ['chiusura', 'Riquadro «chi c\'è dietro» · testo', 'area', 'MyHouse Welcome è un servizio di **City Up S.r.l.** e **Blackout Agency**: oltre vent\'anni di esperienza nella realizzazione di piattaforme digitali, oggi al servizio di chi ospita.', ''],
            // 9K · il link sotto il riquadro, in una scheda nuova
            'chi.link.testo'  => ['chiusura', 'Riquadro «chi c\'è dietro» · link', 'riga', 'Vuoi vedere altri lavori?', 'La freccia la aggiunge il sito. Vuoto, il link non compare.'],
            'chi.link'        => ['chiusura', 'Riquadro «chi c\'è dietro» · indirizzo del link', 'riga', 'https://blackout.in', 'Un indirizzo completo, con https://. Si apre in una scheda nuova.'],
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
            'sito2.privacy'  => ['sito', 'Passo 3 · a chi vanno le risposte', 'area', 'Le risposte e il contatto li condividiamo con **Blackout Agency** (blackout.in), la società che realizza i siti MyHouse: ti ricontatta lei. {informativa}', '8J. {informativa} diventa il link all\'informativa privacy.'],
            'sito2.oggetto'  => ['sito', 'Oggetto dell\'email', 'riga', 'MyHouse siti: informazioni', 'Arriva all\'email commerciale (9H).'],
            'sito2.msg.home' => ['sito', 'Messaggio · prima frase (home)', 'riga', 'Ciao, ho una struttura ricettiva.', ''],
            'sito2.msg.pannello' => ['sito', 'Messaggio · prima frase (pannello)', 'riga', 'Ciao, ho una guida MyHouse Welcome per {struttura}.', '{struttura}: il nome della struttura.'],
            'sito2.msg.si'   => ['sito', 'Messaggio · chi ha il sito', 'riga', 'Ho già un sito e vorrei migliorarlo{bisogni}.', '{bisogni}: «: farmi trovare su Google, …» con le scelte, o niente.'],
            'sito2.msg.no'   => ['sito', 'Messaggio · chi non ha il sito', 'riga', 'Non ho ancora un sito per la mia struttura e vorrei crearlo.', ''],
            'sito2.msg.fine' => ['sito', 'Messaggio · ultima frase', 'riga', 'Mi date una proposta?', ''],
            // ---- 8H · il banner del consenso (solo con Google Analytics o Clarity in SEO e GEO)
            'consenso.testo'     => ['consenso', 'Testo del banner', 'area', 'Usiamo cookie tecnici e, solo con il tuo consenso, {finalita} per migliorare il sito.', '{finalita} diventa da solo «cookie di statistica e di analisi dell\'uso» (o solo una delle due), secondo gli strumenti configurati. I nomi dei fornitori sono in «Scegli» e nell\'informativa; se li vuoi anche qui, usa {strumenti}.'],
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
            'cookie.tecnici' => ['cookie', 'Cookie tecnici', 'area', 'Il sito usa solo cookie tecnici, che non richiedono il consenso: quello della sessione (mhw_sess), che tiene aperto l\'accesso al pannello e protegge i moduli, quello che ricorda la tua scelta sui cookie (mhw_consenso, 6 mesi) e, nel pannello, quello che nasconde per 24 ore l\'avviso del rinnovo quando premi «Chiudi» (mhw_fascia_…).', ''],
            'cookie.matomo'  => ['cookie', 'Matomo (compare solo se è configurato)', 'area', 'Per contare le visite usiamo Matomo, installato sul nostro server: non usa cookie, l\'indirizzo IP viene anonimizzato (gli ultimi due byte) e i dati restano da noi, senza passare a terzi.', ''],
            'cookie.ga4'     => ['cookie', 'Google Analytics (compare solo se è configurato)', 'area', '**Google Analytics 4** (Google Ireland Ltd), solo con il tuo consenso alle «Statistiche»: misura le visite in forma aggregata, senza segnali pubblicitari né personalizzazione degli annunci. Cookie _ga e _ga_*, durata fino a 2 anni. [Informativa di Google](https://policies.google.com/privacy).', ''],
            'cookie.clarity' => ['cookie', 'Microsoft Clarity (compare solo se è configurato)', 'area', '**Microsoft Clarity** (Microsoft Ireland Operations Ltd), solo con il tuo consenso all\'«Analisi dell\'uso»: mappe di calore e registrazioni delle visite per capire dove il sito non è chiaro; i campi dei moduli sono mascherati. Cookie _clck (1 anno) e _clsk (1 giorno). [Informativa di Microsoft](https://privacy.microsoft.com/privacystatement).', ''],
            'cookie.revoca'  => ['cookie', 'Come cambiare idea (compare con Google Analytics o Clarity)', 'area', 'Puoi cambiare idea quando vuoi da «Preferenze cookie», in fondo alle pagine del sito: se revochi il consenso, i cookie di Google Analytics e di Clarity vengono cancellati.', ''],
            'cookie.guide'   => ['cookie', 'Le guide degli ospiti', 'area', 'Le guide degli ospiti non usano cookie né statistiche di terzi: contano solo eventi anonimi per l\'host, come scritto sopra.', ''],
        ];
        // 8J · i documenti legali: testi di serie in lang/legale.php; sezione, tipo ed etichetta vengono dalla chiave.
        $leg = self::legale();
        foreach ($leg['it'] ?? [] as $k => $v) {
            [$sez, $parte] = array_pad(explode('.', $k, 3), 2, '');
            $ultima = substr($k, strrpos($k, '.') + 1);
            $tipo = $k === 'legale.novita' ? 'lista' : ($ultima === 'titolo' ? 'riga' : 'lungo');
            $nome = ctype_digit($parte) ? 'Punto ' . $parte : (preg_match('/^t(\d+)$/', $parte, $m) ? 'Trattamento ' . $m[1] : ($parte === 'b' ? 'Allegato B' : ucfirst($parte)));
            $titolo = $leg['it'][$sez . '.' . $parte . '.titolo'] ?? '';
            $etichetta = match (true) {
                $k === 'legale.novita' => 'Le novità, una per riga (tre)',
                $ultima === 'intro' => 'Introduzione, in cima',
                $ultima === 'titolo' => $nome . ' · titolo',
                default => $nome . ' · testo' . ($titolo !== '' ? ' («' . $titolo . '»)' : ''),
            };
            $c[$k] = [$sez, $etichetta, $tipo, $v, ''];
        }
        return $c;
    }

    /** 8J · lang/legale.php: ['it' => [...], 'en' => [...]]. */
    private static function legale(): array
    {
        static $l = null;
        if ($l === null) { $f = MHW_APP . '/lang/legale.php'; $l = is_file($f) ? (array) require $f : []; }
        return $l;
    }

    /** Le foto della home: nome => [etichetta, file di serie in assets/foto, consiglio]. */
    public const IMMAGINI = [
        'telefono'     => ['La schermata nel telefono, in cima', 'checco-telefono.jpg', 'Orizzontale, 600 × 422 px: è la foto che l\'ospite vede sotto «Benvenuti». Di serie il portone di Casa Checco.'],
        'scena-qr'     => ['Prima scena: il QR all\'ingresso', 'scena-qr.jpg', '1200 × 750 px. Se manca, la scena mostra un disegno.'],
        'scena-ospite' => ['Seconda scena: l\'ospite', 'scena-ospite.jpg', '1200 × 750 px.'],
        'scena-host'   => ['Terza scena: l\'host', 'scena-host.jpg', '1200 × 750 px.'],
        'pannello-1'   => ['Come funziona · schermata del passo 1', 'pannello-1.webp', '1200 × 750 px. Le tre schermate si vedono solo se ci sono tutte e tre.'],
        'pannello-2'   => ['Come funziona · schermata del passo 2', 'pannello-2.webp', ''],
        'pannello-3'   => ['Come funziona · schermata del passo 3', 'pannello-3.webp', ''],
        // 9K · le foto delle sei schede «Per chi è» (4:3, si ritagliano da sole)
        'perchi-case-vacanza'      => ['Per chi è · Casa vacanza', 'perchi-case-vacanza.jpg', '4:3, almeno 720 × 540 px.'],
        'perchi-bed-and-breakfast' => ['Per chi è · Bed & Breakfast', 'perchi-bed-and-breakfast.jpg', '4:3, almeno 720 × 540 px.'],
        'perchi-affittacamere'     => ['Per chi è · Affittacamere', 'perchi-affittacamere.jpg', '4:3, almeno 720 × 540 px.'],
        'perchi-agriturismi'       => ['Per chi è · Agriturismo', 'perchi-agriturismi.jpg', '4:3, almeno 720 × 540 px.'],
        'perchi-piu-strutture'     => ['Per chi è · Più strutture', 'perchi-piu-strutture.jpg', '4:3, almeno 720 × 540 px.'],
        'perchi-property-manager'  => ['Per chi è · Property manager', 'perchi-property-manager.jpg', '4:3, almeno 720 × 540 px.'],
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
        if ($en === null) { $f = MHW_APP . '/lang/sito-en.php'; $en = (is_file($f) ? (array) require $f : []) + (self::legale()['en'] ?? []); }
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
