<?php
namespace MHW;

/**
 * Le email che riportano l'host a finire la guida, e quella prima del rinnovo.
 *
 *   arrivo   — dal 1° al 3° giorno dopo la creazione, se «Arrivo» è ancora vuoto
 *   sezioni  — dal 3° al 7° giorno, se non c'è nessuna sezione aggiuntiva
 *   bozza7   — (9Q, era «pubblica») dal 7° al 21° giorno, se la guida non è pubblicata: la checklist e il consiglio (Bozze)
 *   bozza21  — (9Q) dal 21° al 35° giorno, ancora in bozza: «la finiamo noi», quindici minuti insieme, «Non ora»
 *   rinnovo  — 30 giorni prima del rinnovo automatico (Stripe), con le statistiche dell'anno
 *   eventi   — (6G) ci sono eventi passati nella guida pubblicata: al massimo una al mese per struttura
 *   conferma — (7A) email non confermata: dopo 24 ore e, l'ultima volta, dopo 72, con un link nuovo
 *   pagamento — (7A) pagamento iniziato e annullato, e dopo 2 ore ancora nessun abbonamento
 *   parere   — (7A) 14 giorni dopo la registrazione, mai pubblicato: «Cosa ti ha fermato?»
 *   giudizio — (7D) dal 30° al 40° giorno dopo la prima pubblicazione, una volta sola: «Ti sta piacendo?»
 *   benvenuto — la prima guida dell'account è online (Benvenuto): una volta sola, di servizio, senza «non mandarmene più»
 *   sconti   — (9P) 7 giorni dopo l'attivazione, una volta per account: «Fino al 55%», inviti e giudizio con i conti (EmailSconti)
 *   assistente — (10) Concierge AI: nei primi 7 giorni del mese il riepilogo del mese prima per ogni guida con domande
 *                (quante, le sezioni più chieste, quelle senza risposta); e l'avviso all'80% delle domande del mese
 *
 * Le finestre non si sovrappongono: una struttura riceve al massimo un richiamo
 * per volta, e chi aggiorna l'applicazione con bozze vecchie non riceve una
 * raffica di email. Ognuna parte una volta sola (email_log), mai alle strutture
 * bloccate o di esempio, mai ai clienti di prova (7F), mai a chi ha chiesto di non riceverne di quel tipo.
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
                         'parere' => 'la domanda su cosa ti ha fermato',
                         'giudizio' => 'la richiesta di un giudizio su MyHouse Welcome', 'benvenuto' => 'l\'email di benvenuto',
                         'sconti' => 'l\'email sugli sconti del rinnovo',
                         'bozza7' => 'le email sulla guida in bozza', 'bozza21' => 'le email sulla guida in bozza', 'bozza' => 'le email sulla guida in bozza',
                         'assistente' => 'le email sul Concierge AI'];   // con l'articolo: «Non vuoi più ricevere …?»
    /** Le risposte a un clic di «Cosa ti ha fermato?» (7A). */
    public const MOTIVI = ['prezzo' => 'Il prezzo', 'tempo' => 'Non ho avuto tempo', 'funzione' => 'Mi mancava una funzione', 'altro' => 'Altro'];
    /** I richiami del percorso: uno per volta, mai due a meno di due giorni l'uno dall'altro (7A). */
    private const PERCORSO = ['arrivo', 'sezioni', 'pubblica', 'bozza7', 'bozza21', 'conferma', 'pagamento', 'parere'];
    /** 9Q · I rifiuti che fermano un tipo: le due email sulla bozza si fermano insieme («bozza»), anche con il vecchio «pubblica». */
    private const RIFIUTI = ['bozza7' => ['bozza', 'bozza7', 'bozza21', 'pubblica'], 'bozza21' => ['bozza', 'bozza7', 'bozza21', 'pubblica']];
    private const OGNI = 900;   // secondi tra un controllo e l'altro

    public static function disponibili(): bool { return Migrator::tableExists('email_log'); }

    /** 9N · La lingua delle email dell'account che si sta scrivendo: quella del suo pannello (Pannello::linguaDi). */
    private static string $lin = 'it';

    /** 9N · Fissa (e restituisce) la lingua delle email per un account: da chiamare prima di comporre il testo. */
    public static function lingua(int $accountId): string
    {
        $u = Db::one('SELECT u.* FROM users u JOIN accounts a ON a.user_id = u.id WHERE a.id = ?', [$accountId]);
        return self::$lin = $u ? Pannello::linguaDi($u, $accountId) : 'it';
    }

    /** 9N · Una frase nella lingua fissata da lingua(), con i segnaposto. */
    private static function tt(string $it, array $sost = []): string { return Pannello::tIn(self::$lin, $it, $sost); }

    /** 9N · Una data nella lingua fissata: 26 settembre 2026 / 26 September 2026. */
    private static function giorno(string $iso): string { return self::$lin === 'en' && ($t = strtotime($iso)) ? gmdate('j F Y', $t) : Support::date($iso); }

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
        if (function_exists('set_time_limit')) @set_time_limit(300);   // il giro chiama Stripe per più clienti: non si ferma a metà (30 secondi di serie)
        $t = $adesso ?? time();
        $iso = fn(int $s) => gmdate('Y-m-d\TH:i:s\Z', $s);
        $giorno = 86400;
        $fatte = array_fill_keys(array_keys(self::TIPI), 0);
        $fuori = '%@' . Demo::DOMINIO;
        try { Offerte::aggiornaScadute(); } catch (\Throwable $e) { Log::exception($e, 'offerte scadute'); }   // 9N · 10 bis

        // Conferma (7A): chi non ha confermato l'email dopo 24 ore, e l'ultima volta dopo 72. Fino a 7 giorni:
        // chi aggiorna l'applicazione non scrive agli iscritti di mesi fa. Il link è nuovo (annulla il vecchio).
        foreach (Db::all("SELECT u.id AS uid, u.email, u.name AS user_name, u.created_at, a.id AS acc_id FROM users u JOIN accounts a ON a.user_id = u.id
                          WHERE u.role = 'host' AND u.email_verified_at IS NULL AND u.created_at <= ? AND u.created_at > ? AND u.email NOT LIKE ?",
                         [$iso($t - $giorno), $iso($t - 7 * $giorno), $fuori]) as $u) {
            $ref = ($t - (int) strtotime((string) $u['created_at'])) >= 3 * $giorno ? '72' : '24';
            if (!self::puo((int) $u['acc_id'], 'conferma', $ref, $t)) continue;
            $link = Support::baseUrl() . '/verifica/' . Tokens::issue((int) $u['uid'], Tokens::VERIFY, 48 * 3600);
            self::lingua((int) $u['acc_id']);
            $m = [self::tt('Confermi la tua email?'),
                  ($ref === '72' ? self::tt('è l\'ultima volta che te lo chiediamo:') . ' ' : '') . self::tt('il tuo account MyHouse Welcome aspetta solo la conferma dell\'indirizzo. Un clic sul bottone e prepari la tua guida, come la vedranno i tuoi ospiti.'),
                  self::tt('Conferma la mia email'), $link, self::tt('Il link vale 48 ore. Se non hai creato tu l\'account, ignora questo messaggio.')];
            if (self::manda((int) $u['acc_id'], $u['email'], (string) $u['user_name'], 'conferma', $ref, $m)) $fatte['conferma']++;
        }

        // Pagamento (7A): un checkout aperto e annullato (o lasciato a metà) da almeno 2 ore, entro 3 giorni,
        // e ancora nessun abbonamento. Una volta sola per account.
        $nuovo = Migrator::columnExists('orders', 'kind') ? " AND o.kind = 'new'" : '';
        if (Migrator::columnExists('orders', 'offerta_id')) $nuovo .= ' AND o.offerta_id IS NULL';   // 9N · 10 bis: l'offerta ha il suo link
        foreach (Db::all("SELECT o.*, u.email, u.name AS user_name FROM orders o JOIN accounts a ON a.id = o.account_id JOIN users u ON u.id = a.user_id
                          WHERE o.status IN ('pending', 'canceled')$nuovo AND o.created_at <= ? AND o.created_at > ? AND u.email NOT LIKE ?
                          ORDER BY o.id DESC", [$iso($t - 7200), $iso($t - 3 * $giorno), $fuori]) as $o) {
            $acc = (int) $o['account_id'];
            if (!self::puo($acc, 'pagamento', 'primo', $t) || Subscriptions::active($acc)
                || Db::val("SELECT id FROM orders WHERE account_id = ? AND status = 'paid' AND id > ?", [$acc, $o['id']])) continue;
            self::lingua($acc);
            if (self::manda($acc, $o['email'], (string) $o['user_name'], 'pagamento', 'primo', self::testoPagamento($o))) $fatte['pagamento']++;
        }

        $bozze = Db::all("SELECT p.*, a.id AS acc_id, u.email, u.name AS user_name, u.email_verified_at FROM properties p
                          JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                          WHERE p.is_demo = 0 AND p.archived_at IS NULL AND p.status <> 'published'
                            AND (p.published_at IS NULL OR p.published_at = '') AND p.created_at <= ? AND p.created_at > ? AND u.email NOT LIKE ?",
                         [$iso($t - $giorno), $iso($t - (Bozze::GIORNI['bozza21'] + Bozze::FINESTRA) * $giorno), '%@' . Demo::DOMINIO]);
        foreach ($bozze as $p) {
            if (in_array((int) $p['id'], Entitlements::lockedIds((int) $p['acc_id']), true)) continue;
            if (empty($p['email_verified_at']) || self::recente((int) $p['acc_id'], $t, ['conferma', 'pagamento', 'parere'])) continue;   // c'è già il richiamo giusto
            $eta = ($t - (int) strtotime((string) $p['created_at'])) / $giorno;
            $tipo = null;
            if ($eta < 3) { if (self::arrivoVuoto($p)) $tipo = 'arrivo'; }
            elseif ($eta < 7) { if (!(int) Db::val('SELECT COUNT(*) FROM sections WHERE property_id = ? AND is_core = 0', [$p['id']], 0)) $tipo = 'sezioni'; }
            else {
                // 9Q · Giorno 7 e giorno 21: mai se l'account ha già una guida online (Portfolio), mai nei 3 giorni dopo
                // un'altra email del sito; chi aveva già avuto il vecchio «pubblica» non riceve anche «bozza7».
                $tipo = $eta < Bozze::GIORNI['bozza21'] ? 'bozza7' : 'bozza21';
                if (Bozze::haGuidaOnline((int) $p['acc_id']) || self::recente((int) $p['acc_id'], $t, [], 3)
                    || ($tipo === 'bozza7' && Db::one("SELECT id FROM email_log WHERE account_id = ? AND kind = 'pubblica' AND ref = ?", [$p['acc_id'], (string) $p['id']]))) $tipo = null;
            }
            if (!$tipo) continue;
            self::lingua((int) $p['acc_id']);
            $m = str_starts_with($tipo, 'bozza') ? Bozze::testo($tipo, $p, self::$lin) : self::testo($tipo, $p);
            if (self::manda((int) $p['acc_id'], $p['email'], (string) $p['user_name'], $tipo, (string) $p['id'], $m)) $fatte[$tipo]++;
        }

        // Rinnovo (9D): abbonamenti Stripe col rinnovo automatico (anche in omaggio), a 30 e a 7 giorni dall'addebito.
        // Quello a 7 giorni è dovuto prima di un addebito e parte anche a chi ha disattivato gli avvisi (manda()).
        foreach ([30, 7] as $prima) {
            foreach (Db::all("SELECT s.*, u.email, u.name AS user_name FROM subscriptions s JOIN accounts a ON a.id = s.account_id
                              JOIN users u ON u.id = a.user_id
                              WHERE s.status IN ('active', 'trialing') AND s.provider = 'stripe' AND s.cancel_at_period_end = 0
                                AND s.current_period_end > ? AND s.current_period_end <= ? AND u.email NOT LIKE ?",
                             [$iso($t + ($prima - 1) * $giorno), $iso($t + ($prima + 1) * $giorno), '%@' . Demo::DOMINIO]) as $s) {
                $ref = $s['id'] . '-' . substr((string) $s['current_period_end'], 0, 10) . ($prima === 7 ? '-7' : '');
                self::lingua((int) $s['account_id']);
                if (self::manda((int) $s['account_id'], $s['email'], (string) $s['user_name'], 'rinnovo', $ref, self::testoRinnovo($s, $prima))) $fatte['rinnovo']++;
            }
        }

        // Scadenza: gli abbonamenti che non si rinnovano da soli (rinnovo disattivato, o attivati dallo
        // staff) avvisano 30 e 7 giorni prima; il giorno dopo la fine, se non è arrivato un abbonamento
        // nuovo, un'ultima email dice che la guida è offline. Gli abbonamenti di esempio non scrivono.
        $nonRinnova = "(s.provider <> 'stripe' OR s.cancel_at_period_end = 1 OR s.status = 'canceled') AND s.provider NOT IN ('dimostrazione', 'staff')";   // 9R: lo staff non paga
        foreach ([30, 7] as $prima) {
            foreach (Db::all("SELECT s.*, u.email, u.name AS user_name FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                              WHERE s.status IN ('active', 'trialing') AND $nonRinnova AND s.current_period_end > ? AND s.current_period_end <= ? AND u.email NOT LIKE ?",
                             [$iso($t + ($prima - 1) * $giorno), $iso($t + ($prima + 1) * $giorno), '%@' . Demo::DOMINIO]) as $s) {
                if (self::continua($s)) continue;
                $ref = $s['id'] . '-' . substr((string) $s['current_period_end'], 0, 10) . '-' . $prima;
                self::lingua((int) $s['account_id']);
                if (self::manda((int) $s['account_id'], $s['email'], (string) $s['user_name'], 'scadenza', $ref, self::testoScadenza($s, false))) $fatte['scadenza']++;
            }
        }
        foreach (Db::all("SELECT s.*, u.email, u.name AS user_name FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                          WHERE $nonRinnova AND s.current_period_end <= ? AND s.current_period_end > ? AND u.email NOT LIKE ?",
                         [$iso($t - $giorno), $iso($t - 4 * $giorno), '%@' . Demo::DOMINIO]) as $s) {
            if (self::continua($s) || Subscriptions::active((int) $s['account_id'])) continue;
            $ref = $s['id'] . '-' . substr((string) $s['current_period_end'], 0, 10) . '-offline';
            self::lingua((int) $s['account_id']);
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
            self::lingua((int) $e['acc_id']);
            $m = [$n === 1 ? self::tt('Un evento è passato') : self::tt('{n} eventi sono passati', ['{n}' => $n]),
                  ($nomi ? implode(', ', $nomi) . '. ' : '') . self::tt('Non si vedono più nella guida di {nome}. Se tornano l\'anno prossimo, basta un clic.', ['{nome}' => $e['name']]),
                  self::tt('Apri gli eventi'), Support::baseUrl() . '/pannello/' . (int) $e['id'] . '/sezioni/' . (int) $e['sid']];
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
            self::lingua($acc);
            $m = [self::tt('Cosa ti ha fermato?'),
                  self::tt('ti sei iscritto a MyHouse Welcome due settimane fa, ma la guida non è ancora online. Ci aiuti a capire perché? Basta un clic, non serve accedere.'),
                  null, null, null, array_map(fn($k, $v) => [self::tt($v), $base . $k], array_keys(self::MOTIVI), self::MOTIVI)];
            if (self::manda($acc, $u['email'], (string) $u['user_name'], 'parere', 'primo', $m)) $fatte['parere']++;
        }

        // Giudizio (7D): dal 30° al 40° giorno dopo la prima pubblicazione, una volta sola, se non l'ha già dato.
        // La finestra di 10 giorni: chi aggiorna l'applicazione non scrive a tutti i clienti di mesi fa.
        if (Testimonianze::giudizi()) {
            $prova = Migrator::columnExists('accounts', 'is_test') ? ' AND a.is_test = 0' : '';
            foreach (Db::all("SELECT a.id AS acc_id, u.email, u.name AS user_name, MIN(p.published_at) AS prima FROM accounts a JOIN users u ON u.id = a.user_id
                              JOIN properties p ON p.account_id = a.id WHERE p.is_demo = 0 AND p.published_at IS NOT NULL AND p.published_at <> '' AND u.email NOT LIKE ?$prova
                              GROUP BY a.id, u.email, u.name", [$fuori]) as $g) {
                $quando = (int) strtotime((string) $g['prima']);
                if ($t - $quando < Testimonianze::RICHIAMO_GIORNI * $giorno || $t - $quando > (Testimonianze::RICHIAMO_GIORNI + 10) * $giorno) continue;
                if (Testimonianze::diAccount((int) $g['acc_id'])) continue;
                // 9L · Chi ha aderito a «Giudizio con sconto»: il giudizio promesso, che si aggiunge alla sua scheda.
                $sch = Vetrina::scheda((int) $g['acc_id']);
                if ($sch && !empty($sch['ritirato_at'])) continue;
                // 9R · Chi ha aderito: «Il tuo 5% aspetta solo il tuo giudizio» (mai allo staff, che non paga).
                $aderito = (bool) Db::val("SELECT giudizio_adesione FROM accounts WHERE id = ?", [(int) $g['acc_id']]);
                if ($aderito && Staff::e((int) $g['acc_id'])) continue;
                self::lingua((int) $g['acc_id']);
                $m = $aderito ? self::testoPromemoriaGiudizio((int) $g['acc_id'], 'pubblicazione')
                          : [self::tt('Ti sta piacendo MyHouse Welcome?'),
                             self::tt('la tua guida è online da un mese. Ci dici com\'è andata? Bastano un voto e due righe: ci aiutano a migliorare.'),
                             self::tt('Dai il tuo giudizio'), Support::baseUrl() . '/giudizio?scrivi=1'];
                if (self::manda((int) $g['acc_id'], $g['email'], (string) $g['user_name'], 'giudizio', 'primo', $m)) $fatte['giudizio']++;
            }
        }

        // 9R · Il secondo promemoria del giudizio: 30 giorni prima del rinnovo, a chi ha aderito e non l'ha ancora lasciato.
        if (Testimonianze::giudizi() && Giudizi::disponibili()) {
            foreach (Db::all("SELECT s.*, u.email, u.name AS user_name FROM subscriptions s JOIN accounts a ON a.id = s.account_id JOIN users u ON u.id = a.user_id
                              WHERE s.status IN ('active', 'trialing') AND s.provider IN ('stripe', 'manuale') AND a.giudizio_adesione IS NOT NULL AND a.giudizio_adesione <> ''
                                AND a.giudizio_sconto_stato = 'da_applicare_rinnovo'
                                AND s.current_period_end > ? AND s.current_period_end <= ? AND u.email NOT LIKE ?" . Staff::senza('a'),
                             [$iso($t + 29 * $giorno), $iso($t + 31 * $giorno), $fuori]) as $s) {
                $acc = (int) $s['account_id'];
                if (Giudizi::conGiudizio($acc) || (($sch = Vetrina::scheda($acc)) && !empty($sch['ritirato_at']))) continue;
                self::lingua($acc);
                $ref = 'rinnovo-' . $s['id'] . '-' . substr((string) $s['current_period_end'], 0, 10);
                if (self::manda($acc, $s['email'], (string) $s['user_name'], 'giudizio', $ref, self::testoPromemoriaGiudizio($acc, 'rinnovo', $s))) $fatte['giudizio']++;
            }
        }

        // 9P · «Fino al 55%», 7 giorni dopo l'attivazione: prima dei lavori di manutenzione qui sotto (che chiamano Stripe e possono durare).
        try { $fatte['sconti'] = EmailSconti::giro($t); } catch (\Throwable $e) { Log::exception($e, 'email «Fino al 55%»'); }
        // 10 · Concierge AI: il riepilogo del mese prima, nei primi 7 giorni del mese, e la pulizia delle domande oltre i 12 mesi.
        try { $fatte['assistente'] = self::giroAssistente($t); } catch (\Throwable $e) { Log::exception($e, 'email mensile Concierge AI'); }
        try { Assistente::pulisci(); } catch (\Throwable $e) { Log::exception($e, 'pulizia domande assistente'); }

        Seo::forseFile();      // 7F: una volta al giorno robots.txt, sitemap.xml e llms.txt come file veri
        Registri::giornaliero();   // 8I: una volta al giorno pulizia dei registri e copia di quelli di ieri su S3
        try { Traduttore::pulisciCache(); } catch (\Throwable $e) { Log::exception($e, 'pulizia memoria traduzioni'); }   // 9G: righe mai riusate da 12 mesi
        try { Legale::avvisa(); } catch (\Throwable $e) { Log::exception($e, 'avviso dei documenti legali'); }   // 8J: una volta per versione
        Inviti::inSospeso();   // gli sconti inviti che Stripe non ha ancora preso
        try { CambioAbbonamento::applyPendingChanges(); } catch (\Throwable $e) { Log::exception($e, 'cambi di piano in sospeso'); }
        RecuperoPagamenti::inSospeso();   // i pagamenti di cui il webhook di Stripe non ha dato notizia
        try { Vetrina::sincronizza(); } catch (\Throwable $e) { Log::exception($e, 'vetrina: sincronizzazione'); }   // 9L: schede da spegnere
        try { Campione::pulisci(); } catch (\Throwable $e) { Log::exception($e, 'campione: storico'); }   // 9M: le copie sovrascritte oltre i 30 giorni
        try { AccountProva::pulisci(); } catch (\Throwable $e) { Log::exception($e, 'account di prova: registro'); }   // 9R: l'email tolta dopo 90 giorni
        try { Sconti::riallineaInSospeso(); } catch (\Throwable $e) { Log::exception($e, 'sconti da riallineare'); }   // 9R · migrazione 063
        // Benvenuto: le prime guide degli ultimi giorni rimaste senza email (il primo tentativo non è partito).
        try { $fatte['online'] = Pagamenti::recenti($t); } catch (\Throwable $e) { Log::exception($e, 'email «La tua guida è online»'); }   // 9O: quelle non partite
        try { $fatte['benvenuto'] = Benvenuto::recenti($t); } catch (\Throwable $e) { Log::exception($e, 'email di benvenuto'); }
        return $fatte;
    }

    /** Si può mandare? Non c'è il rifiuto, non è già partita e, per il percorso, nessun altro richiamo negli ultimi 2 giorni. */
    private static function puo(int $accountId, string $tipo, string $ref, int $t): bool
    {
        if (Db::one('SELECT id FROM email_optout WHERE account_id = ? AND kind = ?', [$accountId, $tipo])) return false;
        if (Db::one('SELECT id FROM email_log WHERE account_id = ? AND kind = ? AND ref = ?', [$accountId, $tipo, $ref])) return false;
        return !self::recente($accountId, $t, self::PERCORSO);
    }

    /** Uno di questi richiami è partito negli ultimi $giorni giorni (2 di serie)? Nessun tipo: una qualunque email del registro. */
    private static function recente(int $accountId, int $t, array $tipi, int $giorni = 2): bool
    {
        $segni = implode(',', array_fill(0, count($tipi), '?'));
        return (bool) Db::val('SELECT id FROM email_log WHERE account_id = ?' . ($tipi ? " AND kind IN ($segni)" : '') . ' AND sent_at > ?',
                              array_merge([$accountId], $tipi, [gmdate('Y-m-d\TH:i:s\Z', $t - $giorni * 86400)]));
    }

    private static function testoPagamento(array $o): array
    {
        $pv = Plans::version((int) $o['package_version_id']) ?? [];
        $p = !empty($o['property_id']) ? Db::one('SELECT id, name FROM properties WHERE id = ?', [$o['property_id']]) : null;
        $prezzo = self::tt('{prezzo} + IVA / anno', ['{prezzo}' => Support::money((int) $o['amount_cents'], (string) ($o['currency'] ?: 'EUR'))]);
        return [self::tt('La tua guida è pronta per andare online'),
                ($p ? self::tt('la guida di {nome} è pronta: manca solo il pagamento, e non ti è stato addebitato niente.', ['{nome}' => $p['name']]) : self::tt('la tua guida è pronta: manca solo il pagamento, e non ti è stato addebitato niente.'))
                . ' ' . self::tt('Hai scelto {piano}: {prezzo}. Riprendi da dove eri rimasto: un minuto e gli ospiti la aprono dal QR.', ['{piano}' => $pv['name'] ?? self::tt('il tuo piano'), '{prezzo}' => $prezzo]),
                self::tt('Riprendi e pubblica'), Support::baseUrl() . ($p ? '/pannello/' . (int) $p['id'] . '/procedura/pubblica' : '/piano')];
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
            'arrivo' => [self::tt('Come si entra a {nome}?', ['{nome}' => $nome]),
                         self::tt('hai cominciato la guida di {nome}. Manca la parte che gli ospiti cercano per prima: come si entra, a che ora, dove sono le chiavi. Bastano pochi minuti.', ['{nome}' => $nome]),
                         self::tt('Compila Check-in & Check-out'), $base . 'arrivo'],
            'sezioni' => [self::tt('Wi-Fi, regole, consigli: aggiungili a {nome}', ['{nome}' => $nome]),
                          self::tt('la guida di {nome} ha già Check-in & Check-out, ma ancora nessun\'altra sezione. Wi-Fi, regole della casa, dove mangiare: scegli quelle che servono, si compilano in pochi minuti.', ['{nome}' => $nome]),
                          self::tt('Scegli le sezioni'), $base . 'sezioni'],
            default => [self::tt('{nome} è quasi pronta', ['{nome}' => $nome]),
                        self::tt(Subscriptions::active((int) $p['account_id']) ? 'la guida di {nome} non è ancora online. Guarda l\'anteprima come la vedranno gli ospiti e, quando ti convince, pubblicala: è già compresa nel tuo abbonamento.'
                                                                              : 'la guida di {nome} non è ancora online. Guarda l\'anteprima come la vedranno gli ospiti e, quando ti convince, pubblicala: paghi solo quando pubblichi.', ['{nome}' => $nome]),
                        self::tt('Apri l\'anteprima e pubblica'), $base . 'pubblica'],
        };
    }

    /**
     * 9D · Quanto si pagherà al prossimo addebito: da Stripe (fattura imminente: sconti e IVA veri), altrimenti dal listino
     * con gli sconti noti (inviti, giudizio, piano programmato). @return array{netto:int,sconto:int,percento:int,piano:string,quantita:int,valuta:string,stripe:bool}
     */
    public static function prossimoAddebito(array $s): array
    {
        $pvId = (int) (!empty($s['next_package_version_id']) ? $s['next_package_version_id'] : $s['package_version_id']);
        $pv = Plans::version($pvId) ?? [];
        $q = max(1, (int) (!empty($s['next_package_version_id']) ? ($s['next_quantity'] ?? 1) : ($s['quantity'] ?? 1)));
        $listino = Offerte::prezzo($s) ?? ($pv ? Plans::price($pv, $q) : 0);   // 9N · 10 bis: il prezzo concordato
        [$netto, $percento] = Gestione::importoRinnovo($s);
        $sconto = max(0, $listino - $netto);
        $daStripe = false;
        if ($s['provider'] === 'stripe' && (string) ($s['provider_subscription_id'] ?? '') !== '' && Stripe::enabled()) {
            $f = Stripe::prossimaFattura((string) $s['provider_subscription_id']);
            if ($f && $f['netto'] > 0) { $netto = $f['netto']; $sconto = $f['sconto']; $daStripe = true; $percento = $listino > 0 ? (int) round($sconto * 100 / $listino) : 0; }
        }
        return ['netto' => $netto, 'sconto' => $sconto, 'percento' => $percento, 'piano' => (string) ($pv['name'] ?? ''), 'quantita' => $q,
                'valuta' => (string) ($pv['currency'] ?? 'EUR'), 'stripe' => $daStripe, 'listino' => $listino];
    }

    /** «sulla carta che termina con 4242», oppure «sul tuo metodo di pagamento». */
    private static function carta(array $s): string
    {
        $l4 = (string) ($s['card_last4'] ?? '');
        return $l4 !== '' ? self::tt('sulla carta che termina con {n}', ['{n}' => $l4]) : self::tt('sul tuo metodo di pagamento');
    }

    /** 9D · L'avviso prima dell'addebito, a 30 o a 7 giorni: rinnovo automatico, oppure fine dei mesi gratis di un omaggio. */
    private static function testoRinnovo(array $s, int $prima = 30): array
    {
        $quando = self::giorno((string) $s['current_period_end']);
        $a = self::prossimoAddebito($s);
        $importo = self::tt('{prezzo} + IVA', ['{prezzo}' => Support::money($a['netto'], $a['valuta'])]);
        $piano = $a['piano'] . ($a['quantita'] > 1 ? ' ' . self::tt('({n} strutture)', ['{n}' => $a['quantita']]) : '');
        $omaggio = !empty($s['omaggio_fino']) && $s['status'] === 'trialing';
        $x = ['{data}' => $quando, '{piano}' => $piano, '{importo}' => $importo, '{carta}' => self::carta($s)];
        $sconti = $a['sconto'] > 0 ? ' ' . self::tt('Hai uno sconto del {n}%: pagherai {importo} invece di {pieno} + IVA.', ['{n}' => $a['percento'], '{importo}' => $importo, '{pieno}' => Support::money($a['listino'], $a['valuta'])]) : '';
        if ($omaggio) {
            $corpo = self::tt('la tua prova gratuita finisce il {data}: da quel giorno parte l\'abbonamento {piano} a {importo} l\'anno, {carta}. Non devi fare nulla.', $x) . $sconti . ' '
                   . self::tt('Se non vuoi continuare, disattiva il rinnovo da Account & Fatturazione entro il {data}: fino a quel giorno la guida resta online e non ti addebitiamo nulla.', $x);
            $oggetto = $prima === 7 ? self::tt('Tra 7 giorni finisce la tua prova gratuita') : self::tt('La tua prova gratuita finisce il {data}', $x);
        } else {
            $corpo = self::tt('il tuo abbonamento {piano} si rinnova il {data}: addebiteremo {importo} {carta}. Non devi fare nulla.', $x) . $sconti . ' '
                   . self::tt('Se non vuoi rinnovare, disattiva il rinnovo da Account & Fatturazione entro il {data}: la guida resta online fino a quel giorno e non ti addebitiamo nulla.', $x);
            $oggetto = $prima === 7 ? self::tt('Tra 7 giorni rinnoviamo il tuo abbonamento') : self::tt('Il tuo abbonamento si rinnova il {data}', $x);
        }
        // Una discesa programmata (6H): l'avviso dice già il piano e l'importo nuovi (prossimoAddebito li usa); qui solo la frase.
        if (!empty($s['next_package_version_id'])) $corpo .= ' ' . self::tt('Come avevi chiesto, dal rinnovo passi a {piano}.', $x);
        // 9R · Ha aderito al giudizio ma non l'ha lasciato: senza, il 5% non si applica a questo rinnovo.
        // Solo per chi ha aderito dopo il pagamento (il 5% vale su questo rinnovo, una volta): chi l'ha avuto sul primo anno no.
        if (!$omaggio && Giudizi::disponibili() && (string) Db::val('SELECT giudizio_sconto_stato FROM accounts WHERE id = ?', [(int) $s['account_id']], '') === 'da_applicare_rinnovo'
            && !Giudizi::conGiudizio((int) $s['account_id']) && !(($sch = Vetrina::scheda((int) $s['account_id'])) && !empty($sch['ritirato_at']))) {
            $corpo .= ' ' . self::tt('Hai aderito a «Giudizio con sconto»: senza giudizio il {n}% non si applica, lascialo ora da {link}', ['{n}' => Giudizi::percentuale(), '{link}' => Support::baseUrl() . '/giudizio?scrivi=1']);
        }
        // A 30 giorni, per chi rinnova, restano le statistiche dell'anno (K5) e la spinta a invitare un amico.
        if ($prima === 30 && !$omaggio) $corpo .= ' ' . self::numeriDellAnno($s, $a);
        return [$oggetto, $corpo, self::tt('Vai ad Account & Fatturazione'), Support::baseUrl() . '/account'];
    }

    /**
     * 9R · Il promemoria del giudizio a chi ha aderito e non l'ha lasciato: dopo 30 giorni dalla pubblicazione ($quando =
     * «pubblicazione») o 30 giorni prima del rinnovo («rinnovo»). Con l'importo del 5% sul suo piano e la somma con gli amici.
     * @return array [oggetto, corpo, bottone, indirizzo, nota]
     */
    public static function testoPromemoriaGiudizio(int $accountId, string $quando, ?array $sub = null): array
    {
        $sub ??= Subscriptions::active($accountId);
        $gp = Giudizi::percentuale();
        // 9R · Chi ha aderito al pagamento il 5% l'ha già avuto sul primo anno (una volta sola): il promemoria chiede il voto e basta.
        $accP = Db::one('SELECT * FROM accounts WHERE id = ?', [$accountId]) ?: [];
        if (Giudizi::scontoUsato($accP)) {
            return [self::tt('Ci lasci il tuo giudizio?'),
                    self::tt('hai aderito a «Giudizio con sconto» al pagamento e il {n}% l\'hai già avuto sul primo anno: grazie! Come previsto dal programma, ci lasci un voto da 1 a 5 e un commento? Bastano due minuti.', ['{n}' => $gp]),
                    self::tt('Lascia il giudizio →'), Support::baseUrl() . '/giudizio?scrivi=1#scrivi',
                    self::tt('Il voto non cambia lo sconto che hai avuto. Se non vuoi più partecipare puoi ritirarti da Impostazioni → Giudizio con sconto.')];
        }
        $struttura = (string) Db::val("SELECT name FROM properties WHERE account_id = ? AND is_demo = 0 AND archived_at IS NULL ORDER BY (status = 'published') DESC, id", [$accountId], '');
        $pv = $sub ? Plans::version((int) $sub['package_version_id']) : null;
        $prezzo = $pv ? (Offerte::prezzo($sub) ?? Plans::price($pv, max(1, (int) ($sub['quantity'] ?? 1)))) : 0;
        $valuta = (string) ($pv['currency'] ?? 'EUR');
        $amici = Inviti::disponibili() ? Inviti::percento($accountId) : 0;
        $x = ['{n}' => $gp, '{struttura}' => $struttura, '{piano}' => (string) ($pv['name'] ?? ''), '{data}' => $sub ? self::giorno((string) $sub['current_period_end']) : ''];
        $corpo = $quando === 'rinnovo'
            ? self::tt('il tuo abbonamento si rinnova il {data} e hai aderito al programma «Giudizio con sconto». Per avere il {n}% su questo rinnovo manca solo il tuo voto da 1 a 5 e un commento: due minuti.', $x)
            : self::tt('un mese fa hai aderito al programma «Giudizio con sconto» per {struttura}. Per avere il {n}% sul prossimo rinnovo manca solo il tuo voto da 1 a 5 e un commento: due minuti.', $x);
        if ($prezzo > 0 && !Staff::e($accountId)) {
            $euro = Support::money($prezzo - Sconti::netto($prezzo, $gp), $valuta);
            $corpo .= "\n\n" . ($amici > 0
                ? self::tt('Sul rinnovo di {piano} vale {euro} in meno, che si sommano allo sconto degli amici (oggi sei al {amici}%: in tutto {tot}%, cioè {paga} invece di {prezzo}).', $x + ['{euro}' => $euro, '{amici}' => $amici, '{tot}' => min(100, $amici + $gp),
                          '{paga}' => Support::money(Sconti::netto($prezzo, min(100, $amici + $gp)), $valuta), '{prezzo}' => Support::money($prezzo, $valuta)])
                : self::tt('Sul rinnovo di {piano} vale {euro} in meno: paghi {paga} invece di {prezzo}.', $x + ['{euro}' => $euro, '{paga}' => Support::money(Sconti::netto($prezzo, $gp), $valuta), '{prezzo}' => Support::money($prezzo, $valuta)]));
        }
        return [$quando === 'rinnovo' ? self::tt('Al rinnovo manca il tuo giudizio: senza, il {n}% non si applica', $x) : self::tt('Il tuo {n}% aspetta solo il tuo giudizio.', $x),
                $corpo, self::tt('Lascia il giudizio →'), Support::baseUrl() . '/giudizio?scrivi=1#scrivi',
                self::tt('Senza giudizio al momento del rinnovo lo sconto non si applica. Se non vuoi più partecipare puoi ritirarti da Impostazioni → Giudizio con sconto.')];
    }

    /**
     * 9R · Per Amministrazione → Richiami: chi ha aderito al giudizio e non l'ha lasciato, con lo stato dei due promemoria.
     * @return list<array{account_id:int,cliente:string,email:string,adesione:string,stati:array<string,array{0:string,1:string}>}>
     */
    public static function statoPromemoriaGiudizio(): array
    {
        if (!self::disponibili() || !Giudizi::disponibili()) return [];
        $out = [];
        foreach (Db::all("SELECT a.id, a.giudizio_adesione_at, a.giudizio_sconto_stato, u.email, u.name FROM accounts a JOIN users u ON u.id = a.user_id
                          WHERE a.giudizio_adesione IS NOT NULL AND a.giudizio_adesione <> '' AND u.email NOT LIKE ?" . Staff::senza('a') . ' ORDER BY a.id DESC LIMIT 200', ['%@' . Demo::DOMINIO]) as $a) {
            $acc = (int) $a['id'];
            if (Giudizi::conGiudizio($acc) || (($sch = Vetrina::scheda($acc)) && !empty($sch['ritirato_at']))) continue;
            $prima = Testimonianze::primaPubblicazione($acc);
            $sub = Subscriptions::active($acc);
            $inviata = fn(string $like) => (string) Db::val("SELECT sent_at FROM email_log WHERE account_id = ? AND kind = 'giudizio' AND ref LIKE ? ORDER BY id DESC", [$acc, $like], '');
            $p1 = $inviata('primo'); $p2 = $sub ? $inviata('rinnovo-' . $sub['id'] . '-%') : '';
            $out[] = ['account_id' => $acc, 'cliente' => (string) $a['name'], 'email' => (string) $a['email'], 'adesione' => (string) $a['giudizio_adesione_at'], 'stati' => [
                'pubblicazione' => $p1 !== '' ? ['inviata', 'il ' . Support::date($p1)] : ($prima === null ? ['programmata', 'dopo la pubblicazione'] : ['programmata', 'dal ' . Support::date(gmdate('Y-m-d\TH:i:s\Z', $prima + Testimonianze::RICHIAMO_GIORNI * 86400))]),
                // 9R · Il promemoria prima del rinnovo solo per chi ha aderito dopo il pagamento (il 5% vale su quel rinnovo, una volta).
                'rinnovo' => $p2 !== '' ? ['inviata', 'il ' . Support::date($p2)] : ((string) $a['giudizio_sconto_stato'] !== 'da_applicare_rinnovo' ? ['fermata', 'aderito al pagamento: il 5% è sul primo anno']
                             : ($sub && (string) $sub['current_period_end'] !== '' ? ['programmata', 'dal ' . Support::date(gmdate('Y-m-d\TH:i:s\Z', (int) strtotime((string) $sub['current_period_end']) - 30 * 86400))] : ['fermata', 'nessun rinnovo'])),
            ]];
        }
        return $out;
    }

    /** 9R · «Manda adesso» il promemoria del giudizio (quello della pubblicazione o del rinnovo). @return array{0:bool,1:string} */
    public static function mandaPromemoriaGiudizio(int $accountId, string $quale): array
    {
        $a = Db::one('SELECT a.*, u.email, u.name AS user_name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$accountId]);
        if (!$a || empty($a['giudizio_adesione'])) return [false, 'Il cliente non ha aderito al programma.'];
        if (Giudizi::conGiudizio($accountId)) return [false, 'Il giudizio c\'è già: il promemoria non serve.'];
        if (Staff::e($a)) return [false, 'Non parte: account staff.'];
        if ($quale === 'rinnovo' && (string) $a['giudizio_sconto_stato'] !== 'da_applicare_rinnovo') return [false, 'Non parte: il 5% l\'ha già avuto sul primo anno (vale una volta sola).'];
        self::lingua($accountId);
        $sub = Subscriptions::active($accountId);
        $ref = $quale === 'rinnovo' && $sub ? 'rinnovo-' . $sub['id'] . '-' . substr((string) $sub['current_period_end'], 0, 10) : 'primo';
        if (Db::val("SELECT id FROM email_log WHERE account_id = ? AND kind = 'giudizio' AND ref = ?", [$accountId, $ref])) return [false, 'Già mandato.'];
        $ok = self::manda($accountId, (string) $a['email'], (string) $a['user_name'], 'giudizio', $ref, self::testoPromemoriaGiudizio($accountId, $quale === 'rinnovo' ? 'rinnovo' : 'pubblicazione', $sub));
        return [$ok, $ok ? 'Promemoria del giudizio mandato a ' . $a['email'] . '.' : 'L\'email a ' . $a['email'] . ' non è partita: controlla la posta in Impostazioni.'];
    }

    /** K5 · Le aperture dell'anno (e dal QR) con, se ci sono gli inviti, lo sconto già guadagnato o come abbassare il rinnovo. */
    private static function numeriDellAnno(array $s, array $a): string
    {
        $da = gmdate('Y-m-d', strtotime('-1 year'));
        $conta = fn(string $k) => (int) Db::val("SELECT COUNT(*) FROM analytics_events e JOIN properties p ON p.id = e.property_id
                                                 WHERE p.account_id = ? AND e.day >= ? AND e.kind IN ($k)", [$s['account_id'], $da], 0);
        $aperture = $conta("'guide_view','open'"); $qr = $conta("'qr_open','qr'");
        $guide = (int) Db::val("SELECT COUNT(*) FROM properties WHERE account_id = ? AND status = 'published' AND archived_at IS NULL", [$s['account_id']], 0);
        $x = ['{g}' => $guide, '{n}' => $aperture, '{qr}' => $qr];
        $numeri = $aperture > 0
            ? ($guide === 1 ? ($qr > 0 ? self::tt('In quest\'anno la tua guida è stata aperta {n} volte, {qr} delle quali dal QR.', $x) : self::tt('In quest\'anno la tua guida è stata aperta {n} volte.', $x))
                            : ($qr > 0 ? self::tt('In quest\'anno le tue {g} guide sono state aperte {n} volte, {qr} delle quali dal QR.', $x) : self::tt('In quest\'anno le tue {g} guide sono state aperte {n} volte.', $x)))
            : self::tt('Quest\'anno non abbiamo ancora registrato aperture: controlla che il QR sia in vista e che il link arrivi agli ospiti prima dell\'arrivo.');
        if (Inviti::disponibili() && $a['sconto'] <= 0) $numeri .= ' ' . self::tt('Puoi ancora abbassare il rinnovo: ogni amico che attiva la sua guida con il tuo invito vale il {n}% in meno.', ['{n}' => Inviti::PASSO]);
        return $numeri;
    }

    /**
     * 9D · La fascia in cima al Quadro e ad Account & Fatturazione, dagli stessi 30 giorni: rinnovo, fine dell'omaggio
     * o scadenza senza rinnovo. A 7 giorni in evidenza. Null se non c'è niente da dire. Chiudibile per 24 ore (cookie).
     * @return ?array{tipo:string,giorni:int,data:string,importo:string,piano:string,carta:string,evidenza:bool,sub:array,automatico:bool}
     */
    public static function fascia(array $acc, ?array $sub = null): ?array
    {
        $s = $sub ?? Subscriptions::active((int) $acc['id']);
        if (!$s || $s['provider'] === 'dimostrazione' || (string) $s['current_period_end'] === '') return null;
        $fine = (int) strtotime((string) $s['current_period_end']);
        $giorni = (int) round(($fine - time()) / 86400);   // ai giorni interi: «7 giorni» anche con qualche ora in più, come l'email
        if ($giorni > 30 || $giorni < 0) return null;
        $automatico = $s['provider'] === 'stripe' && (int) $s['cancel_at_period_end'] === 0 && in_array($s['status'], ['active', 'trialing'], true);
        $tipo = $automatico ? (!empty($s['omaggio_fino']) && $s['status'] === 'trialing' ? 'omaggio' : 'rinnovo') : 'scadenza';
        $a = $automatico ? self::prossimoAddebito($s) : ['netto' => 0, 'valuta' => 'EUR', 'piano' => (string) ((Plans::version((int) $s['package_version_id']) ?? [])['name'] ?? ''), 'quantita' => max(1, (int) $s['quantity'])];
        $l4 = (string) ($s['card_last4'] ?? '');
        return ['tipo' => $tipo, 'giorni' => $giorni, 'data' => Support::date((string) $s['current_period_end']),
                'importo' => $automatico ? strtr(Pannello::t('{prezzo} + IVA'), ['{prezzo}' => Support::money($a['netto'], $a['valuta'])]) : '',
                'piano' => $a['piano'] . ($a['quantita'] > 1 ? ' · ' . strtr(Pannello::t('{n} strutture'), ['{n}' => $a['quantita']]) : ''),
                'carta' => $l4 !== '' ? strtr(Pannello::t('carta •••• {n}'), ['{n}' => $l4]) : '', 'evidenza' => $giorni <= 7, 'sub' => $s, 'automatico' => $automatico,
                'chiave' => 'mhw_fascia_' . (int) $s['id'] . '_' . substr((string) $s['current_period_end'], 0, 10)];
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
        $quando = self::giorno((string) $s['current_period_end']);
        $piano = (Plans::version((int) $s['package_version_id']) ?? [])['name'] ?? '';
        $abb = $piano !== '' ? self::tt('il tuo abbonamento MyHouse Welcome {piano}', ['{piano}' => $piano]) : self::tt('il tuo abbonamento MyHouse Welcome');
        $x = ['{abb}' => $abb, '{data}' => $quando];
        if ($finito) {
            return [self::tt('La tua guida è offline'),
                    self::tt('{abb} è finito il {data}: la guida non si apre più, nemmeno dal QR. Testi, foto e QR sono salvati: appena rinnovi, la guida torna online con lo stesso QR, senza ristampare niente.', $x),
                    self::tt('Rinnova e torna online'), Support::baseUrl() . '/piano'];
        }
        $stripe = $s['provider'] === 'stripe';
        $contatto = (string) ((Config::get('legal') ?? [])['contact_email'] ?? '');
        return [self::tt('La tua guida va offline il {data}', $x),
                self::tt('{abb} finisce il {data} e non si rinnova da solo. Da quel giorno la guida non si apre più, nemmeno dal QR stampato. Niente si cancella.', $x) . ' '
                . ($stripe ? self::tt('Per restare online riattiva il rinnovo automatico dal tuo account: bastano due clic.')
                           : ($contatto !== '' ? self::tt('Per restare online scrivici a {email}: ti diciamo come rinnovare.', ['{email}' => $contatto]) : self::tt('Per restare online scrivici: ti diciamo come rinnovare.'))),
                $stripe ? self::tt('Riattiva il rinnovo') : self::tt('Vai al tuo account'), Support::baseUrl() . '/account'];
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
        $giorniR = $automatico ? (int) ceil((strtotime((string) $s['current_period_end']) - time()) / 86400) : 30;
        if (Db::one('SELECT id FROM email_optout WHERE account_id = ? AND kind = ?', [$s['account_id'], $tipo])) {
            return [false, $s['email'] . ' ha chiesto di non ricevere ' . self::TIPI[$tipo] . ': se serve, scrivigli tu.'];
        }
        self::lingua((int) $s['account_id']);
        $ok = self::manda((int) $s['account_id'], (string) $s['email'], (string) $s['user_name'], $tipo,
                          'manuale-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(3)), $automatico ? self::testoRinnovo($s, $giorniR <= 7 ? 7 : 30) : self::testoScadenza($s, $finito));
        return [$ok, $ok ? 'Promemoria mandato a ' . $s['email'] . '.' : 'L\'email a ' . $s['email'] . ' non è partita: controlla la posta in Impostazioni.'];
    }

    /**
     * 9Q · Le email sulla bozza per Amministrazione → Richiami: le strutture mai pubblicate create da 1 a 35 giorni, con lo
     * stato di «bozza7» e «bozza21» (inviata il …, programmata per il …, fermata e perché).
     * @return list<array{id:int,name:string,account_id:int,cliente:string,email:string,created_at:string,giorni:int,stati:array<string,array{0:string,1:string}>}>
     */
    public static function statoBozze(?int $adesso = null): array
    {
        if (!self::disponibili()) return [];
        $t = $adesso ?? time();
        $out = [];
        foreach (Db::all("SELECT p.id, p.name, p.account_id, p.created_at, u.email, u.name AS cliente, u.email_verified_at FROM properties p
                          JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                          WHERE p.is_demo = 0 AND p.archived_at IS NULL AND p.status <> 'published' AND (p.published_at IS NULL OR p.published_at = '')
                            AND p.created_at <= ? AND p.created_at > ? AND u.email NOT LIKE ? ORDER BY p.created_at",
                         [gmdate('Y-m-d\TH:i:s\Z', $t - 86400), gmdate('Y-m-d\TH:i:s\Z', $t - (Bozze::GIORNI['bozza21'] + Bozze::FINESTRA) * 86400), '%@' . Demo::DOMINIO]) as $p) {
            $acc = (int) $p['account_id'];
            $nato = (int) strtotime((string) $p['created_at']);
            $perche = self::fermaBozza($acc, (string) $p['email'], !empty($p['email_verified_at']));
            $stati = [];
            foreach (Bozze::GIORNI as $tipo => $giorni) {
                $inviata = Db::val('SELECT sent_at FROM email_log WHERE account_id = ? AND kind = ? AND ref = ?', [$acc, $tipo, (string) $p['id']]);
                $quando = $nato + $giorni * 86400;
                $stati[$tipo] = match (true) {
                    (bool) $inviata => ['inviata', 'il ' . Support::date((string) $inviata)],
                    $perche !== null => ['fermata', $perche],
                    $t > $quando + ($tipo === 'bozza7' ? Bozze::GIORNI['bozza21'] - Bozze::GIORNI['bozza7'] : Bozze::FINESTRA) * 86400 => ['fermata', 'finestra passata'],
                    default => ['programmata', $t >= $quando ? 'al prossimo giro' : 'il ' . Support::date(gmdate('Y-m-d\TH:i:s\Z', $quando))],
                };
            }
            $out[] = ['id' => (int) $p['id'], 'name' => (string) $p['name'], 'account_id' => $acc, 'cliente' => (string) $p['cliente'], 'email' => (string) $p['email'],
                      'created_at' => (string) $p['created_at'], 'giorni' => (int) floor(($t - $nato) / 86400), 'stati' => $stati];
        }
        return $out;
    }

    /** 9Q · Perché le email sulla bozza di questo account non partono (null: partono). */
    private static function fermaBozza(int $acc, string $email, bool $confermata): ?string
    {
        $vetrinaE = mb_strtolower(trim((string) Config::get('vetrina_email', '')));
        return match (true) {
            self::diProva($acc) => 'cliente di prova',
            $vetrinaE !== '' && mb_strtolower(trim($email)) === $vetrinaE => 'account della vetrina',
            !$confermata => 'email non confermata',
            Bozze::haGuidaOnline($acc) => 'ha già una guida online',
            (bool) Db::val("SELECT id FROM email_optout WHERE account_id = ? AND kind IN ('bozza', 'bozza7', 'bozza21', 'pubblica')", [$acc]) => '«Non ora» o «non mandarmene più»',
            default => null,
        };
    }

    /**
     * 9Q · «Manda adesso» da Amministrazione → Richiami: l'email del giorno 7 o 21 per una struttura, senza aspettare il giorno.
     * Valgono le stesse regole (una volta sola per struttura, mai se ferma). @return array{0:bool,1:string}
     */
    public static function mandaBozza(int $propertyId, string $tipo): array
    {
        if (!in_array($tipo, Bozze::TIPI, true) || !self::disponibili()) return [false, 'Email sconosciuta.'];
        $p = Db::one("SELECT p.*, u.email, u.name AS user_name, u.email_verified_at FROM properties p JOIN accounts a ON a.id = p.account_id
                      JOIN users u ON u.id = a.user_id WHERE p.id = ? AND p.is_demo = 0", [$propertyId]);
        if (!$p) return [false, 'Struttura non trovata.'];
        if ($p['status'] === 'published' || !empty($p['published_at'])) return [false, $p['name'] . ' è già stata pubblicata.'];
        $acc = (int) $p['account_id'];
        if (($perche = self::fermaBozza($acc, (string) $p['email'], !empty($p['email_verified_at']))) !== null) return [false, 'Non parte: ' . $perche . '.'];
        if (Db::val('SELECT id FROM email_log WHERE account_id = ? AND kind = ? AND ref = ?', [$acc, $tipo, (string) $p['id']])) return [false, 'Già mandata per ' . $p['name'] . '.'];
        self::lingua($acc);
        $ok = self::manda($acc, (string) $p['email'], (string) $p['user_name'], $tipo, (string) $p['id'], Bozze::testo($tipo, $p, self::$lin));
        return [$ok, $ok ? 'Email mandata a ' . $p['email'] . '.' : 'L\'email a ' . $p['email'] . ' non è partita: controlla la posta in Impostazioni.'];
    }

    /** Le email di servizio: partono una volta sola e non si disattivano (niente «non mandarmene più»). */
    private const SERVIZIO = ['benvenuto'];

    /**
     * Manda una volta sola: prima si scrive il registro (indice unico), poi l'email.
     * $lingua 'en': il saluto e le parti fisse in inglese (host con il pannello in inglese); il testo $m lo dà chi chiama.
     */
    public static function manda(int $accountId, string $email, string $nome, string $tipo, string $ref, array $m, ?string $lingua = null): bool
    {
        $lingua ??= self::$lin;   // 9N · quella fissata da lingua() prima di comporre il testo
        // 7F: i clienti di prova non ricevono richiami automatici; il promemoria mandato a mano dall'amministrazione sì.
        if (!str_starts_with($ref, 'manuale-') && self::diProva($accountId)) return false;
        // 10H · Un account eliminato da Amministrazione non riceve più niente.
        if (Migrator::columnExists('accounts', 'eliminato_at') && Db::val('SELECT eliminato_at FROM accounts WHERE id = ?', [$accountId])) return false;
        // 9H · L'account della vetrina (vetrina_email in config.php) non riceve email.
        $vetrinaE = mb_strtolower(trim((string) Config::get('vetrina_email', '')));
        if ($vetrinaE !== '' && mb_strtolower(trim($email)) === $vetrinaE) return false;
        // 9D · L'avviso a 7 giorni dall'addebito (rinnovo, ref «-7») è dovuto: parte anche a chi ha disattivato gli avvisi.
        $dovuta = $tipo === 'rinnovo' && str_ends_with($ref, '-7');
        $servizio = in_array($tipo, self::SERVIZIO, true);
        $rifiuti = self::RIFIUTI[$tipo] ?? [$tipo];
        if (!$dovuta && !$servizio && Db::val('SELECT id FROM email_optout WHERE account_id = ? AND kind IN (' . implode(',', array_fill(0, count($rifiuti), '?')) . ')', array_merge([$accountId], $rifiuti))) return false;
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
        $saluto = ($lingua === 'en' ? 'Hi' : 'Ciao') . (trim($nome) !== '' ? ' ' . trim(explode(' ', trim($nome))[0]) : '') . ',';
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $paragrafi = explode("\n\n", $saluto . ' ' . $corpo);   // una riga vuota nel testo = un paragrafo nuovo
        // 9H · Le email di pagamento: la frase sulle fatture e il Reply-To all'email amministrazione.
        $pagamento = in_array($tipo, ['rinnovo', 'scadenza', 'pagamento'], true);
        if ($pagamento) $paragrafi[] = Support::fraseFatture($lingua === 'en');
        $tl = fn(string $it, array $s = []) => Pannello::tIn($lingua, $it, $s);
        $dovutaT = $tl('Questo avviso è dovuto prima di un addebito e non si può disattivare.');
        $piedeHtml = $servizio ? '' : ($dovuta ? $dovutaT
                   : $e($tl('Non vuoi più ricevere {cosa}?', ['{cosa}' => $tl(self::TIPI[$tipo])])) . ' <a href="' . $e($stop) . '" style="color:#6a5b48">' . $e($tl('Non mandarmene più')) . '</a>');
        $piedeTesto = $servizio ? '' : ($dovuta ? $dovutaT : $tl('Non vuoi più ricevere {cosa}?', ['{cosa}' => $tl(self::TIPI[$tipo])]) . " $stop");
        $invio = fn() => Mailer::inLingua($lingua, fn() => Mailer::send($email, $oggetto . ' — MyHouse Welcome',
            Mailer::testo($oggetto, $paragrafi, $tasto, $nota, $piedeTesto, $scelte), Mailer::layout($oggetto, $paragrafi, $tasto, $nota, $piedeHtml, $scelte)));
        $ok = $pagamento ? Mailer::diPagamento($invio) : $invio();
        if (!$ok) Db::run('DELETE FROM email_log WHERE token = ?', [$token]);   // si riproverà al prossimo giro
        return $ok;
    }

    // ------------------------------------------------------- 10 · Concierge AI

    /** Il nome del mese nella lingua fissata: «ottobre 2026» / «October 2026». */
    private static function meseNome(string $ym): string
    {
        $m = (int) substr($ym, 5, 2); $a = substr($ym, 0, 4);
        $it = ['gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre'];
        return (self::$lin === 'en' ? gmdate('F', mktime(0, 0, 0, $m, 1, 2000)) : ($it[$m - 1] ?? $ym)) . ' ' . $a;
    }

    /**
     * Il riepilogo mensile all'host: dal 1° al 7° giorno del mese, per ogni guida pubblicata con l'assistente acceso e
     * almeno una domanda nel mese prima. Una volta per guida e mese (ref «mese-AAAA-MM-pid»), mai ai clienti di prova,
     * mai a chi ha chiesto di non riceverne («Non mandarmene più»). @return int quante email
     */
    public static function giroAssistente(int $t): int
    {
        if (!Assistente::disponibile() || (int) gmdate('j', $t) > 7) return 0;
        $mese = gmdate('Y-m', strtotime('first day of last month', $t));
        $n = 0;
        foreach (Db::all("SELECT DISTINCT p.id, p.name, p.account_id AS acc_id, u.email, u.name AS user_name FROM assistant_questions q
                          JOIN properties p ON p.id = q.property_id JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                          WHERE q.month = ? AND q.outcome IN ('ok', 'no') AND p.is_demo = 0 AND p.archived_at IS NULL AND p.status = 'published' AND u.email NOT LIKE ?",
                         [$mese, '%@' . Demo::DOMINIO]) as $p) {
            $r = Assistente::riepilogo((int) $p['id'], $mese);
            if ($r['domande'] === 0) continue;
            self::lingua((int) $p['acc_id']);
            $x = ['{nome}' => (string) $p['name'], '{mese}' => self::meseNome($mese), '{n}' => $r['domande'], '{ok}' => $r['ok'], '{no}' => $r['no'], '{lingue}' => $r['lingue']];
            $corpo = self::tt('a {mese} il Concierge AI di {nome} ha ricevuto {n} domande dagli ospiti: {ok} hanno avuto risposta dalla guida, {no} no (e gli ospiti hanno visto i tuoi contatti).', $x);
            if ($r['sezioni']) $corpo .= "\n\n" . self::tt('Le sezioni più chieste: ') . implode(', ', array_map(fn($s) => $s['titolo'] . ' (' . $s['n'] . ')', $r['sezioni'])) . '.';
            if ($r['senza']) {
                $corpo .= "\n\n" . self::tt('Senza risposta, da aggiungere alla guida: ') . implode(' · ', array_map(fn($q) => '«' . mb_substr(trim((string) $q['question']), 0, 120) . '»', $r['senza'])) . '. '
                        . self::tt('Due righe nella sezione giusta e la prossima volta il Concierge AI risponde da solo.');
            }
            $m = [self::tt('A {mese} il Concierge AI ha risposto {ok} volte', $x), $corpo, self::tt('Completa la guida'), Support::baseUrl() . '/pannello/' . (int) $p['id'] . '/impostazioni#concierge-ai',
                  self::tt('Le domande degli ospiti sono anonime: qui vedi solo i numeri e le domande rimaste senza risposta.')];
            if (self::manda((int) $p['acc_id'], (string) $p['email'], (string) $p['user_name'], 'assistente', 'mese-' . $mese . '-' . (int) $p['id'], $m)) $n++;
        }
        return $n;
    }

    /** L'avviso all'80% delle domande del mese (da Assistente::rispondi), una volta per guida e mese. */
    public static function avvisoAssistente(int $propertyId, int $accountId, array $contatore): void
    {
        $p = Db::one('SELECT p.id, p.name, u.email, u.name AS user_name FROM properties p JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id WHERE p.id = ? AND p.is_demo = 0', [$propertyId]);
        if (!$p) return;
        self::lingua($accountId);
        $x = ['{nome}' => (string) $p['name'], '{usate}' => $contatore['usate'], '{limite}' => $contatore['limite'], '{percento}' => $contatore['percento']];
        $m = [self::tt('Concierge AI: {nome} è al {percento}% delle domande del mese', $x),
              self::tt('il Concierge AI di {nome} ha già risposto a {usate} domande su {limite} comprese questo mese. Quando le finisce, si ferma fino al primo del mese prossimo e la guida resta come sempre: gli ospiti trovano tutto nelle sezioni.', $x)
              . "\n\n" . self::tt('Se ti serve un limite più alto, scrivici: lo alziamo noi.'),
              self::tt('Apri le impostazioni'), Support::baseUrl() . '/pannello/' . (int) $p['id'] . '/impostazioni#concierge-ai'];
        self::manda($accountId, (string) $p['email'], (string) $p['user_name'], 'assistente', '80-' . Assistente::mese() . '-' . (int) $p['id'], $m);
    }

    /** È un «Cliente di prova» (7F)? */
    public static function diProva(int $accountId): bool
    {
        return Migrator::columnExists('accounts', 'is_test') && (int) Db::val('SELECT is_test FROM accounts WHERE id = ?', [$accountId], 0) === 1;
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
        if (isset(self::RIFIUTI[$tipo])) $tipo = 'bozza';   // 9Q · «Non scrivermi più su questo» ferma tutte e due
        if (!isset(self::TIPI[$tipo]) || Db::one('SELECT id FROM email_optout WHERE account_id = ? AND kind = ?', [$accountId, $tipo])) return;
        Db::insert('email_optout', ['account_id' => $accountId, 'kind' => $tipo, 'created_at' => Support::now()]);
    }
}
