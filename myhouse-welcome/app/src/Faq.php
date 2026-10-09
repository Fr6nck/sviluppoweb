<?php
namespace MHW;

/**
 * Le domande frequenti, in un posto solo: le mostrano la landing (le prime otto, poi
 * «Altre domande») e la pagina /domande, e le leggono i dati strutturati (FAQPage) e llms.txt.
 * Le parti che dipendono dal listino (Portfolio, varianti camera, inviti) si calcolano qui.
 */
final class Faq
{
    /** Le domande sempre visibili nella landing, in quest'ordine; le altre vanno sotto «Altre domande». */
    public const IN_VISTA = ['prova', 'app', 'tempo', 'lingue', 'traduzioni', 'piu-strutture', 'fattura', 'disdire'];

    /** @return array<int,array{id:string,d:string,r:string}> nell'ordine di sempre */
    public static function tutte(): array
    {
        $pf = null;
        foreach (Plans::public() as $p) if (Plans::perProperty($p)) $pf = $p;
        $f = [
            ['prova', 'Posso provarla prima di pagare?', 'Sì. Crei l\'account senza carta di credito, prepari la guida e la guardi in anteprima sul telefono, come la vedranno gli ospiti. Paghi solo quando decidi di pubblicarla.'],
            ['app', 'Serve un\'app?', 'No. La guida si apre nel browser del telefono, da un link o dal QR Code. Gli ospiti non scaricano niente, e nemmeno tu: il pannello funziona dal computer e dal telefono.'],
            ['tempo', 'Quanto ci vuole per prepararla?', 'Per cominciare bastano il nome della struttura e la città. Check-in e Wi-Fi si compilano in pochi minuti; il resto lo aggiungi quando vuoi, una sezione alla volta. Si salva mentre scrivi.'],
            ['lingue', 'In quali lingue la leggono gli ospiti?', 'In italiano e in inglese con Essential; con Plus e Portfolio anche in francese, tedesco e spagnolo. La guida si apre da sola nella lingua del telefono dell\'ospite. Le traduzioni le scrivi tu, accanto al testo originale; i titoli delle sezioni, le categorie e le etichette dei luoghi sono già tradotti.'],
            ['traduzioni', 'Le traduzioni me le fate voi?', 'Con Plus e Portfolio te le suggerisce un traduttore automatico (Amazon Translate), in omaggio per un anno: tocchi «Suggerisci le traduzioni mancanti» e controlli le proposte una per una. Gli ospiti vedono solo quelle che approvi. Sono suggerite da un traduttore automatico, da approvare: per i testi importanti, falli rileggere a un madrelingua.'],
            ['traduzioni-suggerite', 'Le traduzioni suggerite cambiano quello che ho già scritto?', 'No. Si suggerisce solo dove la traduzione manca: quello che hai tradotto tu non si tocca. Se poi cambi il testo originale, la suggerita va rifatta, e te lo diciamo.'],
            ['qr', 'Posso cambiare i testi dopo aver stampato il QR?', 'Sì, quando vuoi. Il QR Code non cambia mai, anche se modifichi la guida: pubblichi la nuova versione e chi inquadra il QR stampato vede già quella. Resta valido finché l\'abbonamento è attivo.'],
            ['piu-strutture', 'Ho più di una struttura: come funziona?', 'Con Portfolio le gestisci tutte dallo stesso account: ognuna ha la sua guida, il suo QR Code e le sue statistiche. Le sezioni che valgono per tutte, come i ristoranti o le regole, le scrivi una volta e le copi nelle altre.'],
        ];
        if ($pf) {
            $tiers = Plans::tiers($pf);
            $f[] = ['costo-portfolio', 'Quanto costa ogni struttura con Portfolio?', 'La prima costa ' . Support::money((int) $pf['price_cents'], $pf['currency']) . ', come Plus; '
                . (count($tiers) > 1
                    ? 'poi ogni struttura in più costa meno, a scaglioni: ' . implode(', ', array_map(fn($t) => Plans::tierLabel($t) . ' ' . Support::money($t['cents'], $pf['currency']) . ($t['a'] === $t['da'] ? '' : " l'una"), $tiers))
                    : 'ogni struttura in più costa ' . Support::money(Plans::unitPrice($pf, 2), $pf['currency']) . ', fino a ' . (int) $pf['max_quantity'] . ' strutture (per di più, scrivici)')
                . '. Prezzi annuali, IVA esclusa. Nel listino scegli quante strutture ti servono e vedi il totale e quanto costa in media ognuna; dentro il pannello trovi il conto struttura per struttura.'];
        }
        array_push($f,
            ['bnb-camere', 'Ho un B&B, un affittacamere o un agriturismo con più camere: mi servono più guide?', 'No. Più camere allo stesso indirizzo e con lo stesso CIN sono una struttura sola: basta una guida. Se alcune camere hanno un Wi-Fi o istruzioni di accesso diverse, con Plus o Portfolio aggiungi una «variante camera» (' . Support::money(Varianti::prezzo()) . ' + IVA l\'anno l\'una): ha il suo QR, e chi lo inquadra vede la stessa guida con il Wi-Fi e le istruzioni della sua camera.'],
            ['piu-case', 'Posso mettere più case in una guida sola?', 'No. Una guida corrisponde a un\'unità ricettiva, con il suo indirizzo e il suo CIN, che serve per pubblicare e non può stare su due guide. Per più case o appartamenti c\'è Portfolio: ogni struttura ha la sua guida, e dalla seconda in poi costa meno.'],
            ['codice-porta', 'Posso scrivere nella guida il codice della porta?', 'Meglio di no. La guida si apre da un link, senza password: chi ha il link la legge. Nella guida spieghi come si entra; i codici di porte e cassette delle chiavi mandali all\'ospite in privato, poco prima dell\'arrivo.'],
            ['fattura', 'Ricevo fattura?', 'Sì. Prima del primo pagamento inserisci una volta i dati di fatturazione: per un\'azienda o un professionista partita IVA e codice destinatario SDI o PEC, per una persona fisica basta il codice fiscale. Le fatture le trovi in Account & Fatturazione.'],
            ['cambio-piano', 'Posso cambiare piano dopo?', 'Sì, quando vuoi, da Account & Fatturazione → «Cambia piano». Se sali (da Essential a Plus, da Plus a Portfolio, o aggiungi strutture al Portfolio) paghi oggi solo la differenza per i giorni che restano fino al rinnovo, e il nuovo piano vale appena il pagamento è confermato. La data di rinnovo non cambia. Se scendi, oggi non paghi niente: il cambio parte dal rinnovo e fino ad allora resti sul piano che hai già pagato.'],
            ['scendere-piano', 'Se scendo di piano perdo qualcosa?', 'Niente si cancella. Prima di confermare scegli quali sezioni tenere e, con Portfolio, quali strutture archiviare, e vedi l\'elenco di cosa la guida non mostrerà più (per esempio le lingue oltre italiano e inglese). Quello che il piano nuovo non comprende resta salvato e torna appena risali. Puoi annullare il cambio fino al giorno prima del rinnovo.'],
        );
        if (Inviti::disponibili()) array_push($f,
            ['porta-amico', 'Come funziona «Porta un amico»?', 'Quando la tua guida è pubblicata, in «Invita un amico» trovi il tuo link personale. Chi si registra da quel link ha il ' . Inviti::AMICO . '% di sconto sul suo primo anno. Per ogni amico che paga il suo abbonamento, il tuo prossimo rinnovo costa il ' . Inviti::PASSO . '% in meno: con ' . Inviti::amiciMassimi() . ' amici arrivi al ' . Inviti::MASSIMO . '%. Lo sconto si applica da solo alla fattura del rinnovo, senza codici da inserire.'],
            ['amico-quando', 'Quando conta un amico, e cosa succede dopo il rinnovo?', 'Un amico conta quando paga il suo primo abbonamento, con dati di fatturazione diversi dai tuoi (un\'altra partita IVA o un altro codice fiscale). Finché si è solo registrato, resta «in attesa». Lo sconto vale sul rinnovo successivo: dopo, il conteggio riparte da zero e gli amici nuovi valgono per l\'anno dopo. Oltre i ' . Inviti::amiciMassimi() . ' amici lo sconto non cresce. Se disattivi il rinnovo automatico, lo sconto non si usa: non diventa un rimborso.'],
        );
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
