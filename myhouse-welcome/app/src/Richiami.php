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
                         'scadenza' => 'l\'avviso prima della scadenza'];   // con l'articolo: «Non vuoi più ricevere …?»
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

        $bozze = Db::all("SELECT p.*, a.id AS acc_id, u.email, u.name AS user_name FROM properties p
                          JOIN accounts a ON a.id = p.account_id JOIN users u ON u.id = a.user_id
                          WHERE p.is_demo = 0 AND p.archived_at IS NULL AND p.status <> 'published'
                            AND p.created_at <= ? AND p.created_at > ? AND u.email NOT LIKE ?",
                         [$iso($t - $giorno), $iso($t - 21 * $giorno), '%@' . Demo::DOMINIO]);
        foreach ($bozze as $p) {
            if (in_array((int) $p['id'], Entitlements::lockedIds((int) $p['acc_id']), true)) continue;
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
        Inviti::inSospeso();   // gli sconti inviti che Stripe non ha ancora preso
        try { CambioAbbonamento::applyPendingChanges(); } catch (\Throwable $e) { Log::exception($e, 'cambi di piano in sospeso'); }
        return $fatte;
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
        [$oggetto, $corpo, $bottone, $url] = $m;
        $stop = Support::baseUrl() . '/email/stop/' . $token;
        $saluto = 'Ciao' . (trim($nome) !== '' ? ' ' . trim(explode(' ', trim($nome))[0]) : '') . ',';
        $testo = "$saluto\n\n$corpo\n\n$bottone: $url\n\nMyHouse Welcome\n\n—\nNon vuoi più ricevere " . self::TIPI[$tipo] . "? $stop";
        $e = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html lang="it"><body style="margin:0;padding:24px;background:#faf5ec;font-family:Arial,sans-serif;color:#231b12">'
              . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;padding:28px">'
              . '<p style="font-size:16px;line-height:24px;margin:0 0 14px">' . $e($saluto) . '</p>'
              . '<p style="font-size:16px;line-height:24px;margin:0 0 24px">' . $e($corpo) . '</p>'
              . '<p style="margin:0 0 24px"><a href="' . $e($url) . '" style="display:inline-block;background:#b4451f;color:#fff8f2;text-decoration:none;font-weight:bold;padding:14px 22px;border-radius:999px">' . $e($bottone) . '</a></p>'
              . '<p style="font-size:14px;color:#6a5b48;margin:0">MyHouse Welcome</p></div>'
              . '<p style="max-width:520px;margin:16px auto 0;font-size:12px;line-height:18px;color:#6a5b48">Non vuoi più ricevere ' . $e(self::TIPI[$tipo])
              . '? <a href="' . $e($stop) . '" style="color:#6a5b48">Non mandarmene più</a></p></body></html>';
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

    public static function smetti(int $accountId, string $tipo): void
    {
        if (!isset(self::TIPI[$tipo]) || Db::one('SELECT id FROM email_optout WHERE account_id = ? AND kind = ?', [$accountId, $tipo])) return;
        Db::insert('email_optout', ['account_id' => $accountId, 'kind' => $tipo, 'created_at' => Support::now()]);
    }
}
