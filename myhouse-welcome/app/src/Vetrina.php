<?php
namespace MHW;

/**
 * 9L · Le due pubblicazioni del programma «Giudizio con sconto», decise dall'amministrazione e approvate SEPARATAMENTE:
 *   1. la scheda in «Le guide dei nostri host» (copertina, logo, nome della struttura, nome dell'host senza cognome, città):
 *      esiste da quando la guida è pubblicata, anche senza giudizio; di norma si approvano tutte; in home le prime
 *      postiSchede() (4, Impostazioni → Sito) nell'ordine deciso in admin, le altre nella pagina «Tutte le guide»
 *      (/guide-dei-nostri-host); senza copertina o logo la scheda mostra il segnaposto «Welcome My House» ed è «incompleta»;
 *   2. il giudizio in «Cosa dicono di noi» (stelle, commento, risposta pubblica): mai in automatico, di norma i 5 stelle,
 *      al massimo postiGiudizi() (6) in home, ordine a mano.
 * La stessa struttura può stare in tutte e due («doppione» in admin). MAI la foto dell'host, nessun link, nessun dato degli ospiti.
 * Si spengono da sole: guida archiviata, abbonamento scaduto, account chiuso (sincronizza(), dai Richiami); copertina o logo
 * tolti dopo l'approvazione → la scheda torna «da approvare». Il ritiro dell'host (dal pannello) spegne tutte e due;
 * lo sconto avuto resta, quello promesso decade (Giudizi). Ogni azione in audit_log con il codice cliente (9I).
 * Il giudizio resta in testimonials (Testimonianze); qui le decisioni (vetrina_schede, migrazioni 050 e 051).
 */
final class Vetrina
{
    public const GIORNI_GIUDIZIO = 30;
    public const PER_PAGINA = 24;
    /** Lo stato della scheda (statoScheda) e quello del giudizio (statoGiudizio). */
    public const STATI_SCHEDA = ['da_approvare' => 'Da approvare', 'in_home' => 'In home', 'in_vetrina' => 'In vetrina', 'ritirata_admin' => 'Ritirata dall\'admin',
                                 'sospesa' => 'Sospesa', 'ritirato' => 'Ritirato dal programma'];
    public const STATI_GIUDIZIO = ['in_attesa' => 'In attesa', 'da_approvare' => 'Da approvare', 'pubblicato' => 'In home', 'non_pubblicato' => 'Non pubblicato',
                                   'sospeso' => 'Sospeso', 'ritirato' => 'Ritirato'];
    /** Perché una scheda (e il suo giudizio) non può stare in home: si spengono da sole. */
    public const SOSPESA = ['chiuso' => 'account chiuso', 'non_pubblicata' => 'guida non ancora pubblicata', 'archiviata' => 'guida archiviata',
                            'scaduto' => 'abbonamento scaduto'];
    /** Le tre schede in alto di «Giudizi e vetrina». */
    public const SCHEDE = ['vetrina' => 'Vetrina (schede)', 'giudizi' => 'Giudizi', 'ritirati' => 'Ritirati'];

    public static function disponibili(): bool
    {
        return Migrator::tableExists('vetrina_schede') && Migrator::columnExists('vetrina_schede', 'giudizio_home') && Giudizi::disponibili();
    }

    /** I posti in home: schede (di serie 4) e giudizi (di serie 6), da Impostazioni → Sito. */
    public static function postiSchede(): int { return max(1, min(24, (int) ((Config::get('giudizi') ?? [])['posti_schede'] ?? 4) ?: 4)); }

    public static function postiGiudizi(): int { return max(1, min(12, (int) ((Config::get('giudizi') ?? [])['posti_giudizi'] ?? 6) ?: 6)); }

    public static function scheda(int $accountId): ?array
    {
        return Migrator::tableExists('vetrina_schede') ? Db::one('SELECT * FROM vetrina_schede WHERE account_id = ?', [$accountId]) : null;
    }

    /** Le immagini che l'host sceglie di mostrare (copertina, logo). */
    public static function scelte(array $s): array
    {
        $j = json_decode((string) ($s['immagini'] ?? ''), true);
        return ['copertina' => !is_array($j) || !empty($j['copertina']), 'logo' => !is_array($j) || !empty($j['logo'])];
    }

    /** Le immagini che la scheda mostra davvero: scelte dall'host E presenti nella guida. */
    public static function mostrate(array $s, ?array $p): array
    {
        $sc = self::scelte($s);
        return ['copertina' => $sc['copertina'] && !empty($p['cover_media_id']), 'logo' => $sc['logo'] && !empty($p['logo_media_id'])];
    }

    /** «Incompleta»: manca la copertina o il logo (al loro posto il segnaposto). @return string[] quelle che mancano */
    public static function mancano(array $s, ?array $p): array
    {
        return array_keys(array_filter(self::mostrate($s, $p), fn($x) => !$x));
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

    /** Perché scheda e giudizio non possono stare in home adesso ('' se possono). */
    public static function motivoSospesa(array $s, ?array $p = null): string
    {
        if (!Db::one('SELECT a.id FROM accounts a JOIN users u ON u.id = a.user_id WHERE a.id = ?', [(int) $s['account_id']])) return 'chiuso';
        $p ??= self::struttura($s);
        if (!$p || (string) $p['published_at'] === '') return 'non_pubblicata';
        if (!empty($p['archived_at']) || $p['status'] !== 'published') return 'archiviata';
        if (!Subscriptions::active((int) $s['account_id'])) return 'scaduto';
        return '';
    }

    /** Il giudizio dell'account (l'ultimo), o null. */
    public static function giudizio(int $accountId): ?array { return Testimonianze::diAccount($accountId); }

    /** Le immagini approvate con la scheda: se una manca adesso, la scheda va riapprovata. */
    private static function approvate(array $s): array
    {
        $j = json_decode((string) ($s['immagini'] ?? ''), true);
        return is_array($j) && is_array($j['approvate'] ?? null) ? $j['approvate'] : [];
    }

    /** Una scheda approvata che ha perso la copertina o il logo con cui era stata approvata. */
    public static function daRiapprovare(array $s, ?array $p): bool
    {
        $ora = self::mostrate($s, $p);
        foreach (self::approvate($s) as $k) if (empty($ora[$k])) return true;
        return false;
    }

    /** ritirato (host) > sospesa > ritirata_admin > da_approvare > in_home | in_vetrina (dalla posizione tra le approvate). */
    public static function statoScheda(array $s, string $sospesa, int $posizione = 0): string
    {
        if (!empty($s['ritirato_at'])) return 'ritirato';
        if ($sospesa !== '') return 'sospesa';
        if (!empty($s['scheda_ritirata_at'])) return 'ritirata_admin';
        if (!(int) $s['scheda_home']) return 'da_approvare';
        return $posizione > 0 && $posizione <= self::postiSchede() ? 'in_home' : 'in_vetrina';
    }

    /** in_attesa (nessun giudizio) | ritirato | sospeso | pubblicato | non_pubblicato | da_approvare */
    public static function statoGiudizio(array $s, ?array $g, string $sospesa = ''): string
    {
        if (!$g) return !empty($s['ritirato_at']) ? 'ritirato' : 'in_attesa';
        if (!empty($g['withdrawn_at']) || !empty($s['ritirato_at'])) return 'ritirato';
        if ($sospesa !== '') return 'sospeso';
        if ((int) ($s['giudizio_home'] ?? 0)) return 'pubblicato';
        if (!empty($s['giudizio_ritirato_at'])) return 'non_pubblicato';
        return 'da_approvare';
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
     * Una scheda pronta per la pagina (home, «Tutte le guide», anteprime nel pannello e in admin): copertina (max 800 px)
     * e logo, o null (al loro posto il segnaposto), nome, struttura e città, «Guida online da …». Mai la foto dell'host.
     * Con $conGiudizio: anche stelle, commento e risposta pubblica (per «Cosa dicono di noi»).
     */
    public static function perScheda(array $s, ?array $p = null, ?array $g = null, bool $conGiudizio = false): ?array
    {
        $p ??= self::struttura($s);
        if (!$p) return null;
        $m = self::mostrate($s, $p);
        if ($conGiudizio) $g ??= self::giudizio((int) $s['account_id']);
        $ok = $conGiudizio && $g && empty($g['withdrawn_at']);
        return [
            'id' => (int) $s['id'], 'account_id' => (int) $s['account_id'], 'property_name' => (string) $p['name'], 'luogo' => self::dove($p),
            'name' => self::nomeHost((int) $s['account_id']),
            'copertina' => $m['copertina'] ? Media::ridotta((int) $p['cover_media_id'], 800) : null,
            'logo' => $m['logo'] ? Media::ridotta((int) $p['logo_media_id'], 240) : null,
            'rating' => $ok ? (int) $g['rating'] : 0, 'body' => $ok ? (string) $g['body'] : '',
            'risposta' => $ok ? trim((string) ($s['risposta_pubblica'] ?? '')) : '',
            'online_da' => (string) ($s['scheda_da'] ?: $p['published_at']),
        ];
    }

    /**
     * Le schede approvate e visibili, nell'ordine deciso in admin: le prime postiSchede() in home, tutte in «Tutte le guide».
     * Fuori: ritirate (host o admin), sospese, da riapprovare (copertina o logo tolti dopo l'approvazione).
     * @return list<array{s:array,p:array}>
     */
    public static function visibili(): array
    {
        if (!self::disponibili()) return [];
        $out = [];
        foreach (Db::all('SELECT * FROM vetrina_schede WHERE scheda_home = 1 AND ritirato_at IS NULL AND scheda_ritirata_at IS NULL
                          ORDER BY CASE WHEN scheda_ordine > 0 THEN 0 ELSE 1 END, scheda_ordine, scheda_approvata_at, id') as $s) {
            $p = self::struttura($s);
            if (!$p || self::motivoSospesa($s, $p) !== '' || self::daRiapprovare($s, $p)) continue;
            $out[] = ['s' => $s, 'p' => $p];
        }
        return $out;
    }

    /** La posizione di una scheda tra le approvate e visibili (1 = la prima), 0 se non c'è. */
    public static function posizione(int $id): int
    {
        foreach (self::visibili() as $i => $x) if ((int) $x['s']['id'] === $id) return $i + 1;
        return 0;
    }

    /** «Le guide dei nostri host» in home: le prime postiSchede() approvate. */
    public static function perHome(?int $max = null): array
    {
        if (!Giudizi::homeAccesa()) return [];
        return array_values(array_filter(array_map(fn($x) => self::perScheda($x['s'], $x['p']), array_slice(self::visibili(), 0, $max ?? self::postiSchede()))));
    }

    /** Quante schede approvate e visibili (in home e in «Tutte le guide»). */
    public static function quanteApprovate(): int { return Giudizi::homeAccesa() ? count(self::visibili()) : 0; }

    /** La pagina «Tutte le guide» (/guide-dei-nostri-host): PER_PAGINA schede per pagina. @return array{schede:list<array>,pagina:int,pagine:int,totale:int} */
    public static function tutte(int $pagina = 1): array
    {
        $tutte = Giudizi::homeAccesa() ? self::visibili() : [];
        $pagine = max(1, (int) ceil(count($tutte) / self::PER_PAGINA));
        $pagina = max(1, min($pagine, $pagina));
        $parte = array_slice($tutte, ($pagina - 1) * self::PER_PAGINA, self::PER_PAGINA);
        return ['schede' => array_values(array_filter(array_map(fn($x) => self::perScheda($x['s'], $x['p']), $parte))), 'pagina' => $pagina, 'pagine' => $pagine, 'totale' => count($tutte)];
    }

    /** I giudizi accesi e visibili, nell'ordine deciso in admin. @return list<array{s:array,p:array,g:array}> */
    private static function giudiziAccesi(): array
    {
        if (!self::disponibili()) return [];
        $out = [];
        foreach (Db::all('SELECT * FROM vetrina_schede WHERE giudizio_home = 1 AND ritirato_at IS NULL
                          ORDER BY CASE WHEN giudizio_ordine > 0 THEN 0 ELSE 1 END, giudizio_ordine, giudizio_approvato_at, id') as $s) {
            $p = self::struttura($s);
            $g = self::giudizio((int) $s['account_id']);
            if (!$p || !$g || !empty($g['withdrawn_at']) || self::motivoSospesa($s, $p) !== '') continue;
            $out[] = ['s' => $s, 'p' => $p, 'g' => $g];
        }
        return $out;
    }

    /** «Cosa dicono di noi» in home: i giudizi accesi, al massimo postiGiudizi(). */
    public static function giudiziHome(): array
    {
        if (!Giudizi::homeAccesa()) return [];
        return array_values(array_filter(array_map(fn($x) => self::perScheda($x['s'], $x['p'], $x['g'], true), array_slice(self::giudiziAccesi(), 0, self::postiGiudizi()))));
    }

    /** Per Impostazioni → prova: quante schede approvate. */
    public static function accese(): int { return count(self::visibili()); }

    // ------------------------------------------------------------ le azioni

    private static function registra(string $azione, int $accountId, array $meta = []): void
    {
        $codice = '';
        try { $codice = Codici::cliente($accountId); } catch (\Throwable) {}
        Auth::audit($azione, null, ['account_id' => $accountId, 'codice' => $codice] + $meta);
    }

    private static function riga(int $id): ?array { return Db::one('SELECT * FROM vetrina_schede WHERE id = ?', [$id]); }

    private static function seo(): void { try { Seo::scriviFile(); } catch (\Throwable $e) { Log::exception($e, 'vetrina: file SEO'); } }

    /** Il prossimo numero d'ordine (in fondo). */
    private static function inFondo(string $col): int { return (int) Db::val("SELECT COALESCE(MAX($col), 0) FROM vetrina_schede", [], 0) + 1; }

    /** Approva la scheda (anche «Rimetti» dopo un ritiro dell'admin): va in fondo all'ordine. @return string l'errore, '' se fatto */
    public static function approvaScheda(int $id): string
    {
        $s = self::riga($id);
        if (!$s) return 'Scheda non trovata.';
        if (!empty($s['ritirato_at'])) return 'L\'host si è ritirato dal programma: la scheda non può tornare.';
        $p = self::struttura($s);
        if (($m = self::motivoSospesa($s, $p)) !== '') return 'Non si può approvare: ' . self::SOSPESA[$m] . '.';
        $imm = json_decode((string) ($s['immagini'] ?? ''), true) ?: ['copertina' => 1, 'logo' => 1];
        $imm['approvate'] = array_keys(array_filter(self::mostrate($s, $p)));
        Db::update('vetrina_schede', ['scheda_home' => 1, 'scheda_approvata_at' => Support::now(), 'scheda_ritirata_at' => null, 'scheda_motivo' => null,
                                      'scheda_ordine' => (int) $s['scheda_ordine'] > 0 && (int) $s['scheda_home'] ? (int) $s['scheda_ordine'] : self::inFondo('scheda_ordine'),
                                      'immagini' => json_encode($imm), 'decisa_at' => Support::now(), 'sospesa' => '', 'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        self::registra('vetrina.scheda_approvata', (int) $s['account_id'], ['incompleta' => self::mancano($s, $p)]);
        self::seo();
        return '';
    }

    /** L'admin ritira la scheda, con un motivo interno (il cliente vede «non in home al momento»). */
    public static function ritiraScheda(int $id, string $motivo = ''): string
    {
        $s = self::riga($id);
        if (!$s) return 'Scheda non trovata.';
        Db::update('vetrina_schede', ['scheda_home' => 0, 'scheda_ritirata_at' => Support::now(), 'scheda_motivo' => mb_substr(trim($motivo), 0, 255) ?: null,
                                      'decisa_at' => Support::now(), 'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        self::registra('vetrina.scheda_ritirata', (int) $s['account_id'], ['motivo' => trim($motivo)]);
        self::seo();
        return '';
    }

    /** L'interruttore «In home» della scheda: acceso = approvata, spento = ritirata dall'admin (senza motivo). */
    public static function accendi(int $id, bool $si, string $motivo = ''): string
    {
        return $si ? self::approvaScheda($id) : self::ritiraScheda($id, $motivo);
    }

    /** L'interruttore del giudizio: acceso = in «Cosa dicono di noi» (al massimo postiGiudizi()), spento = non pubblicato. */
    public static function accendiGiudizio(int $id, bool $si, string $motivo = ''): string
    {
        $s = self::riga($id);
        $g = $s ? self::giudizio((int) $s['account_id']) : null;
        if (!$s || !$g || !empty($g['withdrawn_at'])) return 'Non c\'è un giudizio da pubblicare.';
        if ($si) {
            if (!empty($s['ritirato_at'])) return 'L\'host si è ritirato dal programma.';
            if (($m = self::motivoSospesa($s)) !== '') return 'Non si può pubblicare: ' . self::SOSPESA[$m] . '.';
            if (!(int) $s['giudizio_home'] && count(self::giudiziAccesi()) >= self::postiGiudizi()) return 'In home ci sono già ' . self::postiGiudizi() . ' giudizi: spegnine uno prima.';
            Db::update('vetrina_schede', ['giudizio_home' => 1, 'giudizio_approvato_at' => Support::now(), 'giudizio_ritirato_at' => null, 'giudizio_motivo' => null,
                                          'giudizio_ordine' => (int) $s['giudizio_home'] && (int) $s['giudizio_ordine'] > 0 ? (int) $s['giudizio_ordine'] : self::inFondo('giudizio_ordine'),
                                          'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        } else {
            Db::update('vetrina_schede', ['giudizio_home' => 0, 'giudizio_ritirato_at' => Support::now(), 'giudizio_motivo' => mb_substr(trim($motivo), 0, 255) ?: null,
                                          'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        }
        // testimonials segue la decisione (la media dei giudizi pubblicati, llms.txt).
        Db::update('testimonials', ['status' => $si ? 'pubblicata' : 'in_attesa', 'visible' => 0, 'published_at' => $si ? ($g['published_at'] ?: Support::now()) : $g['published_at']],
                   'id = :tid', ['tid' => (int) $g['id']]);
        self::registra($si ? 'vetrina.giudizio_pubblicato' : 'vetrina.giudizio_non_pubblicato', (int) $s['account_id'], ['voto' => (int) $g['rating'], 'motivo' => trim($motivo)]);
        self::seo();
        return '';
    }

    /** Le frecce ▲▼: scambia con la vicina nell'ordine della sua sezione ('scheda' o 'giudizio'). */
    public static function sposta(string $tipo, int $id, int $verso): void
    {
        $ids = array_map(fn($x) => (int) $x['s']['id'], $tipo === 'giudizio' ? self::giudiziAccesi() : self::visibili());
        $i = array_search($id, $ids, true);
        if ($i === false || !isset($ids[$i + $verso])) return;
        [$ids[$i], $ids[$i + $verso]] = [$ids[$i + $verso], $ids[$i]];
        self::ordina($tipo, $ids);
    }

    /** L'ordine intero (trascinando, o dopo le frecce): 1, 2, 3… nell'ordine dato. */
    public static function ordina(string $tipo, array $ids): void
    {
        $col = $tipo === 'giudizio' ? 'giudizio_ordine' : 'scheda_ordine';
        $n = 0;
        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) Db::update('vetrina_schede', [$col => ++$n], 'id = :id', ['id' => $id]);
        Auth::audit('vetrina.ordine', null, ['tipo' => $tipo === 'giudizio' ? 'giudizio' : 'scheda', 'ordine' => array_values(array_map('intval', $ids))]);
    }

    /** La risposta pubblica sotto il giudizio (vuota = nessuna) e la nota interna. */
    public static function rispondi(int $id, string $testo): void
    {
        $s = self::riga($id);
        if (!$s) return;
        Db::update('vetrina_schede', ['risposta_pubblica' => mb_substr(trim($testo), 0, 600), 'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        self::registra('vetrina.risposta', (int) $s['account_id'], ['vuota' => trim($testo) === '']);
    }

    public static function nota(int $id, string $testo): void
    {
        $s = self::riga($id);
        if (!$s) return;
        Db::update('vetrina_schede', ['nota_admin' => mb_substr(trim($testo), 0, 1000), 'updated_at' => Support::now()], 'id = :id', ['id' => $id]);
        self::registra('vetrina.nota', (int) $s['account_id']);
    }

    /** L'host sceglie copertina e logo (chip). Togliere un'immagine approvata rimette la scheda «da approvare». */
    public static function scegliImmagini(int $accountId, bool $copertina, bool $logo): void
    {
        $s = self::assicura($accountId);
        if (!$s) return;
        $imm = json_decode((string) ($s['immagini'] ?? ''), true) ?: [];
        $imm = ['copertina' => $copertina ? 1 : 0, 'logo' => $logo ? 1 : 0] + (isset($imm['approvate']) ? ['approvate' => $imm['approvate']] : []);
        Db::update('vetrina_schede', ['immagini' => json_encode($imm), 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
        self::registra('vetrina.immagini', $accountId, ['copertina' => $copertina, 'logo' => $logo]);
        self::sincronizza();
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
        if ($s) Db::update('vetrina_schede', ['ritirato_at' => Support::now(), 'scheda_home' => 0, 'giudizio_home' => 0, 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
        Testimonianze::ritira($accountId);
        Giudizi::ritirato($acc);
        self::registra('vetrina.ritiro', $accountId);
        self::seo();
        return true;
    }

    /**
     * Le schede di tutti gli aderenti (create se mancano) e lo spegnimento automatico: guida archiviata, abbonamento
     * scaduto, account chiuso (scheda e giudizio); copertina o logo tolti dopo l'approvazione (la scheda torna «da approvare»).
     * Dai Richiami, dalla home dell'amministrazione e da «Giudizi e vetrina». @return int quante schede o giudizi si sono spenti
     */
    public static function sincronizza(): int
    {
        if (!self::disponibili()) return 0;
        foreach (Db::all('SELECT a.id FROM accounts a LEFT JOIN vetrina_schede v ON v.account_id = a.id WHERE a.giudizio_adesione IS NOT NULL AND v.id IS NULL') as $a) self::assicura((int) $a['id']);
        foreach (Db::all("SELECT * FROM vetrina_schede WHERE property_id IS NULL OR scheda_da IS NULL OR scheda_da = ''") as $s) self::assicura((int) $s['account_id']);
        $n = 0;
        foreach (Db::all('SELECT * FROM vetrina_schede WHERE (scheda_home = 1 OR giudizio_home = 1) AND ritirato_at IS NULL') as $s) {
            $p = self::struttura($s);
            $m = self::motivoSospesa($s, $p);
            if ($m !== '') {
                Db::update('vetrina_schede', ['scheda_home' => 0, 'giudizio_home' => 0, 'sospesa' => $m, 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
                self::registra('vetrina.spenta', (int) $s['account_id'], ['motivo' => $m]);
                $n++;
            } elseif ((int) $s['scheda_home'] && self::daRiapprovare($s, $p)) {
                Db::update('vetrina_schede', ['scheda_home' => 0, 'scheda_approvata_at' => null, 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
                self::registra('vetrina.da_riapprovare', (int) $s['account_id'], ['mancano' => self::mancano($s, $p)]);
                $n++;
            }
        }
        // La causa è passata (guida ripubblicata, abbonamento rinnovato): la sospensione si toglie, l'approvazione va rifatta.
        foreach (Db::all("SELECT * FROM vetrina_schede WHERE sospesa <> '' AND ritirato_at IS NULL") as $s) {
            if (self::motivoSospesa($s) === '') Db::update('vetrina_schede', ['sospesa' => '', 'updated_at' => Support::now()], 'id = :id', ['id' => (int) $s['id']]);
        }
        if ($n) self::seo();
        return $n;
    }

    // ------------------------------------------------------------ l'amministrazione

    /**
     * Le righe di «Giudizi e vetrina» per una delle tre schede in alto, con i conteggi e i quattro numeri.
     * Vetrina: tutte le schede di chi non si è ritirato, prima le approvate nell'ordine (con la posizione), poi le altre.
     * Giudizi: chi ha lasciato un giudizio (filtro «5 stelle» di serie), prima i pubblicati nell'ordine. Ritirati: chi ha lasciato il programma.
     * @return array{righe:list<array>,conta:array<string,int>,numeri:array}
     */
    public static function elenco(string $scheda = 'vetrina', string $stelle = '5'): array
    {
        $conta = array_fill_keys(array_keys(self::SCHEDE), 0);
        if (!self::disponibili()) return ['righe' => [], 'conta' => $conta, 'numeri' => []];
        $posizioni = []; foreach (self::visibili() as $i => $x) $posizioni[(int) $x['s']['id']] = $i + 1;
        $ordG = []; foreach (self::giudiziAccesi() as $i => $x) $ordG[(int) $x['s']['id']] = $i + 1;
        $oggi = gmdate('Y-m-d');
        $tutte = [];
        $num = ['in_vetrina' => count($posizioni), 'in_home' => min(count($posizioni), self::postiSchede()), 'posti_schede' => self::postiSchede(),
                'posti_giudizi' => self::postiGiudizi(), 'schede_da_approvare' => 0, 'giudizi_da_approvare' => 0, 'nuovi_oggi' => 0,
                'giudizi_pubblicati' => count($ordG), 'giudizi_arrivati' => 0, 'doppioni' => 0, 'aderenti' => 0];
        foreach (Db::all('SELECT v.*, a.giudizio_adesione, a.giudizio_adesione_at, a.giudizio_sconto_stato, a.giudizio_sconto_at, u.email
                          FROM vetrina_schede v LEFT JOIN accounts a ON a.id = v.account_id LEFT JOIN users u ON u.id = a.user_id ORDER BY v.id DESC') as $s) {
            $p = self::struttura($s);
            $g = self::giudizio((int) $s['account_id']);
            $sosp = empty($s['ritirato_at']) ? self::motivoSospesa($s, $p) : '';
            $pos = $posizioni[(int) $s['id']] ?? 0;
            $r = $s + ['p' => $p, 'g' => $g, 'motivo' => $sosp, 'posizione' => $pos, 'posizione_giudizio' => $ordG[(int) $s['id']] ?? 0,
                       'stato_scheda' => self::statoScheda($s, $sosp, $pos), 'stato_giudizio' => self::statoGiudizio($s, $g, $sosp),
                       'mancano' => self::mancano($s, $p), 'segnalato' => self::segnalato($g), 'scelte' => self::scelte($s), 'host' => self::nomeHost((int) $s['account_id']),
                       'codice' => Codici::disponibili() ? Codici::cliente((int) $s['account_id']) : '', 'piano' => self::piano((int) $s['account_id']),
                       'richiesta' => self::dataRichiesta($s)];
            $r['doppione'] = $pos > 0 && $r['stato_giudizio'] === 'pubblicato';
            $ritirato = !empty($s['ritirato_at']);
            if ($ritirato) $conta['ritirati']++;
            else {
                $conta['vetrina']++; $num['aderenti']++;
                if ($g && empty($g['withdrawn_at'])) { $conta['giudizi']++; $num['giudizi_arrivati']++; }
                if ($r['stato_scheda'] === 'da_approvare') { $num['schede_da_approvare']++; if (str_starts_with((string) $s['created_at'], $oggi)) $num['nuovi_oggi']++; }
                if ($r['stato_giudizio'] === 'da_approvare') { $num['giudizi_da_approvare']++; if (str_starts_with((string) $g['created_at'], $oggi)) $num['nuovi_oggi']++; }
                if ($r['doppione']) $num['doppioni']++;
            }
            $tutte[] = $r;
        }
        $num['attivi'] = max((int) Db::val("SELECT COUNT(DISTINCT s.account_id) FROM subscriptions s WHERE s.status IN ('active','trialing','past_due')", [], 0), $num['aderenti']);
        $num['da_approvare'] = $num['schede_da_approvare'] + $num['giudizi_da_approvare'];
        $peso = ['in_home' => 0, 'in_vetrina' => 0, 'da_approvare' => 1, 'ritirata_admin' => 2, 'sospesa' => 3];
        $pesoG = ['pubblicato' => 0, 'da_approvare' => 1, 'non_pubblicato' => 2, 'sospeso' => 3];
        $righe = match ($scheda) {
            'giudizi' => array_values(array_filter($tutte, fn($r) => empty($r['ritirato_at']) && $r['g'] && empty($r['g']['withdrawn_at']) && ($stelle !== '5' || (int) $r['g']['rating'] === 5))),
            'ritirati' => array_values(array_filter($tutte, fn($r) => !empty($r['ritirato_at']))),
            default => array_values(array_filter($tutte, fn($r) => empty($r['ritirato_at']))),
        };
        if ($scheda === 'giudizi') usort($righe, fn($a, $b) => [$pesoG[$a['stato_giudizio']] ?? 9, $a['posizione_giudizio'] ?: 999, -(int) strtotime((string) $a['g']['created_at'])]
                                                            <=> [$pesoG[$b['stato_giudizio']] ?? 9, $b['posizione_giudizio'] ?: 999, -(int) strtotime((string) $b['g']['created_at'])]);
        elseif ($scheda === 'vetrina') usort($righe, fn($a, $b) => [$peso[$a['stato_scheda']] ?? 9, $a['posizione'] ?: 999, -(int) $a['id']] <=> [$peso[$b['stato_scheda']] ?? 9, $b['posizione'] ?: 999, -(int) $b['id']]);
        return ['righe' => $righe, 'conta' => $conta, 'numeri' => $num];
    }

    /** Una riga di «Giudizi e vetrina» per id (la pagina della scheda). */
    public static function rigaElenco(int $id): ?array
    {
        foreach (array_merge(self::elenco('vetrina')['righe'], self::elenco('ritirati')['righe']) as $r) if ((int) $r['id'] === $id) return $r;
        return null;
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

    /** Il CSV di «Giudizi e vetrina» (Excel italiano: BOM e punto e virgola), tutte le righe. */
    public static function csv(): string
    {
        $f = fopen('php://temp', 'w+');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['Codice cliente', 'Host', 'Email', 'Struttura', 'Città', 'Piano', 'Adesione', 'Dove', 'Sconto', 'Scheda', 'Posizione', 'Giudizio', 'Voto', 'Commento',
                     'Segnalato', 'Ritirato il'], ';', '"', '');
        foreach (array_merge(self::elenco('vetrina')['righe'], self::elenco('ritirati')['righe']) as $r) {
            fputcsv($f, [$r['codice'], $r['host'], (string) $r['email'], (string) ($r['p']['name'] ?? ''), $r['p'] ? self::dove($r['p']) : '', $r['piano'],
                         substr((string) $r['giudizio_adesione_at'], 0, 10), Giudizi::ADESIONI[$r['giudizio_adesione']] ?? '', Giudizi::STATI[$r['giudizio_sconto_stato']] ?? '',
                         self::STATI_SCHEDA[$r['stato_scheda']] ?? $r['stato_scheda'], $r['posizione'] ?: '', self::STATI_GIUDIZIO[$r['stato_giudizio']] ?? $r['stato_giudizio'],
                         $r['g'] ? (int) $r['g']['rating'] : '', $r['g'] ? (string) $r['g']['body'] : '', $r['segnalato'] ? 'sì' : 'no', substr((string) $r['ritirato_at'], 0, 10)], ';', '"', '');
        }
        rewind($f);
        return (string) stream_get_contents($f);
    }
}
