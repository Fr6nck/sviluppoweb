<?php
namespace MHW;

/**
 * Le email che riportano l'host a finire la guida, e quella prima del rinnovo.
 *
 *   arrivo   — dal 1° al 3° giorno dopo la creazione, se «Arrivo» è ancora vuoto
 *   sezioni  — dal 3° al 7° giorno, se non c'è nessuna sezione aggiuntiva
 *   pubblica — dal 7° al 21° giorno, se la guida non è pubblicata
 *   rinnovo  — 30 giorni prima del rinnovo automatico (Stripe), con le statistiche dell'anno
 *   eventi   — (6G) ci sono eventi passati nella guida pubblicata: al massimo una al mese per struttura
 *   conferma — (7A) email non confermata: dopo 24 ore e, l'ultima volta, dopo 72, con un link nuovo
 *   pagamento — (7A) pagamento iniziato e annullato, e dopo 2 ore ancora nessun abbonamento
 *   parere   — (7A) 14 giorni dopo la registrazione, mai pubblicato: «Cosa ti ha fermato?»
 *
 * Le finestre non si sovrappongono: una struttura riceve al massimo un richiamo
 * per volta, e chi aggiorna l'applicazione con bozze vecchie non riceve una
 * raffica di email. Ognuna parte una volta sola (email_log), mai alle strutture
 * bloccate o di esempio, mai a chi ha chiesto di non riceverne di quel tipo.
 *
 * Nessun cron obbligatorio: forse() gira dopo le pagine del pannello e della
 * landing, al massimo ogni 15 minuti (un file di blocco in storage/). In più
 * /cron/{token} per chi preferisce un cron di cPanel.
 */
final class Richiami
{
    public const TIPI = ['arrivo' => 'il promemoria sul check-in', 'sezioni' => 'il promemoria sulle sezioni',
                         'pubblica' => 'il promemoria sulla pubblicazione', 'rinnovo' => 'l\'avviso prima del rinnovo',
                         'eventi' => 'il promemoria sugli eventi passati', 'inviti' => 'il promemoria sugli inviti',
                         'scadenza' => 'l\'avviso prima della scadenza',
                         'conferma' => 'il promemoria sulla conferma dell\'email', 'pagamento' => 'il promemoria sul pagamento',
                         'parere' => 'la domanda su cosa ti ha fermato'];   // con l'articolo: «Non vuoi più ricevere …?»
    /** Le risposte a un clic di «Cosa ti ha fermato?» (7A). */
    public const MOTIVI = ['prezzo' => 'Il prezzo', 'tempo' => 'Non ho avuto tempo', 'funzione' => 'Mi mancava una funzione', 'altro' => 'Altro'];
    /** I richiami del percorso: uno per volta, mai due a meno di due giorni l'uno dall'altro (7A). */
    private const PERCORSO = ['arrivo', 'sezioni', 'pubblica', 'conferma', 'pagamento', 'parere'];
    private const OGNI = 900;   // secondi tra un controllo e l'altro

    public static function disponibili(): bool { return Migrator::tableExists('email_log'); }

    private static function blocco(): string { return MHW_APP . '/storage/richiami.lock'; }

    /** Il controllo leggero: al massimo ogni 15 minuti, e mai due insieme. */
    public static function forse(): void
    {
        $f = self::blocco();
        if (is_file($f) && time() - (int) @filemtime($f) < self::OGNI) return;
        $h = @fopen($f, 'c');
        if (!$h || !flock($h, LOCK_EX | LOCK_NB)) return;
        try {
            touch($f);
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();   // l'utente ha già la sua pagina
            self::esegui();
        } catch (\Throwable $e) { Log::exception($e, 'Richiami'); }
        finally { flock($h, LOCK_UN); fclose($h); }
    }

    /** @return array<string,int> quante email per tipo */
    public static function esegui(?int $adesso = null): array
    {
        if (!self::disponibili()) return [];
        $t = $adesso ?? time();
        $iso = fn(int $s) => gmdate('Y-m-d\TH:i:s\Z', $s);
        $giorno = 86400;
        $fatte = array_fill_keys(array_keys(self::TIPI), 0);
        $fuori = '%@' . Demo::DOMINIO;

        // Conferma (7A): chi non ha confermato l'email dopo 24 ore, e l'ultima volta dopo 72. Fino a 7 giorni:
        // chi aggiorna l'applicazione non scrive agli iscritti di mesi fa. Il link è nuovo (annulla il vecchio).
        foreach (Db::all("SELECT u.id AS uid, u.email, u.name AS user_name, u.created_at, a.id AS acc_id FROM users u JOIN accounts a ON a.user_id = u.id
                          WHERE u.role = 'host' AND u.email_verified_at IS NULL AND u.created_at <= ? AND u.created_at > ? AND u.email NOT LIKE ?",
                         [$iso($t - $giorno), $iso($t - 7 * $giorno), $fuori]) as $u) {
            $ref = ($t - (int) strtotime((string) $u['created_at'])) >= 3 * $giorno ? '72' : '24';
            if (!self::puo((int) $u['acc_id'], 'conferma', $ref, $t)) continue;
            $link = Support::baseUrl() . '/verifica/' . Tokens::issue((int) $u['uid'], Tokens::VERIFY, 48 * 3600);
            $m = ['Confermi la tua email?',
                  ($ref === '72' ? 'è l\'ultima volta che te lo chiediamo: ' : '') . 'il tuo account MyHouse Welcome aspetta solo la conferma dell\'indirizzo. Un clic sul bottone e prepari la tua guida, come la vedranno i tuoi ospiti.',
                  'Conferma la mia email', $link, 'Il link vale 48 ore. Se non hai creato tu l\'account, ignora questo messaggio.'];
            if (self::manda((int) $u['acc_id'], $u['email'], (string) $u['user_name'], 'conferma', $ref, $m)) $fatte['conferma']++;
        }

        // Pagamento (7A): un checkout aperto e annullato (o lasciato a metà) da almeno 2 ore, entro 3 giorni,
        // e ancora nessun abbonamento. Una volta sola per account.
        $nuovo = Migrator::columnExists('orders', 'kind') ? " AND o.kind = 'new'" : '';
        foreach (Db::all("SELECT o.*, u.email, u.name AS user_name FROM orders o JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id
                          WHERE o.status IN ('pending', 'canceled')$nuovo AND o.created_at <= ? AND o.created_at > ? AND u.email NOT LIKE ?
                          ORDER BY o.id DESC", [$iso($t - 7200), $iso($t - 3 * $giorno), $fuori]) as $o) {
            $acc = (int) $o['account_id'];
            if (!self::puo($acc, 'pagamento', 'primo', $t) || Subscriptions::active($acc)
                || Db::val("SELECT id FROM orders WHERE account_id = ? AND status = 'paid' AND id > ?", [$acc, $o['id']])) continue;
            if (self::manda($acc, $o['email'], (string) $o['user_name'], 'pagamento', 'primo', self::testoPagamento($o))) $fatte['pagamento']++;
        }

        $bozze = Db::all("SELECT p.*, a.id AS acc_id, u.email, u.name AS user_name, u.email_verified_at FROM properties p
                          JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                          WHERE p.is_demo = 0 AND p.archived_at IS NULL AND p.status <> 'published'
                            AND p.created_at <= ? AND p.created_at > ? AND u.email NOT LIKE ?",
                         [$iso($t - $giorno), $iso($t - 21 * $giorno), '%@' . Demo::DOMINIO]);
        foreach ($bozze as $p) {
            if (in_array((int) $p['id'], Entitlements::lockedIds((int) $p['acc_id']), true)) continue;
            if (empty($p['email_verified_at']) || self::recente((int) $p['acc_id'], $t, ['conferma', 'pagamento', 'parere'])) continue;   // c'è già il richiamo giusto
            $eta = ($t - (int) strtotime((string) $p['created_at'])) / $giorno;
            $tipo = null;
            if ($eta < 3) { if (self::arrivoVuoto($p)) $tipo = 'arrivo'; }
            elseif ($eta < 7) { if (!(int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0', [$p['id']], 0)) $tipo = 'sezioni'; }
            else $tipo = 'pubblica';
            if ($tipo && self::manda((int) $p['acc_id'], $p['email'], (string) $p['user_name'], $tipo, (string) $p['id'], self::testo($tipo, $p))) $fatte[$tipo]++;
        }

        // Rinnovo: abbonamenti Stripe col rinnovo automatico, tra 29 e 31 giorni.
        foreach (Db::all("SELECT s.*, u.email, u.name AS user_name FROM subscriptions s JOIN accounts a ON a.id = s.account_id
                          JOIN users u ON u.id = a.user_id
                          WHERE s.status IN ('active', 'trialing') AND s.provider = 'stripe' AND s.cancel_at_period_end = 0
                            AND s.current_period_end > ? AND s.current_period_end <= ? AND u.email NOT LIKE ?",
                         [$iso($t + 29 * $giorno), $iso($t + 31 * $giorno), '%@' . Demo::DOMINIO]) as $s) {
            $ref = $s['id'] . '-' . substr((string) $s['current_period_end'], 0, 10);
            if (self::manda((int) $s['account_id'], $s['email'], (string) $s['user_name'], 'rinnovo', $ref, self::testoRinnovo($s))) $fatte['rinnovo']++;
        }

        // Scadenza: gli abbonamenti che non si rinnovano da soli (rinnovo disattivato, o attivati dallo
        // staff) avvisano 30 e 7 giorni prima; il giorno dopo la fine, se non è arrivato un abbonamento
        // nuovo, un'ultima email dice che la guida è offline. Gli abbonamenti di esempio non scrivono.
        $nonRinnova = "(s.provider <> 'stripe' OR s.cancel_at_period_end = 1 OR s.status = 'canceled') AND s.provider <> 'dimostrazione'";
        foreach ([30, 7] as $prima) {
            foreach (Db::all("SELECT s.*, u.email, u.name AS user_name FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                              WHERE s.status IN ('active', 'trialing') AND $nonRinnova AND s.current_period_end > ? AND s.current_period_end <= ? AND u.email NOT LIKE ?",
                             [$iso($t + ($prima - 1) * $giorno), $iso($t + ($prima + 1) * $giorno), '%@' . Demo::DOMINIO]) as $s) {
                if (self::continua($s)) continue;
                $ref = $s['id'] . '-' . substr((string) $s['current_period_end'], 0, 10) . '-' . $prima;
                if (self::manda((int) $s['account_id'], $s['email'], (string) $s['user_name'], 'scadenza', $ref, self::testoScadenza($s, false))) $fatte['scadenza']++;
            }
        }
        foreach (Db::all("SELECT s.*, u.email, u.name AS user_name FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                          WHERE $nonRinnova AND s.current_period_end <= ? AND s.current_period_end > ? AND u.email NOT LIKE ?",
                         [$iso($t - $giorno), $iso($t - 4 * $giorno), '%@' . Demo::DOMINIO]) as $s) {
            if (self::continua($s) || Subscriptions::active((int) $s['account_id'])) continue;
            $ref = $s['id'] . '-' . substr((string) $s['current_period_end'], 0, 10) . '-offline';
            if (self::manda((int) $s['account_id'], $s['email'], (string) $s['user_name'], 'scadenza', $ref, self::testoScadenza($s, true))) $fatte['scadenza']++;
        }

        // Eventi passati (6G): nella guida non si vedono più; un clic li ripete l'anno dopo.
        $oggi = Eventi::oggi($t);
        $def = SectionCatalog::field('events', 'events');
        foreach (Db::all("SELECT s.id AS sid, s.data, p.id, p.name, p.default_locale, a.id AS acc_id, u.email, u.name AS user_name
                          FROM sections s JOIN properties p ON p.id = s.property_id JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                          WHERE s.kind = 'events' AND s.is_active = 1 AND p.is_demo = 0 AND p.archived_at IS NULL AND p.status = 'published' AND u.email NOT LIKE ?",
                         ['%@' . Demo::DOMINIO]) as $e) {
            $testi = json_decode((string) Db::val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$e['sid'], $e['default_locale']], ''), true) ?: [];
            $righe = SectionCatalog::rows($def, (json_decode((string) $e['data'], true) ?: [])['events'] ?? [], $testi['events'] ?? []);
            $passati = Eventi::passati($righe, $oggi);
            if (!$passati) continue;
            $nomi = array_values(array_filter(array_map(fn($r) => trim((string) ($r['name'] ?? '')), $passati)));
            $n = count($passati);
            $m = [$n === 1 ? 'Un evento è passato' : "$n eventi sono passati",
                  ($nomi ? implode(', ', $nomi) . '. ' : '') . 'Non si vedono più nella guida di ' . $e['name'] . ". Se tornano l'anno prossimo, basta un clic.",
                  'Apri gli eventi', Support::baseUrl() . '/pannello/' . (int) $e['id'] . '/sezioni/' . (int) $e['sid']];
            if (self::manda((int) $e['acc_id'], $e['email'], (string) $e['user_name'], 'eventi', $e['id'] . '-' . substr($oggi, 0, 7), $m)) $fatte['eventi']++;
        }
        // Parere (7A): dal 14° al 30° giorno dopo la registrazione, mai pubblicato né abbonato, nessuna bozza
        // che aspetti ancora «pubblica» (meno di 7 giorni) e nessun altro richiamo del percorso negli ultimi 2 giorni.
        foreach (Db::all("SELECT a.id AS acc_id, u.email, u.name AS user_name FROM users u JOIN accounts a ON a.user_id = u.id
                          WHERE u.role = 'host' AND u.email_verified_at IS NOT NULL AND u.created_at <= ? AND u.created_at > ? AND u.email NOT LIKE ?",
                         [$iso($t - 14 * $giorno), $iso($t - 30 * $giorno), $fuori]) as $u) {
            $acc = (int) $u['acc_id'];
            if (!self::puo($acc, 'parere', 'primo', $t)
                || Db::val("SELECT id FROM properties WHERE account_id = ? AND (published_at <> '' OR status = 'published')", [$acc])
                || Db::val('SELECT id FROM subscriptions WHERE account_id = ?', [$acc])
                || Db::val("SELECT id FROM properties WHERE account_id = ? AND archived_at IS NULL AND created_at > ?", [$acc, $iso($t - 7 * $giorno)])) continue;
            $base = Support::baseUrl() . '/parere/{token}/';
            $m = ['Cosa ti ha fermato?',
                  'ti sei iscritto a MyHouse Welcome due settimane fa, ma la guida non è ancora online. Ci aiuti a capire perché? Basta un clic, non serve accedere.',
                  null, null, null, array_map(fn($k, $v) => [$v, $base . $k], array_keys(self::MOTIVI), self::MOTIVI)];
            if (self::manda($acc, $u['email'], (string) $u['user_name'], 'parere', 'primo', $m)) $fatte['parere']++;
        }

        Inviti::inSospeso();   // gli sconti inviti che Stripe non ha ancora preso
        try { CambioAbbonamento::applyPendingChanges(); } catch (\Throwable $e) { Log::exception($e, 'cambi di piano in sospeso'); }
        return $fatte;
    }

    /** Si può mandare? Non c'è il rifiuto, non è già partita e, per il percorso, nessun altro richiamo negli ultimi 2 giorni. */
    private static function puo(int $accountId, string $tipo, string $ref, int $t): bool
    {
        if (Db::one('SELECT id FROM email_optout WHERE account_id = ? AND kind = ?', [$accountId, $tipo])) return false;
        if (Db::one('SELECT id FROM email_log WHERE account_id = ? AND kind = ? AND ref = ?', [$accountId, $tipo, $ref])) return false;
        return !self::recente($accountId, $t, self::PERCORSO);
    }

    /** Uno di questi richiami è partito negli ultimi 2 giorni? */
    private static function recente(int $accountId, int $t, array $tipi): bool
    {
        $segni = implode(',', array_fill(0, count($tipi), '?'));
        return (bool) Db::val("SELECT id FROM email_log WHERE account_id = ? AND kind IN ($segni) AND sent_at > ?",
                              array_merge([$accountId], $tipi, [gmdate('Y-m-d\TH:i:s\Z', $t - 2 * 86400)]));
    }

    private static function testoPagamento(array $o): array
    {
        $pv = Plans::version((int) $o['package_version_id']) ?? [];
        $p = !empty($o['property_id']) ? Db::one('SELECT id, name FROM properties WHERE id = ?', [$o['property_id']]) : null;
        $prezzo = Support::money((int) $o['amount_cents'], (string) ($o['currency'] ?: 'EUR')) . ' + IVA / anno';
        return ['La tua guida è pronta per andare online',
                ($p ? 'la guida di ' . $p['name'] : 'la tua guida') . ' è pronta: manca solo il pagamento, e non ti è stato addebitato niente. '
                . 'Hai scelto ' . ($pv['name'] ?? 'il tuo piano') . ": $prezzo. Riprendi da dove eri rimasto: un minuto e gli ospiti la aprono dal QR.",
                'Riprendi e pubblica', Support::baseUrl() . ($p ? '/pannello/' . (int) $p['id'] . '/procedura/pubblica' : '/piano')];
    }

    private static function arrivoVuoto(array $p): bool
    {
        $core = Db::one('SELECT id FROM sections WHERE property_id = ? AND is_core = 1', [$p['id']]);
        if (!$core) return true;
        $d = json_decode((string) Db::val('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [$core['id'], $p['default_locale']], ''), true) ?: [];
        return !array_filter((array) ($d['checkin_steps'] ?? []), fn($x) => trim((string) $x) !== '') && trim((string) ($d['checkin_note'] ?? '')) === '';
    }

    /** @return array{0:string,1:string,2:string,3:string} [oggetto, testo, bottone, indirizzo] */
    private static function testo(string $tipo, array $p): array
    {
        $nome = $p['name']; $base = Support::baseUrl() . '/pannello/' . (int) $p['id'] . '/procedura/';
        return match ($tipo) {
            'arrivo' => ["Come si entra a $nome?",
                         "hai cominciato la guida di $nome. Manca la parte che gli ospiti cercano per prima: come si entra, a che ora, dove sono le chiavi. Bastano pochi minuti.",
                         'Compila Check-in & Check-out', $base . 'arrivo'],
            'sezioni' => ["Wi-Fi, regole, consigli: aggiungili a $nome",
                          "la guida di $nome ha già Check-in & Check-out, ma ancora nessun'altra sezione. Wi-Fi, regole della casa, dove mangiare: scegli quelle che servono, si compilano in pochi minuti.",
                          'Scegli le sezioni', $base . 'sezioni'],
            default => ["$nome è quasi pronta",
                        "la guida di $nome non è ancora online. Guarda l'anteprima come la vedranno gli ospiti e, quando ti convince, pubblicala"
                        . (Subscriptions::active((int) $p['account_id']) ? ': è già compresa nel tuo abbonamento.' : ': paghi solo quando pubblichi.'),
                        "Apri l'anteprima e pubblica", $base . 'pubblica'],
        };
    }

    private static function testoRinnovo(array $s): array
    {
        $da = gmdate('Y-m-d', strtotime('-1 year'));
        $conta = fn(string $k) => (int) Db::val("SELECT COUNT(*) FROM analytics_events e JOIN properties p ON p.id = e.property_id
                                                 WHERE p.account_id = ? AND e.day >= ? AND e.kind IN ($k)", [$s['account_id'], $da], 0);
        $aperture = $conta("'guide_view','open'"); $qr = $conta("'qr_open','qr'");
        $guide = (int) Db::val("SELECT COUNT(*) FROM properties WHERE account_id = ? AND status = 'published' AND archived_at IS NULL", [$s['account_id']], 0);
        $quando = Support::date((string) $s['current_period_end']);
        $numeri = $aperture > 0
            ? "In quest'anno " . ($guide === 1 ? 'la tua guida è stata aperta' : "le tue $guide guide sono state aperte") . " $aperture volte" . ($qr > 0 ? ", $qr delle quali dal QR" : '') . '.'
            : "Quest'anno non abbiamo ancora registrato aperture: controlla che il QR sia in vista e che il link arrivi agli ospiti prima dell'arrivo.";
        // Invita un amico: lo sconto già guadagnato, oppure come abbassare il rinnovo finché c'è tempo.
        if (Inviti::disponibili()) {
            $inv = Inviti::stato(['id' => (int) $s['account_id']]);
            $numeri .= $inv['percento'] > 0
                ? ' Grazie ai tuoi inviti hai il ' . $inv['percento'] . '% di sconto: paghi ' . Support::money($inv['scontato'], $inv['valuta']) . ' + IVA invece di ' . Support::money($inv['prezzo'], $inv['valuta']) . '.'
                : ' Puoi ancora abbassarlo: ogni amico che pubblica con il tuo invito vale il ' . Inviti::PASSO . '% in meno.';
        }
        // Una discesa programmata (6H): il rinnovo incasserà il prezzo del piano nuovo.
        if (!empty($s['next_package_version_id']) && ($nuovo = Plans::version((int) $s['next_package_version_id']))) {
            $q = max(1, (int) ($s['next_quantity'] ?? 1));
            $numeri .= ' Dal rinnovo passi a ' . $nuovo['name'] . (Plans::perProperty($nuovo) ? " ($q strutture)" : '') . ': '
                     . Support::money(Plans::price($nuovo, $q), (string) $nuovo['currency']) . ' + IVA.';
        }
        return ["Il tuo abbonamento si rinnova il $quando",
                "il tuo abbonamento MyHouse Welcome si rinnova da solo il $quando. $numeri Se vuoi cambiare qualcosa, o disattivare il rinnovo, lo fai dal tuo account.",
                'Vai al tuo account', Support::baseUrl() . '/account'];
    }

    /** Dopo questo abbonamento ne è già arrivato un altro, che va oltre: niente avviso di scadenza. */
    private static function continua(array $s): bool
    {
        return (bool) Db::val('SELECT id FROM subscriptions WHERE account_id = ? AND id > ? AND current_period_end > ?',
                              [$s['account_id'], $s['id'], $s['current_period_end']]);
    }

    /** L'avviso prima della scadenza (o, a scadenza passata, che la guida è offline). */
    private static function testoScadenza(array $s, bool $finito): array
    {
        $quando = Support::date((string) $s['current_period_end']);
        $piano = (Plans::version((int) $s['package_version_id']) ?? [])['name'] ?? '';
        $abb = 'il tuo abbonamento MyHouse Welcome' . ($piano !== '' ? ' ' . $piano : '');
        if ($finito) {
            return ['La tua guida è offline',
                    "$abb è finito il $quando: la guida non si apre più, nemmeno dal QR. Testi, foto e QR sono salvati: appena rinnovi, la guida torna online con lo stesso QR, senza ristampare niente.",
                    'Rinnova e torna online', Support::baseUrl() . '/piano'];
        }
        $stripe = $s['provider'] === 'stripe';
        $contatto = (string) ((Config::get('legal') ?? [])['contact_email'] ?? '');
        return ["La tua guida va offline il $quando",
                "$abb finisce il $quando e non si rinnova da solo. Da quel giorno la guida non si apre più, nemmeno dal QR stampato. Niente si cancella. "
                . ($stripe ? 'Per restare online riattiva il rinnovo automatico dal tuo account: bastano due clic.'
                           : 'Per restare online scrivici' . ($contatto !== '' ? " a $contatto" : '') . ': ti diciamo come rinnovare.'),
                $stripe ? 'Riattiva il rinnovo' : 'Vai al tuo account', Support::baseUrl() . '/account'];
    }

    /**
     * Il promemoria mandato a mano dall'amministrazione: l'avviso di rinnovo se l'abbonamento si
     * rinnova da solo, altrimenti quello di scadenza (o «sei offline», se è già finito).
     * @return array{0:bool,1:string} [partito, messaggio per l'amministratore]
     */
    public static function promemoriaManuale(int $subId): array
    {
        if (!self::disponibili()) return [false, 'Manca la tabella dei promemoria: apri il sito una volta per aggiornare il database.'];
        $s = Db::one('SELECT s.*, u.email, u.name AS user_name FROM subscriptions s JOIN accounts a ON a.id = s.account_id
                      JOIN users u ON u.id = a.user_id WHERE s.id = ?', [$subId]);
        if (!$s) return [false, 'Abbonamento non trovato.'];
        $finito = (string) $s['current_period_end'] !== '' && strtotime((string) $s['current_period_end']) <= time();
        $automatico = !$finito && $s['provider'] === 'stripe' && (int) $s['cancel_at_period_end'] === 0 && in_array($s['status'], ['active', 'trialing'], true);
        $tipo = $automatico ? 'rinnovo' : 'scadenza';
        if (Db::one('SELECT id FROM email_optout WHERE account_id = ? AND kind = ?', [$s['account_id'], $tipo])) {
            return [false, $s['email'] . ' ha chiesto di non ricevere ' . self::TIPI[$tipo] . ': se serve, scrivigli tu.'];
        }
        $ok = self::manda((int) $s['account_id'], (string) $s['email'], (string) $s['user_name'], $tipo,
                          'manuale-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)), $automatico ? self::testoRinnovo($s) : self::testoScadenza($s, $finito));
        return [$ok, $ok ? 'Promemoria mandato a ' . $s['email'] . '.' : 'L\'email a ' . $s['email'] . ' non è partita: controlla la posta in Impostazioni.'];
    }

    /** Manda una volta sola: prima si scrive il registro (indice unico), poi l'email. */
    public static function manda(int $accountId, string $email, string $nome, string $tipo, string $ref, array $m): bool
    {
        if (Db::one('SELECT id FROM email_optout WHERE account_id = ? AND kind = ?', [$accountId, $tipo])) return false;
        if (Db::one('SELECT id FROM email_log WHERE account_id = ? AND kind = ? AND ref = ?', [$accountId, $tipo, $ref])) return false;
        $token = bin2hex(random_bytes(24));
        try { Db::insert('email_log', ['account_id' => $accountId, 'kind' => $tipo, 'ref' => $ref, 'token' => $token, 'sent_at' => Support::now()]); }
        catch (\Throwable) { return false; }   // un altro processo l'ha appena mandata
        // [oggetto, testo, bottone, indirizzo, nota?, scelte?]: «{token}» negli indirizzi diventa il token di questa email.
        [$oggetto, $corpo, $bottone, $url] = $m;
        $nota = $m[4] ?? null;
        $scelte = array_map(fn($x) => [$x[0], str_replace('{token}', $token, $x[1])], $m[5] ?? []);
        $tasto = $bottone !== null && $url !== null ? [$bottone, str_replace('{token}', $token, $url)] : null;
        $stop = Support::baseUrl() . '/email/stop/' . $token;
        $saluto = 'Ciao' . (trim($nome) !== '' ? ' ' . trim(explode(' ', trim($nome))[0]) : '') . ',';
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $paragrafi = [$saluto . ' ' . $corpo];
        $piedeHtml = 'Non vuoi più ricevere ' . $e(self::TIPI[$tipo]) . '? <a href="' . $e($stop) . '" style="color:#6a5b48">Non mandarmene più</a>';
        $testo = Mailer::testo($oggetto, $paragrafi, $tasto, $nota, 'Non vuoi più ricevere ' . self::TIPI[$tipo] . "? $stop", $scelte);
        $html = Mailer::layout($oggetto, $paragrafi, $tasto, $nota, $piedeHtml, $scelte);
        $ok = Mailer::send($email, $oggetto . ' — MyHouse Welcome', $testo, $html);
        if (!$ok) Db::run('DELETE FROM email_log WHERE token = ?', [$token]);   // si riproverà al prossimo giro
        return $ok;
    }

    /** Il link «non mandarmene più»: l'email di partenza dice account e tipo. */
    public static function daToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token) || !self::disponibili()) return null;
        return Db::one('SELECT account_id, kind FROM email_log WHERE token = ?', [$token]);
    }

    /**
     * La risposta a «Cosa ti ha fermato?»: una riga per account, l'ultima vince. Un clic che arriva meno di
     * 2 minuti dopo un altro non cambia il motivo (i filtri antivirus aprono tutti i link dell'email insieme);
     * il testo di «Raccontaci» si salva sempre.
     */
    public static function rispondi(int $accountId, string $motivo, ?string $testo = null, bool $clic = false): void
    {
        if (!isset(self::MOTIVI[$motivo]) || !Migrator::tableExists('feedback')) return;
        $c = Db::one('SELECT * FROM feedback WHERE account_id = ?', [$accountId]);
        $testo = $testo !== null ? mb_substr(trim($testo), 0, 2000) : null;
        if (!$c) {
            try { Db::insert('feedback', ['account_id' => $accountId, 'motivo' => $motivo, 'testo' => (string) $testo, 'created_at' => Support::now(), 'updated_at' => Support::now()]); }
            catch (\Throwable) { }   // due clic insieme: il primo basta
            return;
        }
        if ($clic && time() - (int) strtotime((string) ($c['updated_at'] ?: $c['created_at'])) < 120) return;
        Db::update('feedback', array_filter(['motivo' => $motivo, 'testo' => $testo, 'updated_at' => Support::now()], fn($v) => $v !== null), 'id = :id', ['id' => $c['id']]);
    }

    public static function smetti(int $accountId, string $tipo): void
    {
        if (!isset(self::TIPI[$tipo]) || Db::one('SELECT id FROM email_optout WHERE account_id = ? AND kind = ?', [$accountId, $tipo])) return;
        Db::insert('email_optout', ['account_id' => $accountId, 'kind' => $tipo, 'created_at' => Support::now()]);
    }
}
