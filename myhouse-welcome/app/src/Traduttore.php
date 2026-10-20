<?php
namespace MHW;

/**
 * Le traduzioni suggerite: Amazon Translate (TranslateText) propone, il cliente
 * approva. Punto 5 del controllo di ottobre 2026.
 *
 * Regole (9G, decisione del titolare):
 *  - solo con la funzione auto_translation del piano (Plus e Portfolio, anche concesso a mano), finché
 *    l'abbonamento è attivo: l'«anno in omaggio» della 7C non esiste più (la data resta solo come storico);
 *  - un tetto ANNUALE di caratteri per account (translate.cap_account, di serie 500.000; personale con
 *    accounts.translate_cap_override), che riparte al rinnovo; il tetto mensile per tutto il sito resta
 *    come freno di emergenza. All'80% e al 100% una email a noi;
 *  - prima di chiamare Amazon si guarda la memoria delle frasi già tradotte (translation_cache): una
 *    frase uguale (stesso testo, stesse lingue) si riusa senza spesa (esito «cache»);
 *  - prima di inviare si tolgono gli spazi doppi e le righe vuote ripetute, e si proteggono i segnaposto
 *    ({link}), gli indirizzi web ed email, i numeri di telefono, gli orari, le temperature e i prezzi:
 *    tornano al loro posto nella traduzione, non tradotti;
 *  - una suggerita sta in translation_suggestions, MAI nelle traduzioni: la guida
 *    non la vede finché il cliente non la approva. Approvata, diventa una
 *    traduzione come le altre («reviewed») e resta anche dopo l'omaggio;
 *  - una traduzione scritta da una persona non si sovrascrive mai: si suggerisce
 *    solo per i campi vuoti, e l'approvazione scrive solo se il campo è ancora vuoto;
 *  - se il testo originale cambia, la suggerita è «da rifare» (si confronta il testo);
 *  - un campo per chiamata, al massimo 10.000 byte (oltre, si spezza ai paragrafi);
 *  - ogni chiamata si registra in translation_usage; tetti al mese per account e
 *    per tutto il sito (Amministrazione → Impostazioni → Traduzioni).
 *
 * La firma è la SigV4 di S3Storage, con servizio «translate». Per le prove,
 * translate.endpoint punta al servizio finto (prove/translate-finto.php).
 */
final class Traduttore
{
    public const MAX_BYTE = 10000;
    /** Il piano gratuito di AWS: caratteri al mese, per 12 mesi dalla prima chiamata. */
    public const GRATIS_AL_MESE = 2000000;
    /** 9G · Il tetto annuale di serie per account (≈ 3–4 guide intere nelle 5 lingue). */
    public const TETTO_ANNUO = 500000;
    /** 9G · Quanto si aggiunge con «Aggiungi 250.000» nella scheda del cliente. */
    public const AGGIUNTA = 250000;
    /** 9G · Campi che non passano MAI dal traduttore (nomi propri, codici, indirizzi, link): per nome del campo (ultimo pezzo del percorso). */
    public const NON_TRADURRE = ['name', 'nome', 'network', 'ssid', 'password', 'code', 'codice', 'cin', 'url', 'link', 'email', 'phone', 'telefono', 'address', 'indirizzo'];
    private const BERSAGLIO = 'AWSShineFrontendService_20170701.TranslateText';
    /** I campi dei luoghi da tradurre, con la lunghezza delle colonne. */
    private const LUOGO = ['category' => 80, 'description' => 600, 'note' => 400, 'badge' => 80];

    // ------------------------------------------------------------- impostazioni

    /** La configurazione in uso: senza chiavi proprie si usano quelle dell'archivio S3. */
    public static function config(): array
    {
        $c = (array) Config::get('translate', []) + ['region' => 'eu-west-1', 'key' => '', 'secret' => '', 'endpoint' => ''];
        if ((string) $c['key'] === '' && (string) $c['secret'] === '') {
            $s3 = (array) (Config::get('storage')['s3'] ?? []);
            $c['key'] = (string) ($s3['key'] ?? ''); $c['secret'] = (string) ($s3['secret'] ?? ''); $c['token'] = (string) ($s3['token'] ?? '');
            $c['chiavi_s3'] = $c['key'] !== '';
        }
        return $c;
    }

    public static function configurato(): bool
    {
        $c = self::config();
        return (string) $c['key'] !== '' && (string) $c['secret'] !== '';
    }

    public static function nelPiano(int $accountId): bool { return Entitlements::can($accountId, 'auto_translation'); }

    /** 9M · La traduzione automatica parte solo dopo il pagamento di Plus o Portfolio (o con un omaggio, o un piano dato a mano). */
    public static function attiva(int $accountId): bool { return Subscriptions::active($accountId) !== null; }

    /** 8C: «Traduci anche la lingua aggiuntiva» (Amministrazione → Impostazioni → Traduzioni), di serie acceso. */
    public static function linguaExtra(): bool { return (bool) (Config::get('translate', [])['extra'] ?? true); }

    public static function omaggioFino(int $accountId): ?string
    {
        if (!Migrator::columnExists('accounts', 'translation_trial_until')) return null;
        $v = Db::val('SELECT translation_trial_until FROM accounts WHERE id = ?', [$accountId]);
        return $v ? (string) $v : null;
    }

    /** 9G · L'anno in omaggio non esiste più: non blocca mai (resta per chi la chiamava). */
    public static function omaggioFinito(int $accountId): bool { return false; }

    /**
     * Perché qui non si può chiedere una traduzione: '' = si può.
     * $prop serve per l'interruttore della struttura.
     */
    public static function perche(int $accountId, ?array $prop = null, ?string $loc = null): string
    {
        if (!self::nelPiano($accountId)) return 'Le traduzioni suggerite sono disponibili con il piano Plus.';
        // 9M · durante la creazione gratuita i bottoni ci sono, spenti, con «Disponibile dopo l'attivazione».
        if (!self::attiva($accountId)) return TestiSito::testoSemplice('attivazione.spiegone');
        // 8C: la lingua aggiuntiva (nl, ja…) si suggerisce solo se l'amministrazione lo lascia (Impostazioni → Traduzioni).
        if ($loc !== null && !I18n::base($loc) && !self::linguaExtra()) return 'Per questa lingua le traduzioni suggerite non ci sono: scrivila a mano, accanto al testo originale.';
        // 7C: la traduzione si chiede campo per campo, con un clic: l'interruttore della struttura non serve più per chiederla.
        // 9G: niente più anno in omaggio; conta il tetto annuale di caratteri.
        if (self::tettoRaggiunto($accountId)) return self::messaggioTetto($accountId);
        if (!self::configurato()) return 'Le traduzioni non sono disponibili in questo momento.';
        return '';
    }

    /**
     * Accende o spegne l'interruttore di una struttura. 9G: la prima accensione nell'account non fa più partire
     * un anno in omaggio (la data di prima, se c'è, resta come storico).
     * @return bool true se è la prima volta nell'account (la pagina mostra la spiegazione)
     */
    public static function interruttore(int $accountId, int $propertyId, bool $acceso): bool
    {
        if ($acceso && !self::nelPiano($accountId)) throw new \RuntimeException('Le traduzioni suggerite sono disponibili con il piano Plus.');
        if ($acceso && !self::attiva($accountId)) throw new \RuntimeException(TestiSito::testoSemplice('attivazione.spiegone'));
        $prima = $acceso && !(int) Db::val('SELECT COUNT(*) FROM properties WHERE account_id = ? AND translation_suggest = 1', [$accountId], 0);
        Db::update('properties', ['translation_suggest' => $acceso ? 1 : 0], 'id = :pid AND account_id = :aid', ['pid' => $propertyId, 'aid' => $accountId]);
        return $prima;
    }

    // ------------------------------------------------------- il tetto annuale (9G)

    /** L'anno delle traduzioni di un account: parte dall'attivazione dell'abbonamento e si azzera al rinnovo. [inizio ISO, fine ISO] */
    public static function anno(int $accountId): array
    {
        $s = Subscriptions::active($accountId);
        $fine = (string) ($s['current_period_end'] ?? '');
        $inizio = (string) ($s['current_period_start'] ?? '');
        if ($fine !== '' && ($t = strtotime($fine))) {
            // Un abbonamento annuale: l'anno è quello del periodo in corso (da «fine − 1 anno», o dall'attivazione se più recente).
            // Se la scadenza è oltre un anno (periodi lunghi, omaggi), si torna indietro di un anno alla volta fino all'anno in corso.
            while (strtotime('-1 year', $t) > time()) { $t = strtotime('-1 year', $t); $fine = gmdate('Y-m-d\TH:i:s\Z', $t); }
            $da = gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 year', $t));
            $creato = (string) ($s['created_at'] ?? '');
            if ($inizio !== '' && $inizio > $da) $da = $inizio;
            if ($creato !== '' && $creato > $da) $da = $creato;
            return [$da, $fine];
        }
        // Senza abbonamento (o senza data): l'anno solare in corso.
        return [gmdate('Y') . '-01-01T00:00:00Z', gmdate('Y') . '-12-31T23:59:59Z'];
    }

    /** Caratteri tradotti (esito «ok») nell'anno dell'abbonamento. */
    public static function usatiAnno(int $accountId): int
    {
        [$da] = self::anno($accountId);
        return (int) Db::val("SELECT COALESCE(SUM(chars), 0) FROM translation_usage WHERE account_id = ? AND outcome = 'ok' AND created_at >= ?", [$accountId, $da], 0);
    }

    /** Il tetto annuale dell'account: quello personale (scheda del cliente), altrimenti quello di serie (Impostazioni). */
    public static function tettoAccount(int $accountId): int
    {
        if (Migrator::columnExists('accounts', 'translate_cap_override')) {
            $v = Db::val('SELECT translate_cap_override FROM accounts WHERE id = ?', [$accountId]);
            if ($v !== null && (int) $v > 0) return (int) $v;
        }
        return self::tetti()['account'];
    }

    public static function tettoRaggiunto(int $accountId): bool
    {
        $t = self::tettoAccount($accountId);
        return $t > 0 && self::usatiAnno($accountId) >= $t;
    }

    /** Il messaggio quando l'anno è finito: si scrive a mano, oppure all'assistenza (il link mailto lo mette la pagina). */
    public static function messaggioTetto(int $accountId): string
    {
        return strtr(Pannello::t(Support::contatto() !== '' ? 'Hai usato le traduzioni suggerite di quest\'anno ({n} caratteri). Puoi scrivere le traduzioni a mano, oppure scrivici a {email}: le riattiviamo noi.'
                                                         : 'Hai usato le traduzioni suggerite di quest\'anno ({n} caratteri). Puoi scrivere le traduzioni a mano, oppure scrivici: le riattiviamo noi.'),
                     ['{n}' => number_format(self::usatiAnno($accountId), 0, ',', '.'), '{email}' => Support::contatto()]);
    }

    /**
     * Il contatore per Account & Fatturazione e Lingue: usati, tetto, percentuale, quanto resta, quando si azzera.
     * @return array{usati:int,tetto:int,percento:int,restante:int,azzera:string,basso:bool,finito:bool}
     */
    public static function contatore(int $accountId): array
    {
        $usati = self::usatiAnno($accountId); $tetto = self::tettoAccount($accountId);
        [, $fine] = self::anno($accountId);
        $perc = $tetto > 0 ? (int) min(100, floor($usati * 100 / $tetto)) : 0;
        $rest = max(0, $tetto - $usati);
        return ['usati' => $usati, 'tetto' => $tetto, 'percento' => $perc, 'restante' => $rest, 'azzera' => $fine,
                'basso' => $tetto > 0 && $rest < $tetto * 0.10, 'finito' => $tetto > 0 && $usati >= $tetto];
    }

    /** «Aggiungi 250.000» (o un tetto personale): nella scheda del cliente, con la nota nel registro. */
    public static function alzaTetto(int $accountId, int $nuovo): void
    {
        if (!Migrator::columnExists('accounts', 'translate_cap_override')) throw new \RuntimeException('Il database non ha ancora la colonna del tetto personale: apri il sito una volta per fare le migrazioni.');
        Db::update('accounts', ['translate_cap_override' => $nuovo > 0 ? $nuovo : null], 'id = :aid', ['aid' => $accountId]);
    }

    /** L'email a noi la prima volta che un account tocca l'80% e il 100% dell'anno (un segno in translation_usage per non ripetersi). */
    private static function avvisaNoi(int $accountId): void
    {
        $tetto = self::tettoAccount($accountId);
        if ($tetto <= 0 || Support::contatto() === '') return;
        $usati = self::usatiAnno($accountId);
        [$da, $fine] = self::anno($accountId);
        foreach ([100 => 'avviso100', 80 => 'avviso80'] as $soglia => $segno) {
            if ($usati < $tetto * $soglia / 100) continue;
            if (Db::val('SELECT id FROM translation_usage WHERE account_id = ? AND outcome = ? AND created_at >= ?', [$accountId, $segno, $da])) return;
            self::registra($accountId, null, '', 0, $segno, 'tetto annuale al ' . $soglia . '%');
            $u = Db::one('SELECT u.name, u.email FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$accountId]) ?: ['name' => '', 'email' => ''];
            $chi = trim((string) $u['name']) . ' · ' . (string) $u['email'];
            try {
                Mailer::manda(Support::contatto(), 'Traduzioni suggerite: ' . ($u['name'] ?: $u['email']) . ' al ' . $soglia . '% del tetto annuale',
                    'Un cliente ' . ($soglia === 100 ? 'ha finito' : 'è all\'80% del') . 'le traduzioni dell\'anno',
                    ['Cliente: ' . $chi . ' (account ' . $accountId . ').',
                     'Usati ' . number_format($usati, 0, ',', '.') . ' caratteri su ' . number_format($tetto, 0, ',', '.') . ' nell\'anno che si azzera il ' . Support::date($fine) . '.',
                     $soglia === 100 ? 'Da ora il cliente vede «Hai usato le traduzioni suggerite di quest\'anno» e può scriverci: nella sua scheda c\'è «Aggiungi 250.000».'
                                     : 'Al 100% le suggerite si fermano: se serve, dalla sua scheda si aggiungono 250.000 caratteri.'],
                    ['Apri la scheda del cliente', Support::baseUrl() . '/admin/cliente/' . $accountId]);
            } catch (\Throwable $e) { Log::exception($e, 'avviso tetto traduzioni'); }
            return;
        }
    }

    // -------------------------------------------------- pulizia e protezione (9G)

    /**
     * Prima di inviare: via gli spazi doppi e le righe vuote ripetute; i segnaposto ({link}), gli indirizzi web ed email,
     * i numeri di telefono, gli orari (10:30), le temperature (22 °C), i prezzi (15 €) e le percentuali diventano ⟦n⟧ e
     * tornano al loro posto dopo. @return array{0:string,1:array<string,string>} [testo protetto, mappa ⟦n⟧ => originale]
     */
    public static function proteggi(string $t): array
    {
        $t = (string) preg_replace('/[ \t]{2,}/u', ' ', $t);
        $t = (string) preg_replace('/\n[ \t]*\n(?:[ \t]*\n)+/u', "\n\n", $t);
        $mappa = []; $n = 0;
        $pattern = '~\{[a-z_]+\}|https?://[^\s<>"]+|www\.[^\s<>"]+|[\w.+-]+@[\w-]+(?:\.[\w-]+)+|\+?\d[\d .\-/]{5,}\d|\b\d{1,2}[:.]\d{2}\b|\b\d+(?:[.,]\d+)?\s?(?:°C|°F|€|%)~u';
        $out = (string) preg_replace_callback($pattern, function (array $m) use (&$mappa, &$n): string {
            $n++; $k = "\u{27E6}$n\u{27E7}"; $mappa[$k] = $m[0]; return $k;
        }, $t);
        return [$out, $mappa];
    }

    /** I segnaposto tornano al loro posto (anche se il traduttore ci ha messo uno spazio intorno). */
    public static function ripristina(string $t, array $mappa): string
    {
        foreach ($mappa as $k => $v) $t = (string) preg_replace('/\s*' . preg_quote($k, '/') . '\s*/u', ' ' . $v . ' ', $t);
        return trim((string) preg_replace('/ {2,}/u', ' ', (string) preg_replace('/ ([,.;:!?])/u', '$1', $t)));
    }

    /** La chiave della memoria: testo protetto + lingue. */
    private static function chiaveCache(string $testo, string $da, string $a): string { return hash('sha256', $testo . '|' . $da . '|' . $a); }

    /** 9G · La pulizia della memoria (dal cron, una volta al giorno): le righe mai riusate più vecchie di 12 mesi. */
    public static function pulisciCache(): int
    {
        if (!Migrator::tableExists('translation_cache')) return 0;
        $prima = gmdate('Y-m-d\TH:i:s\Z', strtotime('-12 months'));
        return Db::run('DELETE FROM translation_cache WHERE hits = 0 AND created_at < ?', [$prima]);
    }

    // ------------------------------------------------------------ consumi e costi

    public static function mese(): string { return gmdate('Y-m'); }

    /** Caratteri tradotti nel mese (di un account, o di tutti). */
    public static function usati(?int $accountId = null, ?string $mese = null): int
    {
        $sql = "SELECT COALESCE(SUM(chars), 0) FROM translation_usage WHERE month = ? AND outcome = 'ok'";
        $a = [$mese ?? self::mese()];
        if ($accountId !== null) { $sql .= ' AND account_id = ?'; $a[] = $accountId; }
        return (int) Db::val($sql, $a, 0);
    }

    /** I tetti: «account» è ANNUALE (9G, di serie 500.000), «sito» resta mensile (freno di emergenza). */
    public static function tetti(): array
    {
        $c = self::config();
        $acc = (int) ($c['cap_account'] ?? self::TETTO_ANNUO);
        // Il valore di serie di prima (150.000 al mese) non ha senso come annuale: vale quello nuovo. Un altro valore è una scelta: resta.
        if ($acc === 150000) $acc = self::TETTO_ANNUO;
        return ['account' => max(0, $acc), 'sito' => max(0, (int) ($c['cap_global'] ?? 1900000))];
    }

    /** Il piano gratuito di AWS vale ancora (data impostata e non passata)? */
    public static function gratuitoAttivo(?string $giorno = null): bool
    {
        $fino = (string) (self::config()['free_tier_until'] ?? '');
        return $fino !== '' && ($giorno ?? Support::today()) <= $fino;
    }

    /** Costo stimato in dollari di un mese con $caratteri tradotti; col piano gratuito, i primi 2 milioni non si pagano. */
    public static function costoUsd(int $caratteri, bool $conGratuito): float
    {
        $prezzo = (float) str_replace(',', '.', (string) (self::config()['price_usd_per_million'] ?? '15'));
        $da = $conGratuito ? max(0, $caratteri - self::GRATIS_AL_MESE) : $caratteri;
        return $da / 1000000 * $prezzo;
    }

    public static function inEuro(float $usd): float
    {
        return $usd * (float) str_replace(',', '.', (string) (self::config()['usd_eur'] ?? '0.86'));
    }

    private static function registra(int $accountId, ?int $propertyId, string $locale, int $chars, string $esito, string $errore = ''): void
    {
        Db::insert('translation_usage', ['account_id' => $accountId, 'property_id' => $propertyId, 'month' => self::mese(), 'locale' => $locale,
            'chars' => $chars, 'outcome' => $esito, 'error' => mb_substr($errore, 0, 255), 'created_at' => Support::now()]);
    }

    // --------------------------------------------------------------- la chiamata

    /**
     * Traduce un testo. Controlla i tetti, registra la chiamata.
     * @throws LimitReached oltre un tetto; \RuntimeException se il servizio non risponde
     */
    public static function traduci(int $accountId, ?int $propertyId, string $testo, string $da, string $a): string
    {
        // 9G · prima la pulizia e la protezione (meno caratteri, niente segnaposto tradotti), poi la memoria.
        [$protetto, $mappa] = self::proteggi($testo);
        $n = mb_strlen($protetto);
        $hash = self::chiaveCache($protetto, $da, $a);
        if (Migrator::tableExists('translation_cache') && ($c = Db::one('SELECT id, testo_tradotto FROM translation_cache WHERE hash = ?', [$hash]))) {
            Db::run('UPDATE translation_cache SET hits = hits + 1, used_at = ? WHERE id = ?', [Support::now(), $c['id']]);
            self::registra($accountId, $propertyId, $a, $n, 'cache');
            return self::ripristina((string) $c['testo_tradotto'], $mappa);
        }
        $tetti = self::tetti();
        $tettoAnno = self::tettoAccount($accountId);
        if ($tettoAnno > 0 && self::usatiAnno($accountId) + $n > $tettoAnno) {
            self::registra($accountId, $propertyId, $a, 0, 'limite', 'tetto annuale account');
            self::avvisaNoi($accountId);
            throw new LimitReached(self::messaggioTetto($accountId));
        }
        if ($tetti['sito'] > 0 && self::usati() + $n > $tetti['sito']) {
            self::registra($accountId, $propertyId, $a, 0, 'limite', 'tetto sito');
            throw new LimitReached('Per questo mese le traduzioni suggerite sono finite: si ricomincia il primo del mese.');
        }
        try {
            $fuori = [];
            // I codici di Amazon: il portoghese del Portogallo è pt-PT, quello del Brasile pt (I18n::aws).
            foreach (self::pezzi($protetto) as $pezzo) $fuori[] = trim($pezzo) === '' ? $pezzo : self::chiama($pezzo, I18n::aws($da), I18n::aws($a));
            $tradotto = implode('', $fuori);
            self::registra($accountId, $propertyId, $a, $n, 'ok');
            if (Migrator::tableExists('translation_cache')) {
                try { Db::insert('translation_cache', ['hash' => $hash, 'da' => $da, 'a' => $a, 'chars' => $n, 'testo_tradotto' => $tradotto, 'hits' => 0, 'created_at' => Support::now()]); }
                catch (\Throwable) { }   // la stessa frase arrivata nello stesso momento da due richieste
            }
            self::avvisaNoi($accountId);
            return self::ripristina($tradotto, $mappa);
        } catch (\Throwable $e) {
            self::registra($accountId, $propertyId, $a, 0, 'errore', $e->getMessage());
            Log::exception($e, 'traduzione suggerita');
            throw new \RuntimeException('Il traduttore automatico non ha risposto. Riprova tra qualche minuto; se succede ancora, scrivici da «Aiuto».');
        }
    }

    /** «Prova la connessione» di Amministrazione → Impostazioni: una parola, registrata senza account. */
    public static function prova(): string
    {
        try {
            $t = self::chiama('Buongiorno', 'it', 'en');
            self::registra(0, null, 'en', 10, 'ok');
            return $t;
        } catch (\Throwable $e) {
            self::registra(0, null, 'en', 0, 'errore', $e->getMessage());
            throw $e;
        }
    }

    /** 7C: cosa fare, dato il messaggio esatto di Amazon Translate (per «Prova» e per Diagnostica). */
    public static function consiglio(string $errore): string
    {
        return match (true) {
            str_contains($errore, 'UnrecognizedClient') => 'La chiave di accesso (Access key ID) non esiste o è stata disattivata: controllala in AWS → IAM → Utenti → Credenziali di sicurezza.',
            str_contains($errore, 'InvalidSignature') || str_contains($errore, 'SignatureDoesNotMatch') => 'La chiave segreta non corrisponde alla chiave di accesso, oppure l\'orologio del server è sbagliato di più di 5 minuti.',
            str_contains($errore, 'AccessDenied') => 'La chiave funziona ma non ha il permesso di tradurre: all\'utente IAM serve la policy «TranslateReadOnly» (o il permesso translate:TranslateText).',
            str_contains($errore, 'SubscriptionRequired') || str_contains($errore, 'OptInRequired') => 'Amazon Translate non è attivo su questo account AWS: attivalo dalla console di AWS.',
            str_contains($errore, 'Could not resolve') || str_contains($errore, 'resolve host') => 'La regione scritta non esiste o il server non raggiunge Amazon: controlla la regione (per esempio eu-west-1).',
            str_contains($errore, 'UnsupportedLanguagePair') || str_contains($errore, 'UnsupportedLanguage') => 'La regione scelta non offre questa coppia di lingue: prova eu-west-1 o eu-central-1.',
            str_contains($errore, 'Throttling') || str_contains($errore, 'LimitExceeded') => 'Troppe richieste in poco tempo: riprova tra un minuto.',
            str_contains($errore, 'timed out') || str_contains($errore, 'Timeout') => 'Amazon non ha risposto in tempo: riprova, e se si ripete controlla che l\'hosting permetta le connessioni in uscita.',
            default => '',
        };
    }

    /** L'impronta di un testo originale (7C): sha1, per accorgersi quando cambia. */
    public static function impronta(string $t): string { return sha1(trim($t)); }

    /** Salva l'impronta dell'originale di un campo tradotto (approvato o salvato a mano). */
    public static function fissa(string $tipo, int $id, string $loc, string $path, string $origine): void
    {
        if (!Migrator::tableExists('translation_hashes')) return;
        $h = self::impronta($origine);
        $c = Db::one('SELECT id, source_hash FROM translation_hashes WHERE target_type = ? AND target_id = ? AND locale = ? AND field_path = ?', [$tipo, $id, $loc, $path]);
        if (!$c) Db::insert('translation_hashes', ['target_type' => $tipo, 'target_id' => $id, 'locale' => $loc, 'field_path' => $path, 'source_hash' => $h, 'created_at' => Support::now()]);
        elseif ($c['source_hash'] !== $h) Db::update('translation_hashes', ['source_hash' => $h, 'created_at' => Support::now()], 'id = :id', ['id' => $c['id']]);
    }

    /**
     * Le impronte dei campi tradotti che non l'hanno ancora (tradotti prima della 7C, o salvati a mano), e di quelli
     * appena cambiati in $prima → $dopo (stesse chiavi di campi()). Così il controllo «testo cambiato» parte da adesso.
     */
    public static function fissaImpronte(array $prop, string $loc, array $dopo, array $prima = []): void
    {
        if (!Migrator::tableExists('translation_hashes')) return;
        $hanno = [];
        foreach (Db::all('SELECT target_type, target_id, field_path FROM translation_hashes WHERE locale = ?', [$loc]) as $h) $hanno[$h['target_type'] . ':' . $h['target_id'] . ':' . $h['field_path']] = true;
        foreach ($dopo as $k => $c) {
            if ($c['tradotto'] === '') continue;
            $cambiataQui = isset($prima[$k]) && $prima[$k]['tradotto'] !== $c['tradotto'];
            if (!isset($hanno[$k]) || $cambiataQui) self::fissa($c['tipo'], (int) $c['id'], $loc, $c['path'], $c['origine']);
        }
    }

    /**
     * Quanti campi mancano, per lingua e per sezione (7C: «EN da tradurre», «Manca in: …», il passo Pubblica).
     * @return array<string,array<int,int>> [lingua => [id sezione => campi mancanti]]
     */
    public static function mancanti(array $prop): array
    {
        $out = [];
        foreach (Db::all('SELECT locale FROM property_locales WHERE property_id = ?', [$prop['id']]) as $l) {
            $loc = (string) $l['locale'];
            if ($loc === $prop['default_locale']) continue;
            $per = [];
            foreach (self::campi($prop, $loc) as $c) {
                if ($c['tradotto'] !== '') continue;
                $sid = $c['tipo'] === 'section' ? $c['id'] : (int) Db::val('SELECT section_id FROM places WHERE id = ?', [$c['id']], 0);
                $per[$sid] = ($per[$sid] ?? 0) + 1;
            }
            $out[$loc] = $per;
        }
        return $out;
    }

    /** Un testo oltre i 10.000 byte si spezza ai paragrafi (o, se serve, alle frasi). */
    public static function pezzi(string $testo): array
    {
        if (strlen($testo) <= self::MAX_BYTE) return [$testo];
        $out = []; $cur = '';
        foreach (preg_split('/(?<=\n)|(?<=[.!?] )/u', $testo) ?: [$testo] as $parte) {
            while (strlen($parte) > self::MAX_BYTE) {   // una frase sola troppo lunga: si taglia ai caratteri
                $taglio = mb_strcut($parte, 0, self::MAX_BYTE, 'UTF-8');
                if ($cur !== '') { $out[] = $cur; $cur = ''; }
                $out[] = $taglio; $parte = substr($parte, strlen($taglio));
            }
            if (strlen($cur . $parte) > self::MAX_BYTE) { $out[] = $cur; $cur = ''; }
            $cur .= $parte;
        }
        if ($cur !== '') $out[] = $cur;
        return $out;
    }

    /** La richiesta firmata a Amazon Translate: corpo, intestazioni e indirizzo (le prove della firma la confrontano con botocore). */
    public static function richiesta(string $testo, string $da, string $a, string $amzDate): array
    {
        $c = self::config();
        $url = rtrim((string) ($c['endpoint'] ?: 'https://translate.' . $c['region'] . '.amazonaws.com'), '/') . '/';
        $corpo = json_encode(['Text' => $testo, 'SourceLanguageCode' => $da, 'TargetLanguageCode' => $a], JSON_UNESCAPED_UNICODE);
        $h = S3Storage::signHeaders('POST', $url, ['content-type' => 'application/x-amz-json-1.1', 'x-amz-target' => self::BERSAGLIO],
                                    hash('sha256', $corpo), (string) $c['region'], (string) $c['key'], (string) $c['secret'], $amzDate,
                                    (string) ($c['token'] ?? ''), 'translate');
        return [$url, $corpo, $h];
    }

    private static function chiama(string $testo, string $da, string $a): string
    {
        [$url, $corpo, $h] = self::richiesta($testo, $da, $a, gmdate('Ymd\THis\Z'));
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $corpo, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys($h), $h),
        ]);
        $risposta = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        $j = json_decode($risposta, true);
        if ($code !== 200 || !is_array($j) || !isset($j['TranslatedText'])) {
            $tipo = is_array($j) ? (string) ($j['__type'] ?? '') : '';
            throw new \RuntimeException('Amazon Translate ' . $code . ($tipo !== '' ? ' ' . preg_replace('/^.*#/', '', $tipo) : '') . ': '
                . mb_substr($err !== '' ? $err : (string) (is_array($j) ? ($j['message'] ?? $j['Message'] ?? '') : $risposta), 0, 200));
        }
        return (string) $j['TranslatedText'];
    }

    // ------------------------------------------------------ i campi da tradurre

    /**
     * Tutti i testi da tradurre di una struttura, nella lingua $loc.
     * @return array<string,array{tipo:string,id:int,path:string,origine:string,tradotto:string,max:int}> per chiave «tipo:id:path»
     */
    public static function campi(array $prop, string $loc): array
    {
        $out = [];
        // 7C: l'impronta del testo originale quando la traduzione è stata approvata o salvata.
        $impronte = [];
        if (Migrator::tableExists('translation_hashes')) {
            foreach (Db::all("SELECT h.target_type, h.target_id, h.field_path, h.source_hash FROM translation_hashes h
                              WHERE h.locale = ? AND ((h.target_type = 'section' AND h.target_id IN (SELECT id FROM sections WHERE property_id = ?))
                                 OR (h.target_type = 'place' AND h.target_id IN (SELECT pl.id FROM places pl JOIN sections s ON s.id = pl.section_id WHERE s.property_id = ?)))",
                             [$loc, $prop['id'], $prop['id']]) as $h) $impronte[$h['target_type'] . ':' . $h['target_id'] . ':' . $h['field_path']] = $h['source_hash'];
        }
        $metti = function (string $tipo, int $id, string $path, string $origine, string $tradotto, int $max) use (&$out, $impronte) {
            if (trim($origine) === '') return;
            $k = "$tipo:$id:$path";
            $out[$k] = ['tipo' => $tipo, 'id' => $id, 'path' => $path, 'origine' => $origine, 'tradotto' => trim($tradotto), 'max' => $max,
                        // «Il testo italiano è cambiato»: tradotto, ma l'originale non è più quello di allora.
                        'cambiato' => trim($tradotto) !== '' && isset($impronte[$k]) && $impronte[$k] !== self::impronta($origine)];
        };
        $base = $prop['default_locale'];
        foreach (Db::all('SELECT * FROM sections WHERE property_id = ? AND is_active = 1 ORDER BY is_core DESC, position, id', [$prop['id']]) as $s) {
            $o = Db::one('SELECT title, data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $base]) ?: ['title' => '', 'data' => ''];
            $t = Db::one('SELECT title, data FROM section_translations WHERE section_id = ? AND locale = ?', [$s['id'], $loc]) ?: ['title' => '', 'data' => ''];
            $od = json_decode((string) $o['data'], true) ?: []; $td = json_decode((string) $t['data'], true) ?: [];
            $sid = (int) $s['id'];
            // Il titolo: solo se il cliente l'ha scritto lui; quello del catalogo è già tradotto.
            if (trim((string) $o['title']) !== '' && $o['title'] !== SectionCatalog::title($s['kind'], $base)) {
                $tt = (string) $t['title'];
                $metti('section', $sid, 'title', (string) $o['title'], $tt === SectionCatalog::title($s['kind'], $loc) ? '' : $tt, 120);
            }
            foreach (SectionCatalog::fields($s['kind']) as $f => $def) {
                $tipo = $def[0];
                if ($tipo === 'repeater') {
                    $comuni = json_decode((string) $s['data'], true)[$f] ?? [];
                    $trPerId = [];
                    foreach ((array) ($td[$f] ?? []) as $x) if (is_array($x) && isset($x['id'])) $trPerId[(string) $x['id']] = $x;
                    foreach (SectionCatalog::rows($def, $comuni, $od[$f] ?? []) as $r) {
                        foreach ($def['sub'] as $sn => $sd) {
                            if (!SectionCatalog::isTranslated($sd[0])) continue;
                            $metti('section', $sid, "$f.{$r['id']}.$sn", (string) ($r[$sn] ?? ''), (string) ($trPerId[$r['id']][$sn] ?? ''), $sd[0] === 'textarea' ? 2000 : 300);
                        }
                    }
                } elseif (in_array($tipo, ['steps', 'list'], true)) {
                    foreach (array_values((array) ($od[$f] ?? [])) as $i => $v) $metti('section', $sid, "$f.$i", (string) $v, (string) (array_values((array) ($td[$f] ?? []))[$i] ?? ''), 600);
                } elseif (SectionCatalog::isTranslated($tipo)) {
                    $metti('section', $sid, $f, is_string($od[$f] ?? null) ? $od[$f] : '', is_string($td[$f] ?? null) ? $td[$f] : '', $tipo === 'textarea' ? 2000 : 300);
                }
            }
            if (SectionCatalog::hasPlaces($s['kind'])) {
                foreach (Db::all('SELECT * FROM places WHERE section_id = ? ORDER BY position, id', [$sid]) as $pl) {
                    $po = Db::one('SELECT * FROM place_translations WHERE place_id = ? AND locale = ?', [$pl['id'], $base]) ?: [];
                    $pt = Db::one('SELECT * FROM place_translations WHERE place_id = ? AND locale = ?', [$pl['id'], $loc]) ?: [];
                    foreach (self::LUOGO as $f => $max) {
                        // Categoria ed etichetta scelte dall'elenco si traducono da sole.
                        if ($f === 'category' && (string) ($pl['category_key'] ?? '') !== '') continue;
                        if ($f === 'badge' && (string) ($pl['badge_key'] ?? '') !== '') continue;
                        $metti('place', (int) $pl['id'], $f, (string) ($po[$f] ?? ''), (string) ($pt[$f] ?? ''), $max);
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Le suggerite di una lingua, per chiave «tipo:id:path», con 'da_rifare' se il
     * testo originale è cambiato. Quelle di campi spariti o già tradotti si tolgono.
     */
    public static function suggerite(array $prop, string $loc, ?array $campi = null): array
    {
        $campi ??= self::campi($prop, $loc);
        $out = [];
        foreach (Db::all('SELECT * FROM translation_suggestions WHERE property_id = ? AND locale = ? ORDER BY id', [$prop['id'], $loc]) as $s) {
            $k = $s['target_type'] . ':' . $s['target_id'] . ':' . $s['field_path'];
            $c = $campi[$k] ?? null;
            if (!$c || ($c['tradotto'] !== '' && !$c['cambiato'])) { Db::run('DELETE FROM translation_suggestions WHERE id = ?', [$s['id']]); continue; }
            $s['da_rifare'] = $s['source_text'] !== $c['origine'];
            // 9G: il campo aveva già una traduzione (approvata o scritta a mano) e l'originale è cambiato: la suggerita la sostituisce, «da ricontrollare».
            $s['aggiornata'] = $c['tradotto'] !== '';
            $out[$k] = $s;
        }
        return $out;
    }

    /** 9G · Un percorso di campo che non si manda mai al traduttore (per nome dell'ultimo pezzo). */
    public static function nonTradurre(string $path): bool
    {
        $parti = explode('.', $path);
        return in_array(strtolower((string) end($parti)), self::NON_TRADURRE, true);
    }

    /** I campi ancora da tradurre senza una suggerita valida (le «da rifare» si rifanno). */
    public static function daSuggerire(array $prop, string $loc): array
    {
        $campi = self::campi($prop, $loc);
        $sugg = self::suggerite($prop, $loc, $campi);
        return array_filter($campi, fn($c, $k) => $c['tradotto'] === '' && !self::nonTradurre($c['path']) && (!isset($sugg[$k]) || $sugg[$k]['da_rifare']), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * «Suggerisci le traduzioni mancanti»: un campo per chiamata, fino a un tetto o a un errore.
     * $solo: le chiavi da fare (vuoto = tutte). @return array{0:int,1:string} [quante, perché si è fermato]
     */
    public static function suggerisci(int $accountId, array $prop, string $loc, array $solo = []): array
    {
        $no = self::perche($accountId, $prop, $loc);
        if ($no !== '') return [0, $no];
        @set_time_limit(300);
        $fatte = 0;
        // 7C: con $solo (un campo, «Traduci» o «Traduci di nuovo») vale anche un campo tradotto il cui originale è cambiato.
        $lista = self::daSuggerire($prop, $loc);
        if ($solo) foreach (self::campi($prop, $loc) as $k => $c) if ($c['cambiato'] && in_array($k, $solo, true)) $lista[$k] = $c;
        if ($lista && empty($prop['translation_suggest'])) self::interruttore($accountId, (int) $prop['id'], true);   // il clic è il consenso
        foreach ($lista as $k => $c) {
            if ($solo && !in_array($k, $solo, true)) continue;
            if (self::nonTradurre($c['path'])) continue;   // 9G: nomi, codici, indirizzi e link non passano dal traduttore
            try {
                $testo = self::traduci($accountId, (int) $prop['id'], $c['origine'], $prop['default_locale'], $loc);
            } catch (LimitReached | \RuntimeException $e) {
                return [$fatte, $e->getMessage()];
            }
            Db::run('DELETE FROM translation_suggestions WHERE target_type = ? AND target_id = ? AND locale = ? AND field_path = ?', [$c['tipo'], $c['id'], $loc, $c['path']]);
            Db::insert('translation_suggestions', ['property_id' => $prop['id'], 'locale' => $loc, 'target_type' => $c['tipo'], 'target_id' => $c['id'],
                'field_path' => $c['path'], 'source_text' => $c['origine'], 'text' => $testo, 'chars' => mb_strlen($c['origine']), 'created_at' => Support::now()]);
            $fatte++;
        }
        return [$fatte, ''];
    }

    // ------------------------------------------------------------- approvazione

    /**
     * Approva una suggerita (con il testo corretto dal cliente, se c'è): diventa la
     * traduzione del campo. Se nel frattempo il campo è stato tradotto a mano, o la
     * suggerita è da rifare, non scrive niente. @return string '' se approvata, altrimenti il perché
     */
    public static function approva(array $prop, string $loc, int $id, ?string $testo = null): string
    {
        $s = Db::one('SELECT * FROM translation_suggestions WHERE id = ? AND property_id = ? AND locale = ?', [$id, $prop['id'], $loc]);
        if (!$s) return 'Questa traduzione suggerita non c\'è più.';
        $k = $s['target_type'] . ':' . $s['target_id'] . ':' . $s['field_path'];
        $c = self::campi($prop, $loc)[$k] ?? null;
        if (!$c || ($c['tradotto'] !== '' && !$c['cambiato'])) { Db::run('DELETE FROM translation_suggestions WHERE id = ?', [$id]); return 'Questo testo è già tradotto: la suggerita è stata tolta.'; }
        if ($s['source_text'] !== $c['origine']) return 'Il testo originale è cambiato: questa suggerita è da rifare.';
        $v = trim($testo ?? (string) $s['text']);
        if ($v === '') return 'La traduzione è vuota.';
        Db::tx(function () use ($s, $loc, $c, $v, $id) {
            self::scrivi($s['target_type'], (int) $s['target_id'], $loc, $s['field_path'], mb_substr($v, 0, $c['max']));
            self::fissa($s['target_type'], (int) $s['target_id'], $loc, $s['field_path'], $c['origine']);
            Db::run('DELETE FROM translation_suggestions WHERE id = ?', [$id]);
        });
        return '';
    }

    public static function scarta(array $prop, string $loc, int $id): void
    {
        Db::run('DELETE FROM translation_suggestions WHERE id = ? AND property_id = ? AND locale = ?', [$id, $prop['id'], $loc]);
    }

    /** Scrive un campo tradotto, come se l'avesse scritto il cliente («reviewed»). Serve anche alla vetrina (Demo). */
    public static function scrivi(string $tipo, int $id, string $loc, string $path, string $v): void
    {
        if ($tipo === 'place') {
            if (!isset(self::LUOGO[$path])) return;
            $t = Db::one('SELECT id FROM place_translations WHERE place_id = ? AND locale = ?', [$id, $loc]);
            if ($t) Db::update('place_translations', [$path => $v], 'id = :tid', ['tid' => $t['id']]);
            else Db::insert('place_translations', [$path => $v] + ['place_id' => $id, 'locale' => $loc, 'category' => '', 'description' => '', 'note' => '', 'badge' => '']);
            return;
        }
        $t = Db::one('SELECT * FROM section_translations WHERE section_id = ? AND locale = ?', [$id, $loc]);
        $kind = (string) Db::val('SELECT kind FROM sections WHERE id = ?', [$id]);
        $dati = $t ? (json_decode((string) $t['data'], true) ?: []) : [];
        $titolo = $t['title'] ?? SectionCatalog::title($kind, $loc);
        $parti = explode('.', $path);
        if ($path === 'title') $titolo = $v;
        elseif (count($parti) === 1) $dati[$parti[0]] = $v;
        elseif (count($parti) === 2) {   // una voce di un elenco: le voci prima, se mancano, restano vuote
            $lista = array_values((array) ($dati[$parti[0]] ?? []));
            for ($i = count($lista); $i < (int) $parti[1]; $i++) $lista[] = '';
            $lista[(int) $parti[1]] = $v;
            $dati[$parti[0]] = $lista;
        } else {                          // un campo di una riga: la riga tradotta si trova per id
            [$f, $rid, $sn] = $parti;
            $righe = array_values((array) ($dati[$f] ?? []));
            $trovata = false;
            foreach ($righe as &$r) if (is_array($r) && (string) ($r['id'] ?? '') === $rid) { $r[$sn] = $v; $trovata = true; }
            unset($r);
            if (!$trovata) $righe[] = ['id' => $rid, $sn => $v];
            $dati[$f] = $righe;
        }
        $riga = ['title' => $titolo, 'data' => json_encode($dati, JSON_UNESCAPED_UNICODE), 'state' => 'reviewed', 'updated_at' => Support::now()];
        if ($t) Db::update('section_translations', $riga, 'id = :tid', ['tid' => $t['id']]);
        else Db::insert('section_translations', $riga + ['section_id' => $id, 'locale' => $loc, 'body' => '']);
    }
}
