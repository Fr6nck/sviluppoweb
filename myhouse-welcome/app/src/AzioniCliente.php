<?php
namespace MHW;

/**
 * 9E · Le azioni dell'amministrazione sulla scheda di un cliente: fermare o riattivare il rinnovo, cambiare il
 * pacchetto (subito con conguaglio, oppure dal rinnovo), rimborsare una fattura da Stripe, il link per reimpostare
 * la password e la password provvisoria. Ogni azione: solo amministratori, CSRF, motivo nel registro (audit_log),
 * e il cliente riceve un'email che dice cosa è cambiato. Niente logica doppia: si riusa CambioAbbonamento e Stripe.
 */
final class AzioniCliente
{
    private static function utente(int $accountId): array
    {
        $u = Db::one('SELECT u.*, a.id AS account_id FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$accountId]);
        if (!$u) throw new NotFound('Cliente non trovato.');
        return $u;
    }

    private static function nome(array $u): string
    {
        $n = trim(explode(' ', trim((string) ($u['name'] ?? '')))[0] ?? '');
        return 'Ciao' . ($n !== '' ? ' ' . $n : '');
    }

    private static function email(array $u, string $oggetto, array $paragrafi, ?array $bottone = null): void
    {
        try { Mailer::manda((string) $u['email'], $oggetto, $oggetto, $paragrafi, $bottone ?? ['Vai ad Account & Fatturazione', Support::baseUrl() . '/account']); }
        catch (\Throwable $e) { Log::exception($e, 'email al cliente (amministrazione)'); }
    }

    // ------------------------------------------------------------ 1 · rinnovo

    /** Ferma (o riattiva) il rinnovo automatico: lo stesso effetto di quando lo fa il cliente. @return string il messaggio per l'amministratore */
    public static function rinnovo(int $accountId, bool $ferma, string $motivo): string
    {
        $u = self::utente($accountId);
        $s = Subscriptions::active($accountId);
        if (!$s || $s['provider'] !== 'stripe' || (string) $s['provider_subscription_id'] === '') throw new \RuntimeException('Questo cliente non ha un abbonamento Stripe attivo: niente rinnovo da fermare.');
        if ($motivo === '') throw new \RuntimeException('Scrivi il motivo: resta nel registro.');
        Stripe::setCancelAtPeriodEnd((string) $s['provider_subscription_id'], $ferma);
        Db::update('subscriptions', ['cancel_at_period_end' => $ferma ? 1 : 0, 'updated_at' => Support::now()], 'id = :sid', ['sid' => (int) $s['id']]);
        Auth::audit($ferma ? 'admin.renewal_off' : 'admin.renewal_on', (int) $u['id'], ['subscription' => (int) $s['id'], 'motivo' => $motivo]);
        $fine = Support::date((string) $s['current_period_end']);
        $omaggio = !empty($s['omaggio_fino']) && $s['status'] === 'trialing';
        if ($ferma) {
            self::email($u, 'Il tuo abbonamento non si rinnoverà', [self::nome($u) . ', come concordato con la nostra assistenza abbiamo disattivato il rinnovo automatico del tuo abbonamento MyHouse Welcome.',
                $omaggio ? "La guida resta online fino alla fine dei mesi gratis, il $fine. Dopo quella data non ti verrà addebitato nulla e la guida non sarà più visibile agli ospiti: i contenuti restano salvati."
                         : "La guida resta online fino al $fine, fine del periodo già pagato. Dopo quella data non ti verrà addebitato nulla e la guida non sarà più visibile agli ospiti: i contenuti restano salvati.",
                'Se cambi idea, puoi riattivare il rinnovo quando vuoi da Account & Fatturazione.']);
            return 'Rinnovo fermato: resta attivo fino al ' . $fine . ', poi la guida va offline senza addebiti. Il cliente ha ricevuto un\'email.';
        }
        self::email($u, 'Il tuo abbonamento si rinnova di nuovo da solo', [self::nome($u) . ', come concordato con la nostra assistenza abbiamo riattivato il rinnovo automatico del tuo abbonamento MyHouse Welcome.',
            "Il prossimo addebito sarà il $fine: ti avvisiamo per email 30 e 7 giorni prima. Puoi disattivarlo quando vuoi da Account & Fatturazione."]);
        return 'Rinnovo riattivato: il prossimo addebito è il ' . $fine . '. Il cliente ha ricevuto un\'email.';
    }

    // ------------------------------------------------------------ 2 · cambio del pacchetto

    /**
     * L'anteprima del cambio: la versione scelta, la quantità, il conguaglio e cosa cambierebbe, con i due modi.
     * @return array{st:array,pv:array,q:int,prev:array,cambia:?array,addebito:array} @throws \RuntimeException
     */
    public static function anteprimaCambio(int $accountId, string $codice, mixed $strutture): array
    {
        $st = CambioAbbonamento::stato($accountId);
        if ($st['motivo'] === 'nessuno') throw new \RuntimeException('Il cliente non ha un abbonamento attivo: attiva un abbonamento, non un cambio.');
        if ($st['motivo'] !== '') throw new \RuntimeException($st['motivo']);
        $pv = CambioAbbonamento::versioneDi($codice);
        if (!$pv) throw new \RuntimeException('Scegli un piano in vendita.');
        $q = Plans::perProperty($pv) ? Plans::quantity($pv, ($strutture === null || $strutture === '') ? ((string) $st['pv']['code'] === $codice ? (string) $st['quantita'] : '') : $strutture) : 1;
        if ($q === null) throw new \RuntimeException('Il numero di strutture non va bene per questo piano.');
        $prev = CambioAbbonamento::preventivo($st['sub'], $st['pv'], $pv, $q);
        if ($prev['stesso']) throw new \RuntimeException('È già il suo piano.');
        $cambia = $prev['tipo'] === CambioPiano::SCENDE ? CambioAbbonamento::cosaCambia($accountId, $pv, $q) : null;
        // Dal rinnovo: il prezzo del pacchetto scelto meno gli sconti maturati (inviti, giudizio), come li conta Gestione::importoRinnovo.
        $finto = $st['sub']; $finto['next_package_version_id'] = $pv['id']; $finto['next_quantity'] = $q;
        [$netto, $pct] = Gestione::importoRinnovo($finto);
        return ['st' => $st, 'pv' => $pv, 'q' => $q, 'prev' => $prev, 'cambia' => $cambia, 'addebito' => ['netto' => $netto, 'percento' => $pct, 'listino' => Plans::price($pv, $q)]];
    }

    /**
     * a · Subito, con conguaglio: lo stesso ordine del cliente (6H); il cliente riceve l'email con il link di pagamento
     * della differenza e il cambio parte quando paga. Senza differenza (o scendendo) non si passa da qui.
     * @return string messaggio per l'amministratore
     */
    public static function cambiaSubito(int $accountId, string $codice, mixed $strutture, string $motivo): string
    {
        $u = self::utente($accountId);
        $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$accountId]);
        if ($motivo === '') throw new \RuntimeException('Scrivi il motivo: resta nel registro.');
        if (!Stripe::enabled()) throw new \RuntimeException('Stripe non è configurato.');
        $a = self::anteprimaCambio($accountId, $codice, $strutture);
        if ($a['prev']['tipo'] === CambioPiano::SCENDE) throw new \RuntimeException('Scendere di piano si fa dal rinnovo: scegli «Dal rinnovo».');
        if (!Fatturazione::completa($acc)) throw new \RuntimeException('Mancano i dati di fatturazione del cliente: senza, non si può emettere la fattura della differenza.');
        $descr = CambioAbbonamento::descrizione($a['st']['pv'], $a['st']['quantita'], $a['pv'], $a['q']);
        if ($a['prev']['conguaglio'] === 0) {
            CambioAbbonamento::subito($a['st']['sub'], $a['pv'], $a['q']);
            Auth::audit('admin.plan_change', (int) $u['id'], ['a' => ['pv' => (int) $a['pv']['id'], 'q' => $a['q']], 'come' => 'subito', 'motivo' => $motivo]);
            self::email($u, 'Il tuo piano è cambiato', [self::nome($u) . ', come concordato con la nostra assistenza il tuo abbonamento è passato a ' . $a['pv']['name'] . (Plans::perProperty($a['pv']) ? ' (' . $a['q'] . ' strutture)' : '') . ', senza differenze da pagare.']);
            return 'Fatto: ora è su ' . $a['pv']['name'] . '. Il cliente ha ricevuto un\'email.';
        }
        $o = CambioAbbonamento::ordine($acc, $a['st']['sub'], $a['pv'], $a['q'], $a['prev']['conguaglio']);
        $url = Stripe::checkoutChange($o, $descr, $acc, $u);
        Auth::audit('admin.plan_change_requested', (int) $u['id'], ['order' => (int) $o['id'], 'a' => ['pv' => (int) $a['pv']['id'], 'q' => $a['q']], 'conguaglio' => $a['prev']['conguaglio'], 'motivo' => $motivo]);
        self::email($u, 'Il passaggio a ' . $a['pv']['name'] . ': paga la differenza e parte subito', [
            self::nome($u) . ', come concordato con la nostra assistenza abbiamo preparato il passaggio a ' . $a['pv']['name'] . (Plans::perProperty($a['pv']) ? ' (' . $a['q'] . ' strutture)' : '') . '.',
            'Per i ' . $a['prev']['giorni'] . ' giorni che restano fino al rinnovo la differenza è di ' . Support::money($a['prev']['conguaglio'], (string) $a['pv']['currency']) . ' + IVA: la paghi dal bottone qui sotto, su Stripe, e il piano nuovo parte appena il pagamento è confermato. La data di rinnovo non cambia.',
            'Niente viene addebitato sulla tua carta senza il tuo clic. Il link vale 24 ore.'], ['Paga la differenza e cambia piano', $url]);
        return 'Ordine preparato: ' . Support::money($a['prev']['conguaglio'], (string) $a['pv']['currency']) . ' + IVA. Il cliente ha ricevuto l\'email con il link di pagamento; il cambio parte quando paga.';
    }

    /** c · Dal rinnovo: il cambio programmato (subscriptions.pending/next_*), con il prezzo nuovo meno gli sconti maturati. */
    public static function cambiaDalRinnovo(int $accountId, string $codice, mixed $strutture, string $motivo): string
    {
        $u = self::utente($accountId);
        if ($motivo === '') throw new \RuntimeException('Scrivi il motivo: resta nel registro.');
        if (!Stripe::enabled()) throw new \RuntimeException('Stripe non è configurato.');
        $a = self::anteprimaCambio($accountId, $codice, $strutture);
        // Nessuna scelta dell'amministratore su sezioni e strutture: al rinnovo valgono le regole automatiche (le ultime create).
        CambioAbbonamento::programma($a['st']['sub'], $a['pv'], $a['q'], ['sezioni' => [], 'archivia' => []]);
        Auth::audit('admin.plan_change_scheduled', (int) $u['id'], ['a' => ['pv' => (int) $a['pv']['id'], 'q' => $a['q']], 'dal' => $a['prev']['fine'], 'motivo' => $motivo]);
        $quando = Support::date($a['prev']['fine']);
        self::email($u, 'Dal ' . $quando . ' passi a ' . $a['pv']['name'], [
            self::nome($u) . ', come concordato con la nostra assistenza dal rinnovo del ' . $quando . ' il tuo abbonamento passa a ' . $a['pv']['name'] . (Plans::perProperty($a['pv']) ? ' (' . $a['q'] . ' strutture)' : '') . '.',
            'Fino ad allora resti sul piano di adesso. Al rinnovo pagherai ' . Support::money($a['addebito']['netto'], (string) $a['pv']['currency']) . ' + IVA' . ($a['addebito']['percento'] > 0 ? ' (con il ' . $a['addebito']['percento'] . '% di sconto maturato)' : '') . ': ti avvisiamo 30 e 7 giorni prima.',
            'Se cambi idea, puoi annullare il cambio da Account & Fatturazione.']);
        return 'Programmato: dal ' . $quando . ' passa a ' . $a['pv']['name'] . ' a ' . Support::money($a['addebito']['netto'], (string) $a['pv']['currency']) . ' + IVA. Il cliente ha ricevuto un\'email.';
    }

    // ------------------------------------------------------------ 3 · rimborsi

    public const MOTIVI_RIMBORSO = ['duplicate' => 'Pagamento doppio', 'requested_by_customer' => 'Chiesto dal cliente', 'other' => 'Errore nostro / altro'];

    /** Le ultime fatture pagate del cliente, da Stripe, con i rimborsi già fatti (tabella rimborsi). */
    public static function fatture(array $acc): array
    {
        if (!Stripe::enabled() || (string) ($acc['stripe_customer_id'] ?? '') === '') return [];
        try { $inv = Stripe::fatturePagate((string) $acc['stripe_customer_id']); } catch (\Throwable $e) { Log::exception($e, 'fatture del cliente'); return []; }
        $fatti = Migrator::tableExists('rimborsi') ? Db::all('SELECT * FROM rimborsi WHERE account_id = ? ORDER BY id DESC', [(int) $acc['id']]) : [];
        $out = [];
        foreach ($inv as $i) {
            $id = (string) ($i['id'] ?? '');
            $rimb = array_values(array_filter($fatti, fn($r) => $r['invoice_id'] === $id));
            $out[] = ['id' => $id, 'numero' => (string) ($i['number'] ?? $id), 'data' => gmdate('Y-m-d\TH:i:s\Z', (int) ($i['created'] ?? 0)),
                      'netto' => (int) ($i['total_excluding_tax'] ?? $i['subtotal'] ?? 0), 'totale' => (int) ($i['total'] ?? 0), 'valuta' => strtoupper((string) ($i['currency'] ?? 'eur')),
                      'payment_intent' => is_array($i['payment_intent'] ?? null) ? (string) ($i['payment_intent']['id'] ?? '') : (string) ($i['payment_intent'] ?? ''),
                      'rimborsato' => array_sum(array_map(fn($r) => (int) $r['amount_cents'], $rimb)), 'rimborsi' => $rimb];
        }
        return $out;
    }

    /** Il rimborso, totale o parziale, di una fattura pagata. Non tocca l'abbonamento. @return string messaggio per l'amministratore */
    public static function rimborsa(int $accountId, string $invoiceId, string $importo, string $motivo, string $testo, int $adminId): string
    {
        $u = self::utente($accountId);
        $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$accountId]);
        if (!isset(self::MOTIVI_RIMBORSO[$motivo])) throw new \RuntimeException('Scegli il motivo del rimborso.');
        if ($motivo === 'other' && trim($testo) === '') throw new \RuntimeException('Con «altro» scrivi il motivo: resta nel registro.');
        if (!Stripe::enabled()) throw new \RuntimeException('Stripe non è configurato.');
        $f = null;
        foreach (self::fatture($acc) as $x) if ($x['id'] === $invoiceId) $f = $x;
        if (!$f) throw new \RuntimeException('Fattura non trovata tra le ultime pagate del cliente.');
        if ($f['payment_intent'] === '') throw new \RuntimeException('Questa fattura non ha un pagamento da rimborsare su Stripe.');
        $grezzo = str_replace(',', '.', trim($importo));
        $cents = $grezzo === '' ? (int) $f['totale'] : (int) round((float) $grezzo * 100);
        if (!is_numeric($grezzo) && $grezzo !== '') throw new \RuntimeException('Scrivi l\'importo da rimborsare, per esempio 127,00.');
        if ($cents <= 0 || $cents > (int) $f['totale'] - (int) $f['rimborsato']) throw new \RuntimeException('L\'importo va da 0,01 a ' . Support::money((int) $f['totale'] - (int) $f['rimborsato'], $f['valuta']) . ' (il totale della fattura, meno quanto già rimborsato).');
        $r = Stripe::rimborsa($f['payment_intent'], $cents, $motivo, $invoiceId);
        $id = Db::insert('rimborsi', ['account_id' => $accountId, 'invoice_id' => $invoiceId, 'charge_id' => (string) ($r['charge'] ?? ''), 'refund_id' => (string) ($r['id'] ?? ''),
                                      'amount_cents' => $cents, 'motivo' => $motivo, 'testo' => mb_substr(trim($testo), 0, 255), 'stato' => ($r['status'] ?? '') === 'succeeded' ? 'confermato' : 'richiesto',
                                      'creato_da' => $adminId, 'created_at' => Support::now(), 'confermato_at' => ($r['status'] ?? '') === 'succeeded' ? Support::now() : null]);
        Auth::audit('refund.requested', (int) $u['id'], ['rimborso' => $id, 'invoice' => $invoiceId, 'amount' => $cents, 'motivo' => $motivo, 'testo' => mb_substr(trim($testo), 0, 255)]);
        self::email($u, 'Ti abbiamo rimborsato ' . Support::money($cents, $f['valuta']), [
            self::nome($u) . ', abbiamo disposto un rimborso di ' . Support::money($cents, $f['valuta']) . ' sulla fattura ' . $f['numero'] . ' di MyHouse Welcome.',
            'Lo vedrai sulla carta usata per il pagamento entro 5–10 giorni lavorativi, a seconda della tua banca. Il tuo abbonamento non cambia.',
            'Per la fattura elettronica riceverai la nota di credito con gli stessi dati di fatturazione.']);
        return 'Rimborso di ' . Support::money($cents, $f['valuta']) . ' disposto su Stripe (fattura ' . $f['numero'] . '). Il cliente ha ricevuto un\'email. Nota di credito da fare in Adamo.';
    }

    // ------------------------------------------------------------ 4 · password

    /** Il link per reimpostare la password: lo stesso flusso di «Password dimenticata» (token di un'ora). */
    public static function linkReset(int $accountId): string
    {
        $u = self::utente($accountId);
        Auth::sendPasswordReset((string) $u['email']);
        Auth::audit('admin.password_link', (int) $u['id']);
        return 'Link mandato a ' . $u['email'] . ': vale un\'ora.';
    }

    private const PAROLE = ['tavolo', 'ruota', 'ponte', 'fiume', 'lampada', 'chiave', 'pietra', 'nuvola', 'bosco', 'porta', 'finestra', 'giardino', 'collina',
                            'barca', 'sentiero', 'cesto', 'fonte', 'torre', 'piazza', 'mulino', 'vigna', 'olivo', 'cortile', 'terrazza', 'camino', 'sedia'];

    /** 12 caratteri leggibili: due parole e quattro cifre («tavolo-ruota-7392»), facili da dettare al telefono. */
    public static function generaProvvisoria(): string
    {
        return self::PAROLE[random_int(0, count(self::PAROLE) - 1)] . '-' . self::PAROLE[random_int(0, count(self::PAROLE) - 1)] . '-' . random_int(1000, 9999);
    }

    /**
     * La password provvisoria: generata dal sistema, mostrata una sola volta all'amministratore, valida 48 ore, da cambiare
     * al primo accesso. Nel database solo l'hash; nel registro solo «password provvisoria impostata».
     * @return string la password, da comunicare
     */
    public static function passwordProvvisoria(int $accountId): string
    {
        $u = self::utente($accountId);
        if (!Migrator::columnExists('users', 'must_change_password')) throw new \RuntimeException('Manca la migrazione 045: apri il sito una volta per aggiornare il database.');
        $pw = self::generaProvvisoria();
        Db::update('users', ['password_hash' => password_hash($pw, PASSWORD_DEFAULT), 'password_temp_until' => gmdate('Y-m-d\TH:i:s\Z', time() + 48 * 3600), 'must_change_password' => 1], 'id = :uid', ['uid' => (int) $u['id']]);
        Auth::audit('admin.password_temp', (int) $u['id']);
        self::email($u, 'Una password provvisoria per il tuo account', [
            self::nome($u) . ', un amministratore ha impostato una password provvisoria per il tuo account MyHouse Welcome, su tua richiesta. Vale 48 ore: al primo accesso ti chiediamo di sceglierne una nuova.',
            'Se non sei stato tu a chiederla, scrivici subito' . (Support::contatto() !== '' ? ' a ' . Support::contatto() : '') . '.'], ['Accedi', Support::baseUrl() . '/accedi']);
        return $pw;
    }
}
