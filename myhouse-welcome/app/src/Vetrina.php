<?php
namespace MHW;

/**
 * 9L · «Le guide dei nostri host»: le schede in home di chi ha aderito al programma «Giudizio con sconto».
 *
 * Le regole (decise dal titolare, nei Termini al punto «Giudizio con sconto»):
 *   - un solo programma, una sola casella (al pagamento o dal pannello): chi aderisce ha la sua scheda da quando
 *     pubblica la guida, anche prima del giudizio; dopo 30 giorni lascia voto e commento, che si aggiungono alla scheda;
 *   - la scheda mostra nome della struttura, copertina, logo, nome dell'host senza cognome, città (provincia);
 *     MAI la foto dell'host, nessun link alla guida, nessun dato degli ospiti;
 *   - una scheda nuova è «da approvare»: la accende a mano l'amministrazione, al massimo 6 accese; si spegne da sola se
 *     la guida è archiviata, l'abbonamento scade, l'account non c'è più o manca la copertina (sincronizza(), dai Richiami);
 *   - il giudizio arrivato è «da approvare» finché l'amministrazione non lo approva; lo sconto non dipende dal voto;
 *   - il ritiro (dal pannello) toglie subito scheda e giudizio; lo sconto avuto resta, quello promesso decade (Giudizi).
 * Ogni cambio di stato va in audit_log con il codice cliente (9I). Il giudizio resta in testimonials (Testimonianze).
 */
final class Vetrina
{
    public const MASSIMO = 6;
    public const GIORNI_GIUDIZIO = 30;
    /** Gli stati calcolati di una riga (Vetrina::stato). */
    public const STATI = ['in_attesa' => 'In attesa', 'da_approvare' => 'Da approvare', 'pubblicato' => 'Pubblicato', 'ritirato' => 'Ritirato', 'sospeso' => 'Sospeso'];
    /** Perché una scheda non può stare in home (si spegne da sola). */
    public const SOSPESA = ['chiuso' => 'account chiuso', 'non_pubblicata' => 'guida non ancora pubblicata', 'archiviata' => 'guida archiviata',
                            'scaduto' => 'abbonamento scaduto', 'copertina' => 'manca la copertina'];
    public const FILTRI = ['tutti' => 'Tutti', 'in_home' => 'In home', 'da_approvare' => 'Da approvare', 'senza_giudizio' => 'Senza giudizio',
                           'ritirati' => 'Ritirati', 'segnalati' => 'Segnalati'];

    public static function disponibili(): bool { return Migrator::tableExists('vetrina_schede') && Giudizi::disponibili(); }

    public static function scheda(int $accountId): ?array
    {
        return self::disponibili() ? Db::one('SELECT * FROM vetrina_schede WHERE account_id = ?', [$accountId]) : null;
    }

    /** Le immagini che l'host sceglie di mostrare (copertina, logo). */
    public static function scelte(array $s): array
    {
        $j = json_decode((string) ($s['immagini'] ?? ''), true);
        return ['copertina' => !is_array($j) || !empty($j['copertina']), 'logo' => !is_array($j) || !empty($j['logo'])];
    }

    /** La struttura della scheda: la prima pubblicata dell'account (anche se poi archiviata, per dirlo in admin). */
    public static function struttura(array $s): ?array
    {
        $cols = 'id, account_id, name, city, cover_media_id, logo_media_id, status, archived_at, published_at, is_demo'
              . (Migrator::columnExists('properties', 'province') ? ', province' : '');
        if (!empty($s['property_id']) && ($p = Db::one("SELECT $cols FROM properties WHERE id = ? AND account_id = ?", [(int) $s['property_id'], (int) $s['account_id']]))) return $p;
        return Db::one("SELECT $cols FROM properties WHERE account_id = ? AND is_demo = 0 AND published_at IS NOT NULL AND published_at <> ''
                        ORDER BY CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END, published_at, id LIMIT 1", [(int) $s['account_id']]);
    }

    /**
     * La scheda di chi ha aderito, creata se manca (all'adesione, anche prima della pubblicazione: «in elenco subito»)
     * e aggiornata con la struttura e la data di pubblicazione appena la guida va online.
     */
    public static function assicura(int $accountId): ?array
    {
        if (!self::disponibili()) return null;
        $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$accountId]);
        if (!$acc || empty($acc['giudizio_adesione']) || Giudizi::escluso($acc)) return null;
        $s = self::scheda($accountId);
        if (!$s) {
            Db::insert('vetrina_schede', ['account_id' => $accountId, 'scheda_home' => 0, 'immagini' => json_encode(['copertina' => 1, 'logo' => 1]),
                                          'sospesa' => '', 'created_at' => Support::now(), 'updated_at' => Support::now()]);
            $s = self::scheda($accountId);
            self::registra('vetrina.scheda', $accountId, ['stato' => 'creata']);
        }
        if ($s && (empty($s['property_id']) || empty($s['scheda_da'])) && ($p = self::struttura($s)) && (string) $p['published_at'] !== '') {
            Db::update('vetrina_schede', ['property_id' => (int) $p['id'], 'scheda_da' => $s['scheda_da'] ?: (string) $p['published_at'], 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
            $s = self::scheda($accountId);
        }
        return $s;
    }

    /** Perché la scheda non può stare in home adesso ('' se può). */
    public static function motivoSospesa(array $s, ?array $p = null): string
    {
        if (!Db::one('SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $s['account_id']])) return 'chiuso';
        $p ??= self::struttura($s);
        if (!$p || (string) $p['published_at'] === '') return 'non_pubblicata';
        if (!empty($p['archived_at']) || $p['status'] !== 'published') return 'archiviata';
        if (!Subscriptions::active((int) $s['account_id'])) return 'scaduto';
        if (empty($p['cover_media_id']) || !self::scelte($s)['copertina']) return 'copertina';
        return '';
    }

    /** Il giudizio dell'account (l'ultimo), o null. */
    public static function giudizio(int $accountId): ?array { return Testimonianze::diAccount($accountId); }

    /** nessuno | da_approvare | pubblicato | ritirato */
    public static function statoGiudizio(?array $g): string
    {
        if (!$g) return 'nessuno';
        if (!empty($g['withdrawn_at'])) return 'ritirato';
        return $g['status'] === 'pubblicata' ? 'pubblicato' : 'da_approvare';
    }

    /** Lo stato della riga: ritirato > sospeso > da_approvare (giudizio da leggere o scheda mai decisa) > pubblicato > in_attesa. */
    public static function stato(array $s, ?array $g, string $sospesa): string
    {
        if (!empty($s['ritirato_at'])) return 'ritirato';
        if ($sospesa !== '') return 'sospeso';
        $sg = self::statoGiudizio($g);
        if ($sg === 'da_approvare' || empty($s['decisa_at'])) return 'da_approvare';
        return $sg === 'pubblicato' ? 'pubblicato' : 'in_attesa';
    }

    /** Il giorno in cui chiediamo il giudizio: 30 giorni dopo la pubblicazione. */
    public static function dataRichiesta(array $s): ?string
    {
        return !empty($s['scheda_da']) ? gmdate('Y-m-d\TH:i:s\Z', (int) strtotime((string) $s['scheda_da']) + self::GIORNI_GIUDIZIO * 86400) : null;
    }

    /** Le parole da controllare (Impostazioni → Giudizi con sconto), in minuscolo. */
    public static function parole(): array
    {
        $l = (string) ((Config::get('giudizi') ?? [])['parole'] ?? '');
        return array_values(array_filter(array_map(fn($x) => mb_strtolower(trim($x)), preg_split('/[,\n]/', $l) ?: [])));
    }

    /** «Segnalato»: un giudizio con 1–2 stelle o con parole da controllare. Solo per l'attenzione: lo sconto resta. */
    public static function segnalato(?array $g): bool
    {
        if (!$g || !empty($g['withdrawn_at'])) return false;
        if ((int) $g['rating'] >= 1 && (int) $g['rating'] <= 2) return true;
        $t = mb_strtolower((string) $g['body']);
        foreach (self::parole() as $p) if ($p !== '' && str_contains($t, $p)) return true;
        return false;
    }

    /** «Città (PR)»: la provincia accanto alla città, se c'è e non è già lì. */
    public static function dove(array $p): string
    {
        $citta = trim((string) ($p['city'] ?? '')); $pr = trim((string) ($p['province'] ?? ''));
        if ($pr === '' || $citta === '' || mb_strtolower($pr) === mb_strtolower($citta)) return $citta !== '' ? $citta : $pr;
        return $citta . ' (' . (mb_strlen($pr) <= 3 ? mb_strtoupper($pr) : $pr) . ')';
    }

    /** Il nome dell'host senza cognome. */
    public static function nomeHost(int $accountId): string
    {
        $n = trim((string) Db::val('SELECT u.name FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [$accountId], ''));
        return $n !== '' ? (preg_split('/\s+/', $n)[0] ?? $n) : 'Un host';
    }

    /**
     * Una scheda pronta per la home (o per l'anteprima nel pannello e in admin): copertina (max 800 px), logo, nome,
     * struttura e città, il giudizio approvato con la risposta pubblica, «Guida online da …». Mai la foto dell'host.
     */
    public static function perScheda(array $s, ?array $p = null, ?array $g = null): ?array
    {
        $p ??= self::struttura($s);
        if (!$p) return null;
        $sc = self::scelte($s);
        $g ??= self::giudizio((int) $s['account_id']);
        $ok = $g && self::statoGiudizio($g) === 'pubblicato';
        return [
            'id' => (int) $s['id'], 'account_id' => (int) $s['account_id'], 'property_name' => (string) $p['name'], 'luogo' => self::dove($p),
            'name' => self::nomeHost((int) $s['account_id']),
            'copertina' => $sc['copertina'] && !empty($p['cover_media_id']) ? Media::ridotta((int) $p['cover_media_id'], 800) : null,
            'logo' => $sc['logo'] && !empty($p['logo_media_id']) ? Media::ridotta((int) $p['logo_media_id'], 240) : null,
            'rating' => $ok ? (int) $g['rating'] : 0, 'body' => $ok ? (string) $g['body'] : '',
            'risposta' => $ok ? trim((string) ($s['risposta_pubblica'] ?? '')) : '',
            'online_da' => (string) ($s['scheda_da'] ?: $p['published_at']),
        ];
    }

    /**
     * Le schede di «Le guide dei nostri host»: accese, non ritirate, non sospese; prima quelle con il giudizio approvato,
     * poi le altre; le più recenti prima; al massimo 6.
     */
    public static function perHome(int $max = self::MASSIMO): array
    {
        if (!self::disponibili() || !Giudizi::homeAccesa()) return [];
        $out = [];
        foreach (Db::all('SELECT * FROM vetrina_schede WHERE scheda_home = 1 AND ritirato_at IS NULL ORDER BY scheda_da DESC, id DESC') as $s) {
            $p = self::struttura($s);
            if (self::motivoSospesa($s, $p) !== '') continue;
            if ($c = self::perScheda($s, $p)) $out[] = $c;
        }
        usort($out, fn($a, $b) => [$b['rating'] > 0, $b['online_da']] <=> [$a['rating'] > 0, $a['online_da']]);
        return array_slice($out, 0, $max);
    }

    /** Quante schede sono accese (e possono stare in home). */
    public static function accese(): int
    {
        return self::disponibili() ? (int) Db::val('SELECT COUNT(*) FROM vetrina_schede WHERE scheda_home = 1 AND ritirato_at IS NULL', [], 0) : 0;
    }

    // ------------------------------------------------------------ le azioni

    private static function registra(string $azione, int $accountId, array $meta = []): void
    {
        $codice = '';
        try { $codice = Codici::cliente($accountId); } catch (\Throwable) {}
        Auth::audit($azione, null, ['account_id' => $accountId, 'codice' => $codice] + $meta);
    }

    /** L'interruttore «In home» dell'amministrazione. @return string l'errore da mostrare, '' se fatto */
    public static function accendi(int $id, bool $si): string
    {
        $s = Db::one('SELECT * FROM vetrina_schede WHERE id = ?', [$id]);
        if (!$s) return 'Scheda non trovata.';
        if ($si) {
            if (!empty($s['ritirato_at'])) return 'L\'host si è ritirato dal programma: la scheda non può tornare in home.';
            if (($m = self::motivoSospesa($s)) !== '') return 'Non può andare in home: ' . self::SOSPESA[$m] . '.';
            if (!(int) $s['scheda_home'] && self::accese() >= self::MASSIMO) return 'In home ci sono già ' . self::MASSIMO . ' schede: spegnine una prima.';
        }
        Db::update('vetrina_schede', ['scheda_home' => $si ? 1 : 0, 'decisa_at' => Support::now(), 'sospesa' => '', 'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        self::registra('vetrina.home', (int) $s['account_id'], ['in_home' => $si]);
        try { Seo::scriviFile(); } catch (\Throwable $e) { Log::exception($e, 'vetrina: file SEO'); }
        return '';
    }

    /** Approva (o rimette da approvare) il giudizio: solo approvato compare sulla scheda. */
    public static function approvaGiudizio(int $id, bool $si): string
    {
        $s = Db::one('SELECT * FROM vetrina_schede WHERE id = ?', [$id]);
        $g = $s ? self::giudizio((int) $s['account_id']) : null;
        if (!$g || !empty($g['withdrawn_at'])) return 'Non c\'è un giudizio da approvare.';
        Db::update('testimonials', ['status' => $si ? 'pubblicata' : 'in_attesa', 'visible' => 0, 'published_at' => $si ? ($g['published_at'] ?: Support::now()) : $g['published_at']],
                   'id = :tid', ['tid' => (int) $g['id']]);
        if ($si && empty($s['decisa_at'])) Db::update('vetrina_schede', ['decisa_at' => Support::now()], 'id = :id', ['id' => $id]);
        self::registra($si ? 'vetrina.giudizio_approvato' : 'vetrina.giudizio_sospeso', (int) $s['account_id'], ['voto' => (int) $g['rating']]);
        return '';
    }

    /** La risposta pubblica sotto il giudizio (vuota = nessuna) e la nota interna. */
    public static function rispondi(int $id, string $testo): void
    {
        $s = Db::one('SELECT account_id FROM vetrina_schede WHERE id = ?', [$id]);
        if (!$s) return;
        Db::update('vetrina_schede', ['risposta_pubblica' => mb_substr(trim($testo), 0, 600), 'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        self::registra('vetrina.risposta', (int) $s['account_id'], ['vuota' => trim($testo) === '']);
    }

    public static function nota(int $id, string $testo): void
    {
        $s = Db::one('SELECT account_id FROM vetrina_schede WHERE id = ?', [$id]);
        if (!$s) return;
        Db::update('vetrina_schede', ['nota_admin' => mb_substr(trim($testo), 0, 1000), 'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        self::registra('vetrina.nota', (int) $s['account_id']);
    }

    /** L'host sceglie copertina e logo (chip). Senza copertina la scheda non va in home (stato «manca la copertina»). */
    public static function scegliImmagini(int $accountId, bool $copertina, bool $logo): void
    {
        $s = self::assicura($accountId);
        if (!$s) return;
        Db::update('vetrina_schede', ['immagini' => json_encode(['copertina' => $copertina ? 1 : 0, 'logo' => $logo ? 1 : 0]), 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
        self::registra('vetrina.immagini', $accountId, ['copertina' => $copertina, 'logo' => $logo]);
        if (!$copertina && (int) $s['scheda_home']) self::sincronizza();
    }

    /**
     * L'host si ritira: scheda e giudizio spariscono subito dalla home; lo sconto già avuto resta, quello promesso decade.
     * @return bool se c'era qualcosa da ritirare
     */
    public static function ritira(int $accountId): bool
    {
        if (!self::disponibili()) return false;
        $acc = Db::one('SELECT * FROM accounts WHERE id = ?', [$accountId]);
        if (!$acc || empty($acc['giudizio_adesione'])) return false;
        $s = self::assicura($accountId) ?? self::scheda($accountId);
        if ($s && !empty($s['ritirato_at'])) return false;
        if ($s) Db::update('vetrina_schede', ['ritirato_at' => Support::now(), 'scheda_home' => 0, 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
        Testimonianze::ritira($accountId);
        Giudizi::ritirato($acc);
        self::registra('vetrina.ritiro', $accountId);
        try { Seo::scriviFile(); } catch (\Throwable $e) { Log::exception($e, 'vetrina: file SEO'); }
        return true;
    }

    /**
     * Le schede di tutti gli aderenti (create se mancano) e lo spegnimento automatico: guida archiviata, abbonamento
     * scaduto, account chiuso, copertina mancante. Dai Richiami e da Amministrazione → Giudizi e vetrina.
     * @return int quante schede si sono spente
     */
    public static function sincronizza(): int
    {
        if (!self::disponibili()) return 0;
        foreach (Db::all('SELECT a.id FROM accounts a LEFT JOIN vetrina_schede v ON v.account_id = a.id WHERE a.giudizio_adesione IS NOT NULL AND v.id IS NULL') as $a) self::assicura((int) $a['id']);
        foreach (Db::all("SELECT * FROM vetrina_schede WHERE property_id IS NULL OR scheda_da IS NULL OR scheda_da = ''") as $s) self::assicura((int) $s['account_id']);
        $n = 0;
        foreach (Db::all('SELECT * FROM vetrina_schede WHERE scheda_home = 1') as $s) {
            $m = self::motivoSospesa($s);
            if ($m === '') continue;
            Db::update('vetrina_schede', ['scheda_home' => 0, 'sospesa' => $m, 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
            self::registra('vetrina.spenta', (int) $s['account_id'], ['motivo' => $m]);
            $n++;
        }
        if ($n) { try { Seo::scriviFile(); } catch (\Throwable $e) { Log::exception($e, 'vetrina: file SEO'); } }
        return $n;
    }

    // ------------------------------------------------------------ l'amministrazione

    /**
     * Le righe di «Giudizi e vetrina», con tutto quello che serve alla tabella, e i conteggi dei filtri.
     * @return array{righe:list<array>,conta:array<string,int>,numeri:array}
     */
    public static function elenco(string $filtro = 'tutti'): array
    {
        $righe = []; $conta = array_fill_keys(array_keys(self::FILTRI), 0);
        if (!self::disponibili()) return ['righe' => [], 'conta' => $conta, 'numeri' => []];
        $oggi = gmdate('Y-m-d');
        $nuoviOggi = 0; $voti = [];
        foreach (Db::all('SELECT v.*, a.giudizio_adesione, a.giudizio_adesione_at, a.giudizio_sconto_stato, a.giudizio_sconto_at, u.email
                          FROM vetrina_schede v LEFT JOIN accounts a ON a.id = v.account_id LEFT JOIN users u ON u.id = a.user_id ORDER BY v.id DESC') as $s) {
            $p = self::struttura($s);
            $g = self::giudizio((int) $s['account_id']);
            $sosp = empty($s['ritirato_at']) ? self::motivoSospesa($s, $p) : '';
            $r = $s + ['p' => $p, 'g' => $g, 'motivo' => $sosp, 'stato' => self::stato($s, $g, $sosp), 'stato_giudizio' => self::statoGiudizio($g),
                       'segnalato' => self::segnalato($g), 'scelte' => self::scelte($s), 'host' => self::nomeHost((int) $s['account_id']),
                       'codice' => Codici::disponibili() ? Codici::cliente((int) $s['account_id']) : '', 'piano' => self::piano((int) $s['account_id']),
                       'richiesta' => self::dataRichiesta($s)];
            $chiavi = ['tutti'];
            if ((int) $s['scheda_home'] && $sosp === '' && empty($s['ritirato_at'])) $chiavi[] = 'in_home';
            if ($r['stato'] === 'da_approvare') $chiavi[] = 'da_approvare';
            if (!$g && empty($s['ritirato_at'])) $chiavi[] = 'senza_giudizio';
            if (!empty($s['ritirato_at'])) $chiavi[] = 'ritirati';
            if ($r['segnalato']) $chiavi[] = 'segnalati';
            foreach ($chiavi as $k) $conta[$k]++;
            if ($g && empty($g['withdrawn_at'])) { $voti[] = (int) $g['rating']; if (str_starts_with((string) $g['created_at'], $oggi) && $r['stato'] === 'da_approvare') $nuoviOggi++; }
            if (in_array($filtro, $chiavi, true)) $righe[] = $r;
        }
        $attivi = (int) Db::val("SELECT COUNT(DISTINCT s.account_id) FROM subscriptions s WHERE s.status IN ('active','trialing','past_due')", [], 0);
        $aderenti = $conta['tutti'] - $conta['ritirati'];
        return ['righe' => $righe, 'conta' => $conta, 'numeri' => [
            'aderenti' => $aderenti, 'attivi' => max($attivi, $aderenti), 'in_home' => $conta['in_home'], 'posti' => self::MASSIMO,
            'giudizi' => count($voti), 'media' => $voti ? round(array_sum($voti) / count($voti), 1) : null,
            'da_approvare' => $conta['da_approvare'], 'nuovi_oggi' => $nuoviOggi,
        ]];
    }

    /** Il nome del piano in uso (o scelto) dell'account, per la tabella. */
    private static function piano(int $accountId): string
    {
        $s = Subscriptions::active($accountId);
        $pv = $s && !empty($s['package_version_id']) ? Plans::version((int) $s['package_version_id']) : null;
        if (!$pv && ($i = (int) Db::val('SELECT intended_package_version_id FROM accounts WHERE id = ?', [$accountId], 0))) $pv = Plans::version($i);
        return (string) ($pv['name'] ?? '');
    }

    /** Lo storico di una scheda: le righe di audit_log del programma per quell'account. */
    public static function storico(int $accountId): array
    {
        $out = [];
        foreach (Db::all("SELECT action, meta, created_at FROM audit_log WHERE (action LIKE 'vetrina.%' OR action LIKE 'giudizio.%' OR action LIKE 'testimonial.%')
                          ORDER BY id DESC LIMIT 400") as $r) {
            $m = json_decode((string) $r['meta'], true) ?: [];
            if ((int) ($m['account_id'] ?? 0) === $accountId) $out[] = $r + ['dati' => $m];
        }
        return $out;
    }

    /** Il CSV di «Giudizi e vetrina» (Excel italiano: BOM e punto e virgola). */
    public static function csv(): string
    {
        $f = fopen('php://temp', 'w+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['Codice cliente', 'Host', 'Email', 'Struttura', 'Città', 'Piano', 'Adesione', 'Dove', 'Sconto', 'Stato', 'In home', 'Voto', 'Giudizio', 'Segnalato', 'Ritirato il'], ';', '"', '');
        foreach (self::elenco()['righe'] as $r) {
            fputcsv($f, [$r['codice'], $r['host'], (string) $r['email'], (string) ($r['p']['name'] ?? ''), $r['p'] ? self::dove($r['p']) : '', $r['piano'],
                         substr((string) $r['giudizio_adesione_at'], 0, 10), Giudizi::ADESIONI[$r['giudizio_adesione']] ?? '', Giudizi::STATI[$r['giudizio_sconto_stato']] ?? '',
                         self::STATI[$r['stato']] ?? $r['stato'], (int) $r['scheda_home'] ? 'sì' : 'no', $r['g'] ? (int) $r['g']['rating'] : '', $r['g'] ? (string) $r['g']['body'] : '',
                         $r['segnalato'] ? 'sì' : 'no', substr((string) $r['ritirato_at'], 0, 10)], ';', '"', '');
        }
        rewind($f);
        return (string) stream_get_contents($f);
    }
}
