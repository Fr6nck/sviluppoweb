<?php
namespace MHW;

/**
 * Le traduzioni suggerite: Amazon Translate (TranslateText) propone, il cliente
 * approva. Punto 5 del controllo di ottobre 2026.
 *
 * Regole:
 *  - solo con la funzione auto_translation del piano (Plus e Portfolio), con
 *    l'interruttore della struttura acceso (Lingue) e dentro l'anno in omaggio,
 *    che parte alla prima accensione nell'account; l'amministratore lo può allungare;
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

    public static function omaggioFino(int $accountId): ?string
    {
        if (!Migrator::columnExists('accounts', 'translation_trial_until')) return null;
        $v = Db::val('SELECT translation_trial_until FROM accounts WHERE id = ?', [$accountId]);
        return $v ? (string) $v : null;
    }

    public static function omaggioFinito(int $accountId): bool
    {
        $fino = self::omaggioFino($accountId);
        return $fino !== null && $fino < Support::now();
    }

    /**
     * Perché qui non si può chiedere una traduzione: '' = si può.
     * $prop serve per l'interruttore della struttura.
     */
    public static function perche(int $accountId, ?array $prop = null): string
    {
        if (!self::nelPiano($accountId)) return 'Le traduzioni suggerite sono disponibili con il piano Plus.';
        if ($prop !== null && empty($prop['translation_suggest'])) return 'Accendi le traduzioni suggerite nella pagina Lingue.';
        if (self::omaggioFinito($accountId)) {
            return 'L\'anno in omaggio delle traduzioni suggerite è finito il ' . Support::date((string) self::omaggioFino($accountId)) . ': le traduzioni che hai approvato restano.';
        }
        if (!self::configurato()) return 'Le traduzioni suggerite non sono ancora attive su questo sito: riprova più tardi.';
        return '';
    }

    /**
     * Accende o spegne l'interruttore di una struttura. La prima accensione
     * nell'account fa partire l'anno in omaggio.
     * @return bool true se è la prima volta (la pagina mostra la spiegazione)
     */
    public static function interruttore(int $accountId, int $propertyId, bool $acceso): bool
    {
        if ($acceso && !self::nelPiano($accountId)) throw new \RuntimeException('Le traduzioni suggerite sono disponibili con il piano Plus.');
        $prima = false;
        Db::tx(function () use ($accountId, $propertyId, $acceso, &$prima) {
            Db::update('properties', ['translation_suggest' => $acceso ? 1 : 0], 'id = :pid AND account_id = :aid', ['pid' => $propertyId, 'aid' => $accountId]);
            if ($acceso && self::omaggioFino($accountId) === null) {
                Db::update('accounts', ['translation_trial_until' => gmdate('Y-m-d\TH:i:s\Z', strtotime('+12 months'))], 'id = :aid', ['aid' => $accountId]);
                $prima = true;
            }
        });
        return $prima;
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

    public static function tetti(): array
    {
        $c = self::config();
        return ['account' => max(0, (int) ($c['cap_account'] ?? 150000)), 'sito' => max(0, (int) ($c['cap_global'] ?? 1900000))];
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
        $n = mb_strlen($testo);
        $tetti = self::tetti();
        if (self::usati($accountId) + $n > $tetti['account']) {
            self::registra($accountId, $propertyId, $a, 0, 'limite', 'tetto account');
            throw new LimitReached('Per questo mese hai usato tutte le traduzioni suggerite: si ricomincia il primo del mese.');
        }
        if (self::usati() + $n > $tetti['sito']) {
            self::registra($accountId, $propertyId, $a, 0, 'limite', 'tetto sito');
            throw new LimitReached('Per questo mese le traduzioni suggerite sono finite: si ricomincia il primo del mese.');
        }
        try {
            $fuori = [];
            foreach (self::pezzi($testo) as $pezzo) $fuori[] = trim($pezzo) === '' ? $pezzo : self::chiama($pezzo, $da, $a);
            self::registra($accountId, $propertyId, $a, $n, 'ok');
            return implode('', $fuori);
        } catch (\Throwable $e) {
            self::registra($accountId, $propertyId, $a, 0, 'errore', $e->getMessage());
            Log::exception($e, 'traduzione suggerita');
            throw new \RuntimeException('Il traduttore automatico non ha risposto. Riprova tra qualche minuto.');
        }
    }

    /** «Prova la connessione» di Amministrazione → Impostazioni: una parola, registrata senza account. */
    public static function prova(): string
    {
        try {
            $t = self::chiama('Benvenuti', 'it', 'en');
            self::registra(0, null, 'en', 9, 'ok');
            return $t;
        } catch (\Throwable $e) {
            self::registra(0, null, 'en', 0, 'errore', $e->getMessage());
            throw $e;
        }
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
        $metti = function (string $tipo, int $id, string $path, string $origine, string $tradotto, int $max) use (&$out) {
            if (trim($origine) === '') return;
            $out["$tipo:$id:$path"] = ['tipo' => $tipo, 'id' => $id, 'path' => $path, 'origine' => $origine, 'tradotto' => trim($tradotto), 'max' => $max];
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
            if (!$c || $c['tradotto'] !== '') { Db::run('DELETE FROM translation_suggestions WHERE id = ?', [$s['id']]); continue; }
            $s['da_rifare'] = $s['source_text'] !== $c['origine'];
            $out[$k] = $s;
        }
        return $out;
    }

    /** I campi ancora da tradurre senza una suggerita valida (le «da rifare» si rifanno). */
    public static function daSuggerire(array $prop, string $loc): array
    {
        $campi = self::campi($prop, $loc);
        $sugg = self::suggerite($prop, $loc, $campi);
        return array_filter($campi, fn($c, $k) => $c['tradotto'] === '' && (!isset($sugg[$k]) || $sugg[$k]['da_rifare']), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * «Suggerisci le traduzioni mancanti»: un campo per chiamata, fino a un tetto o a un errore.
     * $solo: le chiavi da fare (vuoto = tutte). @return array{0:int,1:string} [quante, perché si è fermato]
     */
    public static function suggerisci(int $accountId, array $prop, string $loc, array $solo = []): array
    {
        $no = self::perche($accountId, $prop);
        if ($no !== '') return [0, $no];
        @set_time_limit(300);
        $fatte = 0;
        foreach (self::daSuggerire($prop, $loc) as $k => $c) {
            if ($solo && !in_array($k, $solo, true)) continue;
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
        if (!$c || $c['tradotto'] !== '') { Db::run('DELETE FROM translation_suggestions WHERE id = ?', [$id]); return 'Questo testo è già tradotto: la suggerita è stata tolta.'; }
        if ($s['source_text'] !== $c['origine']) return 'Il testo originale è cambiato: questa suggerita è da rifare.';
        $v = trim($testo ?? (string) $s['text']);
        if ($v === '') return 'La traduzione è vuota.';
        Db::tx(function () use ($s, $loc, $c, $v, $id) {
            self::scrivi($s['target_type'], (int) $s['target_id'], $loc, $s['field_path'], mb_substr($v, 0, $c['max']));
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
