<?php
/**
 * Le novità per chi ha già fatto la guida (7C): la fascia nel pannello le mostra una per volta, la più recente
 * prima, finché l'account non la chiude (tabella novita_viste).
 *
 *   id      — unico e stabile (si salva in novita_viste)
 *   data    — AAAA-MM-GG (nella fascia si mostra prima la prima dell'elenco non ancora chiusa)
 *   titolo, testo — per la fascia
 *   dove    — «Mostrami dove»: indirizzo del pannello; {pid} diventa la guida aperta (o la prima dell'account)
 *   campi   — [tipo di sezione => [campi nuovi]]: finché sono vuoti, la sezione ha il pallino ocra e «Nuovo»
 *             in Contenuti, e il campo il bordo ocra nell'editor
 *   sezioni — [tipi di sezione nuovi]: «Nuovo» nel catalogo «Aggiungi una sezione»
 */
return [
    // 10 · «Chiedi alla guida»: l'assistente che risponde agli ospiti con il testo della guida (Plus e Portfolio).
    ['id' => '2026-10-assistente', 'data' => '2026-10-27', 'titolo' => 'La tua guida risponde agli ospiti: «Chiedi alla guida»',
     'testo' => 'Nella guida c\'è il bottone «Chiedi alla guida»: l\'ospite scrive una domanda nella sua lingua e l\'assistente risponde solo con quello che hai scritto tu, indicando la sezione. Se la guida non lo dice, mostra i tuoi contatti. In Impostazioni la accendi e la spegni, e vedi le domande rimaste senza risposta. Compresa in Plus e Portfolio.',
     'dove' => '/pannello/{pid}/impostazioni#chiedi-alla-guida', 'campi' => [], 'sezioni' => []],
    // 8C. «nota_aria»: la riga in più per chi ha una sezione «Aria condizionata» nata spenta (migrazione 037).
    ['id' => '2026-10-clima', 'data' => '2026-10-15', 'titolo' => 'Riscaldamento e aria condizionata ora sono due sezioni',
     'testo' => 'Ognuna ha la sua pagina nella guida, con lo stato «acceso adesso», gli orari e il consiglio per non sprecare energia. I tuoi testi sono già al loro posto.',
     'nota_aria' => 'La sezione «Aria condizionata» è nata disattivata perché eri al limite di sezioni del tuo piano: attivala in Contenuti, al posto di un\'altra, oppure passa a Plus.',
     'dove' => '/pannello/{pid}', 'campi' => [], 'sezioni' => ['riscaldamento', 'aria']],
    ['id' => '2026-10-lingue', 'data' => '2026-10-15', 'titolo' => 'Lingua principale a scelta e una lingua in più',
     'testo' => 'Puoi scrivere la guida nella lingua che vuoi. Con Plus e Portfolio, oltre alle lingue di base, ne aggiungi una a scelta tra olandese, portoghese (del Portogallo o del Brasile), polacco, russo, cinese, giapponese, svedese, danese e ceco.',
     'dove' => '/pannello/{pid}/lingue', 'campi' => [], 'sezioni' => []],
    ['id' => '2026-10-portali', 'data' => '2026-10-15', 'titolo' => 'Su quali piattaforme sei presente: spunta i portali, ai link pensiamo noi',
     'testo' => 'In Impostazioni spunti Booking.com, Airbnb, Google e gli altri: a chi dà 5 stelle mostriamo i bottoni per la recensione, con i link già pronti.',
     'dove' => '/pannello/{pid}/impostazioni#piattaforme', 'campi' => [], 'sezioni' => []],
    ['id' => '2026-10-host', 'data' => '2026-10-11', 'titolo' => 'La tua foto nella guida, anche con Essential',
     'testo' => 'La foto profilo ora compare in un tondo sopra la copertina: toccandola, gli ospiti leggono le tue due parole di benvenuto e ti scrivono.',
     'dove' => '/pannello/{pid}/aspetto', 'campi' => [], 'sezioni' => []],
    ['id' => '2026-10-stelle', 'data' => '2026-10-11', 'titolo' => 'Le stelle nel saluto di fine soggiorno',
     'testo' => 'Gli ospiti danno da una a cinque stelle e trovano i bottoni per la recensione; i voti, anonimi, li vedi in Statistiche.',
     'dove' => '/pannello/{pid}/impostazioni#dopo-il-soggiorno', 'campi' => [], 'sezioni' => []],
    ['id' => '2026-10-sconto', 'data' => '2026-10-11', 'titolo' => 'Lo sconto per chi torna',
     'testo' => 'Nel saluto di fine soggiorno, la scheda «La prossima volta prenota da noi» mostra il tuo codice e lo sconto, con «Copia il codice».',
     'dove' => '/pannello/{pid}/impostazioni#dopo-il-soggiorno', 'campi' => [], 'sezioni' => []],
    ['id' => '2026-10-foto', 'data' => '2026-10-11', 'titolo' => 'Le foto si ingrandiscono',
     'testo' => 'Nella guida, le foto dentro le sezioni (il cancello, la lavatrice, il parcheggio) si aprono a tutto schermo con un tocco, e si ingrandiscono con le dita.',
     'dove' => '/pannello/{pid}/anteprima', 'campi' => [], 'sezioni' => []],
    ['id' => '2026-10-traduzioni', 'data' => '2026-10-11', 'titolo' => 'Le traduzioni campo per campo',
     'testo' => 'Nella pagina di ogni lingua, accanto a ogni testo da tradurre c\'è «Traduci»: la proposta arriva da controllare e approvare. Se poi cambi l\'italiano, il campo te lo segnala.',
     'dove' => '/pannello/{pid}/lingue', 'campi' => [], 'sezioni' => []],
];
