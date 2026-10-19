<?php
namespace MHW;

/**
 * Le domande frequenti, in un posto solo: le mostrano la landing (le prime otto, poi
 * «Altre domande») e la pagina /domande, e le leggono i dati strutturati (FAQPage) e llms.txt.
 * Le parti che dipendono dal listino (Portfolio, inviti) si calcolano qui.
 */
final class Faq
{
    /** Le domande sempre visibili nella landing, in quest'ordine; le altre vanno sotto «Altre domande». */
    public const IN_VISTA = ['prova', 'app', 'tempo', 'lingue', 'traduzioni', 'piu-strutture', 'fattura', 'disdire'];

    /** @return array<int,array{id:string,d:string,r:string}> nell'ordine di sempre */
    public static function tutte(?string $loc = null): array
    {
        // 8E: in inglese le stesse domande, con gli stessi id (lang/faq-en.php); le parti calcolate arrivano da qui.
        if (($loc ?? LinguaSito::get()) === 'en' && is_file(MHW_APP . '/lang/faq-en.php')) {
            $it = self::tutte('it');
            $pfE = null;
            foreach (Plans::public() as $p) if (Plans::perProperty($p)) $pfE = $p;
            $m = fn(int $c, string $cur = 'EUR') => '€' . number_format($c / 100, $c % 100 ? 2 : 0, '.', ',');
            $ord = fn(int $n) => $n . (in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'));
            $tiers = '';
            if ($pfE && count(Plans::tiers($pfE)) > 1) {
                $tiers = implode(', ', array_map(fn($t) => ($t['a'] === null ? ($t['da'] === 2 ? 'from the 2nd on' : 'beyond the ' . $ord($t['da'] - 1))
                    : ($t['a'] === $t['da'] ? 'the ' . $ord($t['da']) : 'from the ' . $ord($t['da']) . ' to the ' . $ord($t['a']))) . ' ' . $m($t['cents']) . ($t['a'] === $t['da'] ? '' : ' each'), Plans::tiers($pfE)));
            }
            $en = (require MHW_APP . '/lang/faq-en.php')([
                'prima' => $pfE ? $m((int) $pfE['price_cents']) : '', 'tiers' => $tiers,
                'unica' => $pfE ? $m(Plans::unitPrice($pfE, 2)) : '', 'max_q' => $pfE ? (int) $pfE['max_quantity'] : 0,
                'amico' => Inviti::AMICO, 'passo' => Inviti::PASSO, 'massimo' => Inviti::MASSIMO, 'amici' => Inviti::amiciMassimi(),
                'giudizio' => Giudizi::attivo() ? Giudizi::percentuale() : 0, 'totale' => Sconti::massimoRinnovo()['totale'],
            ]);
            return array_map(fn($x) => isset($en[$x['id']]) ? ['id' => $x['id'], 'd' => $en[$x['id']][0], 'r' => $en[$x['id']][1]] : $x, $it);
        }
        $pf = null;
        foreach (Plans::public() as $p) if (Plans::perProperty($p)) $pf = $p;
        $f = [
            ['prova', 'Posso provarla prima di pagare?', 'Sì. Crei l\'account senza carta di credito, prepari la guida e la guardi in anteprima sul telefono, come la vedranno gli ospiti. Paghi solo quando decidi di pubblicarla.'],
            ['app', 'Serve un\'app?', 'No. La guida si apre nel browser del telefono, da un link o dal QR Code. Gli ospiti non scaricano niente, e nemmeno tu: il pannello funziona dal computer e dal telefono.'],
            ['tempo', 'Quanto ci vuole per prepararla?', 'Per cominciare bastano il nome della struttura e la città. Check-in e Wi-Fi si compilano in pochi minuti; il resto lo aggiungi quando vuoi, una sezione alla volta. Si salva mentre scrivi.'],
            ['lingue', 'In quali lingue la leggono gli ospiti?', 'Con Essential in due lingue: la tua e una a scelta. Con Plus e Portfolio in sei: la tua, le altre tra italiano, inglese, francese, tedesco e spagnolo, e una lingua in più a scelta tra olandese, portoghese del Portogallo, portoghese del Brasile, polacco, russo, cinese, giapponese, svedese, danese e ceco. Alla prima apertura l\'ospite sceglie la lingua, con quella del suo telefono in cima. Le traduzioni le scrivi tu, accanto al testo originale; i titoli delle sezioni, le categorie e le etichette dei luoghi sono già tradotti (per russo, cinese, giapponese, svedese, danese e ceco i bottoni della guida sono in inglese).'],
            ['lingua-principale', 'Posso scrivere la guida in un\'altra lingua?', 'Sì, in quella che vuoi. In Impostazioni, alla voce «In che lingua scrivi la guida?», di serie c\'è l\'italiano, ma puoi scegliere l\'inglese, il tedesco, il russo, il portoghese del Brasile o una delle altre: è la lingua dei tuoi testi e quella da cui partono le traduzioni suggerite. Con Essential hai la tua lingua e una seconda, quella che vuoi. Con Plus e Portfolio, se scrivi in una delle cinque lingue di base hai anche le altre e una lingua in più; se scrivi in un\'altra lingua, quella occupa il posto della lingua in più e hai tutte e cinque le lingue di base. I bottoni e i titoli della guida sono tradotti nelle lingue di base e in olandese, portoghese e polacco; per le altre lingue l\'ospite li legge in inglese. Il pannello resta in italiano. Se cambi la lingua dopo, i testi già scritti restano nella lingua in cui li hai scritti e diventano una traduzione.'],
            ['traduzioni', 'Le traduzioni me le fate voi?', 'Con Plus e Portfolio te le suggerisce un traduttore automatico (Amazon Translate), comprese nel piano entro un limite annuale di caratteri che vedi nel pannello. Nella pagina di ogni lingua tocchi «Traduci» accanto a un campo, oppure «Traduci tutti i campi mancanti», e controlli le proposte una per una: gli ospiti vedono solo quelle che approvi. Sono proposte di un traduttore automatico: per i testi importanti, falli rileggere a un madrelingua.'],
            ['traduzioni-suggerite', 'Le traduzioni suggerite cambiano quello che ho già scritto?', 'No. Si suggerisce solo dove la traduzione manca: quello che hai tradotto tu non si tocca. Se poi cambi il testo originale, la suggerita va rifatta, e te lo diciamo.'],
            ['sezione-libera', 'Posso aggiungere una sezione tutta mia?', 'Sì. Oltre alle sezioni pronte c\'è la «Sezione libera» («Informazioni utili» di serie: titolo, icona e testo li scegli tu), una per guida, in tutti i piani. Con Essential conta come una delle 6 sezioni aggiuntive; con Plus e Portfolio è compresa come le altre.'],
            ['recensioni', 'Come chiedo una recensione agli ospiti?', 'In QR & Link trovi il messaggio di fine soggiorno, pronto da copiare nella lingua dell\'ospite: lo mandi su WhatsApp o nella chat di Booking e Airbnb. Il link apre una pagina in cui l\'ospite dà un voto da 1 a 5. A chi dà 5 stelle mostriamo i bottoni dei portali che hai spuntato in Impostazioni («Su quali piattaforme si trova la tua struttura?»): i link li prepariamo noi. Da 1 a 4 stelle l\'ospite può scriverti su WhatsApp, per dirti cosa migliorare.'],
            ['qr', 'Posso cambiare i testi dopo aver stampato il QR?', 'Sì, quando vuoi. Il QR Code non cambia mai, anche se modifichi la guida: pubblichi la nuova versione e chi inquadra il QR stampato vede già quella. Resta valido finché l\'abbonamento è attivo.'],
            ['piu-strutture', 'Ho più di una struttura: come funziona?', 'Con Portfolio le gestisci tutte dallo stesso account: ognuna ha la sua guida, il suo QR Code e le sue statistiche. Le sezioni che valgono per tutte, come i ristoranti o le regole, le scrivi una volta e le copi nelle altre.'],
        ];
        if ($pf) {
            $tiers = Plans::tiers($pf);
            $f[] = ['costo-portfolio', 'Quanto costa ogni struttura con Portfolio?', 'La prima costa ' . Support::money((int) $pf['price_cents'], $pf['currency']) . '; '
                . (count($tiers) > 1
                    ? 'poi ogni struttura in più costa meno, a scaglioni: ' . implode(', ', array_map(fn($t) => Plans::tierLabel($t, (int) $pf['max_quantity']) . ' ' . Support::money($t['cents'], $pf['currency']) . ($t['a'] === $t['da'] ? '' : " l'una"), $tiers))
                    : 'ogni struttura in più costa ' . Support::money(Plans::unitPrice($pf, 2), $pf['currency']) . ', fino a ' . (int) $pf['max_quantity'] . ' strutture (per di più, scrivici)')
                . '. Prezzi annuali, IVA esclusa. Nel listino scegli quante strutture ti servono e vedi il totale e quanto costa in media ognuna; dentro il pannello trovi il conto struttura per struttura.'];
        }
        array_push($f,
            ['bnb-camere', 'Ho un B&B, un affittacamere o un agriturismo con più camere: mi servono più guide?', 'No. Più camere allo stesso indirizzo e con lo stesso CIN sono una struttura sola: basta una guida. Se le camere hanno reti Wi-Fi diverse, nella sezione Wi-Fi aggiungi una riga per ogni zona.'],
            ['piu-case', 'Posso mettere più case in una guida sola?', 'No. Una guida corrisponde a un\'unità ricettiva, con il suo indirizzo e il suo CIN, che serve per pubblicare e non può stare su due guide. Per più case o appartamenti c\'è Portfolio: ogni struttura ha la sua guida, e dalla seconda in poi costa meno.'],
            ['google', 'La mia guida si trova su Google?', 'No, ed è voluto, per la tua privacy e per quella dei tuoi ospiti. La guida contiene l\'indirizzo della casa, come si entra, il Wi-Fi e i tuoi contatti: informazioni utili a chi soggiorna, non a chiunque cerchi online. Per questo non compare su Google né sugli altri motori di ricerca, e gli assistenti come ChatGPT non la leggono: la apre solo chi ha il link o inquadra il QR. Le pagine di MyHouse Welcome che spiegano il servizio, invece, si trovano normalmente.'],
            ['codice-porta', 'Posso scrivere nella guida il codice della porta?', 'Te lo sconsigliamo. La guida si apre da un link, senza password: chi ha il link la legge, e un link si può inoltrare. Un codice scritto lì resterebbe leggibile anche dopo la partenza dell\'ospite, mettendo a rischio la casa e la privacy di chi ci soggiorna dopo. Per questo, se scrivi un codice di porte, cassette delle chiavi, casseforti o allarmi, la piattaforma ti avvisa e prima di pubblicare ti chiede di confermare che lo fai a tuo rischio. Nella guida spieghi come si entra e dove sono le chiavi; il codice lo comunichi all\'ospite di persona o in privato, poco prima dell\'arrivo.'],
            ['fattura', 'Ricevo fattura?', 'Sì. Prima del primo pagamento inserisci una volta i dati di fatturazione: per un\'azienda o un professionista partita IVA e codice destinatario SDI o PEC, per una persona fisica basta il codice fiscale. Le fatture le trovi in Account & Fatturazione.'],
            ['cambio-piano', 'Posso cambiare piano dopo?', 'Sì, quando vuoi, da Account & Fatturazione → «Cambia piano». Se sali (da Essential a Plus, da Plus a Portfolio, o aggiungi strutture al Portfolio) paghi oggi solo la differenza per i giorni che restano fino al rinnovo, e il nuovo piano vale appena il pagamento è confermato. La data di rinnovo non cambia. Se scendi, oggi non paghi niente: il cambio parte dal rinnovo e fino ad allora resti sul piano che hai già pagato.'],
            ['scendere-piano', 'Se scendo di piano perdo qualcosa?', 'Niente si cancella. Prima di confermare scegli quali sezioni tenere e, con Portfolio, quali strutture archiviare, e vedi l\'elenco di cosa la guida non mostrerà più (per esempio le lingue oltre la tua e una seconda). Quello che il piano nuovo non comprende resta salvato e torna appena risali. Puoi annullare il cambio fino al giorno prima del rinnovo.'],
        );
        if (Inviti::disponibili()) array_push($f,
            ['porta-amico', 'Come funziona «Porta un amico»?', 'Quando la tua guida è pubblicata, in «Invita un amico» trovi il tuo link personale. Chi si registra da quel link ha il ' . Inviti::AMICO . '% di sconto sul suo primo anno. Per ogni amico che paga il suo abbonamento, il tuo prossimo rinnovo costa il ' . Inviti::PASSO . '% in meno: con ' . Inviti::amiciMassimi() . ' amici arrivi al ' . Inviti::MASSIMO . '%. Lo sconto si applica da solo alla fattura del rinnovo, senza codici da inserire.'
                . (Giudizi::attivo() ? ' Con il programma «Giudizio con sconto» si aggiunge un altro ' . Giudizi::percentuale() . '%: fino al ' . Sconti::massimoRinnovo()['totale'] . '% di sconto a partire dal prossimo anno.' : '')],
            ['amico-quando', 'Quando conta un amico, e cosa succede dopo il rinnovo?', 'Un amico conta quando paga il suo primo abbonamento, con dati di fatturazione diversi dai tuoi (un\'altra partita IVA o un altro codice fiscale). Finché si è solo registrato, resta «in attesa». Lo sconto vale sul rinnovo successivo: dopo, il conteggio riparte da zero e gli amici nuovi valgono per l\'anno dopo. Oltre i ' . Inviti::amiciMassimi() . ' amici lo sconto non cresce. Se disattivi il rinnovo automatico, lo sconto non si usa: non diventa un rimborso.'],
        );
        // 9L · Il programma «Giudizio con sconto»: tre domande, con le stesse regole dei Termini.
        if (Giudizi::attivo()) { $gp = Giudizi::percentuale() . '%'; array_push($f,
            ['giudizio-sconto', 'Cos\'è il programma «Giudizio con sconto»?', 'Con un\'unica casella, al pagamento o dal pannello (Impostazioni → Giudizio con sconto), aderisci e hai il ' . $gp . ' di sconto: sul primo anno se aderisci al pagamento, sul rinnovo successivo se aderisci dopo. Si somma allo sconto degli inviti; non si somma ai codici sconto (vale il più alto) e non c\'è con i codici omaggio. In cambio, 30 giorni dopo la pubblicazione lasci un voto da 1 a 5 con un commento. Lo sconto non dipende dal voto: vale anche con una stella.'],
            ['giudizio-vetrina', 'La mia guida compare sul sito di MyHouse Welcome?', 'Solo se aderisci al programma «Giudizio con sconto»: da quando la pubblichi, tra «Le guide dei nostri host» nella nostra home, con il nome della struttura, la copertina, il logo, il tuo nome senza cognome e la città; dopo il giudizio anche le stelle e il commento. Non mostriamo link alla guida, la tua foto né dati dei tuoi ospiti. Quali schede mostrare lo decidiamo noi.'],
            ['giudizio-ritiro', 'Posso ritirarmi dal programma?', 'Sì, quando vuoi, da Impostazioni → Giudizio con sconto: la scheda e il giudizio spariscono subito dalla home. Lo sconto già avuto resta; quello promesso sul rinnovo decade.'],
        ); }
        array_push($f,
            ['disdire', 'Posso disdire?', 'Sì. Disattivi il rinnovo automatico da Account & Fatturazione quando vuoi: la guida resta online fino alla fine del periodo già pagato, poi va offline. Nessun vincolo.'],
            ['non-rinnovo', 'Cosa succede se non rinnovo?', 'Alla fine del periodo pagato la guida va offline da sola. Niente si cancella: testi, foto e QR restano salvati, e il QR stampato torna a funzionare appena rinnovi.'],
            ['tracciamento', 'Gli ospiti vengono tracciati?', 'No. La guida non usa cookie e non compare nei motori di ricerca. Le statistiche di lettura contano solo aperture anonime: nessun indirizzo IP, nessun profilo.'],
        );
        return array_map(fn($x) => ['id' => $x[0], 'd' => $x[1], 'r' => $x[2]], $f);
    }

    /** [in vista, altre]: le otto della landing nel loro ordine, poi tutte le altre nell'ordine di sempre. */
    public static function perLanding(): array
    {
        $tutte = self::tutte();
        $perId = array_column($tutte, null, 'id');
        $vista = array_values(array_filter(array_map(fn($id) => $perId[$id] ?? null, self::IN_VISTA)));
        $altre = array_values(array_filter($tutte, fn($x) => !in_array($x['id'], self::IN_VISTA, true)));
        return [$vista, $altre];
    }
}
