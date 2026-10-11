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
