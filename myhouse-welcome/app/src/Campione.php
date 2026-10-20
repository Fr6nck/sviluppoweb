<?php
namespace MHW;

/**
 * 9M · La «scheda campione» di Portfolio: una guida dell'account fa da modello per le altre (le «gemelle»).
 *
 * Regole (brief 9M, mockup campione.png):
 *   - solo con un abbonamento Portfolio PAGATO (o dato a mano, o con un codice omaggio): disponibile();
 *   - una sola campione per account (rendi() toglie le altre); si cambia quando si vuole: i collegamenti esistenti restano,
 *     i nuovi puntano alla nuova; può essere anche una bozza; niente campione automatica;
 *   - le sezioni copiate (Copia) restano collegate all'origine (origine_sezione_id, origine_property_id, origine_versione);
 *     ogni salvataggio di una sezione della campione ne alza la versione (toccata());
 *   - una gemella è una guida con almeno una sezione copiata dalla campione; è «da aggiornare» se una sua sezione collegata
 *     è più vecchia della campione, se manca una sezione aggiunta alla campione dopo il collegamento, o se ha una sezione
 *     che nella campione è stata eliminata;
 *   - NIENTE si propaga da solo e nessuna email: banner e contatori nel pannello, poi «Porta le modifiche alle gemelle»
 *     (righe = sezioni cambiate, colonne = gemelle) e applica(): storico 30 giorni delle copie sovrascritte o tolte
 *     (sezioni_versioni), audit_log, ripubblicazione delle guide online. Le traduzioni arrivano con la sezione (stesse
 *     righe della campione, con le impronte): non consumano il tetto annuale (9G); le lingue che la campione non ha restano
 *     da tradurre come sempre.
 */
final class Campione
{
    public const GIORNI_STORICO = 30;
    /** Gli stati di una cella (sezione × gemella) e se la casella parte spuntata. */
    public const STATI = [
        'uguale' => ['uguale alla campione', true], 'personalizzata' => ['personalizzata', false], 'manca' => ['manca: verrà aggiunta', true],
        'indirizzo' => ['indirizzo diverso: controlla', false], 'presente' => ['presente: verrà tolta se spunti', false], 'assente' => ['già assente', false],
        'allineata' => ['allineata', false],
    ];

    public static function tabelle(): bool { return Migrator::columnExists('properties', 'campione') && Migrator::columnExists('sections', 'origine_sezione_id'); }

    /** Portfolio pagato (o dato a mano, o con l'omaggio): la campione e la propagazione si accendono. */
    public static function disponibile(int $accountId): bool
    {
        if (!self::tabelle()) return false;
        $sub = Subscriptions::active($accountId);
        if (!$sub || empty($sub['package_version_id'])) return false;
        $pv = Plans::version((int) $sub['package_version_id']);
        return $pv && Plans::perProperty($pv);
    }

    /** Il piano scelto (non ancora pagato) è Portfolio: la voce c'è, spenta, con lo spiegone. */
    public static function inArrivo(int $accountId): bool
    {
        if (!self::tabelle() || self::disponibile($accountId)) return false;
        $i = (int) Db::val('SELECT intended_package_version_id FROM accounts WHERE id = ?', [$accountId], 0);
        $pv = $i ? Plans::version($i) : null;
        return $pv && Plans::perProperty($pv);
    }

    /** La guida campione dell'account, o null. */
    public static function di(int $accountId): ?array
    {
        if (!self::tabelle()) return null;
        return Db::one('SELECT * FROM properties WHERE account_id = ? AND campione = 1 AND archived_at IS NULL', [$accountId]);
    }

    /** Rende campione una guida (una sola per account). */
    public static function rendi(int $accountId, int $propertyId): void
    {
        if (!self::disponibile($accountId)) throw new \RuntimeException(TestiSito::testoSemplice('attivazione.spiegone'));
        $p = Db::one('SELECT id, name FROM properties WHERE id = ? AND account_id = ? AND archived_at IS NULL AND is_demo = 0', [$propertyId, $accountId]);
        if (!$p) throw new NotFound('Struttura non trovata.');
        Db::tx(function () use ($accountId, $propertyId) {
            Db::run('UPDATE properties SET campione = 0 WHERE account_id = ? AND campione = 1', [$accountId]);
            Db::update('properties', ['campione' => 1], 'id = :pid', ['pid' => $propertyId]);
        });
        Auth::audit('campione.scelta', null, ['account_id' => $accountId, 'property_id' => $propertyId, 'nome' => (string) $p['name']]);
    }

    /** Toglie la campione (i collegamenti restano). */
    public static function togli(int $accountId): void
    {
        if (!self::tabelle()) return;
        Db::run('UPDATE properties SET campione = 0 WHERE account_id = ?', [$accountId]);
        Auth::audit('campione.tolta', null, ['account_id' => $accountId]);
    }

    /**
     * Dopo un salvataggio di una sezione: nella campione la versione sale; in una gemella la copia diventa «personalizzata»
     * (e il parcheggio non è più «da controllare»). Non propaga niente.
     */
    public static function toccata(int $sectionId): void
    {
        if (!self::tabelle()) return;
        $s = Db::one('SELECT s.id, s.origine_sezione_id, p.campione FROM sections s JOIN properties p ON p.id = s.property_id WHERE s.id = ?', [$sectionId]);
        if (!$s) return;
        $cambi = ['da_controllare' => 0];
        if ((int) $s['campione'] === 1) Db::run('UPDATE sections SET versione = versione + 1, versione_at = ?, da_controllare = 0 WHERE id = ?', [Support::now(), $sectionId]);
        else {
            if (!empty($s['origine_sezione_id'])) $cambi['personalizzata_at'] = Support::now();
            Db::update('sections', $cambi, 'id = :sid', ['sid' => $sectionId]);
        }
    }

    /** Una sezione della campione sta per essere eliminata: resta nello storico (titolo e testo) per dirlo alle gemelle. */
    public static function primaDiEliminare(int $sectionId): void
    {
        if (!self::tabelle()) return;
        $s = Db::one('SELECT s.*, p.campione FROM sections s JOIN properties p ON p.id = s.property_id WHERE s.id = ?', [$sectionId]);
        if ($s && (int) $s['campione'] === 1) self::storico($s, 'eliminata_campione');
    }

    // ------------------------------------------------------------ lo stato delle gemelle

    /** Le sezioni non di base della campione (quelle che si possono copiare), per id. */
    private static function sezioniCampione(array $c): array
    {
        $out = [];
        foreach (Db::all('SELECT s.*, t.title FROM sections s LEFT JOIN section_translations t ON t.section_id = s.id AND t.locale = ?
                          WHERE s.property_id = ? AND s.is_core = 0 ORDER BY s.position, s.id', [$c['default_locale'], (int) $c['id']]) as $s) {
            if (in_array($s['kind'], Copia::SEZIONI, true)) $out[(int) $s['id']] = $s;
        }
        return $out;
    }

    /** Il titolo di una sezione (quello scritto, o quello di serie). */
    public static function titolo(array $s): string
    {
        return trim((string) ($s['title'] ?? '')) !== '' ? (string) $s['title'] : SectionCatalog::title((string) $s['kind'], 'it');
    }

    /** Le gemelle della campione: le guide dell'account con almeno una sezione copiata da lei. */
    public static function gemelle(array $c): array
    {
        return Db::all('SELECT p.* FROM properties p WHERE p.account_id = ? AND p.id <> ? AND p.archived_at IS NULL AND p.is_demo = 0
                          AND EXISTS (SELECT 1 FROM sections s WHERE s.property_id = p.id AND s.origine_property_id = ?) ORDER BY p.id',
                       [(int) $c['account_id'], (int) $c['id'], (int) $c['id']]);
    }

    /**
     * La tabella di «Porta le modifiche alle gemelle»: una riga per sezione cambiata (modificata, aggiunta, eliminata),
     * una colonna per gemella, in ogni cella lo stato e se la casella parte spuntata.
     * @return array{campione:array,gemelle:list<array>,righe:list<array>,contatori:array<int,int>,cambiate:list<string>}
     */
    public static function differenze(int $accountId): array
    {
        $vuoto = ['campione' => null, 'gemelle' => [], 'righe' => [], 'contatori' => [], 'cambiate' => []];
        $c = self::di($accountId);
        if (!$c) return $vuoto;
        $sezC = self::sezioniCampione($c);
        $gemelle = self::gemelle($c);
        $righe = []; $cont = array_fill_keys(array_map(fn($g) => (int) $g['id'], $gemelle), 0);
        $perG = [];
        foreach ($gemelle as $g) {
            $sez = Db::all('SELECT s.*, t.title FROM sections s LEFT JOIN section_translations t ON t.section_id = s.id AND t.locale = ? WHERE s.property_id = ? AND s.is_core = 0',
                           [$g['default_locale'], (int) $g['id']]);
            $collegate = []; $perKind = [];
            foreach ($sez as $s) {
                if ((int) ($s['origine_property_id'] ?? 0) === (int) $c['id'] && $s['origine_sezione_id']) $collegate[(int) $s['origine_sezione_id']] = $s;
                $perKind[$s['kind']][] = $s;
            }
            $dal = (string) Db::val('SELECT MIN(created_at) FROM sections WHERE property_id = ? AND origine_property_id = ?', [(int) $g['id'], (int) $c['id']], '');
            $perG[(int) $g['id']] = ['collegate' => $collegate, 'perKind' => $perKind, 'dal' => $dal];
        }
        // Le sezioni della campione: modificate o aggiunte.
        foreach ($sezC as $cid => $cs) {
            $celle = []; $daFare = false;
            foreach ($gemelle as $g) {
                $x = $perG[(int) $g['id']]; $l = $x['collegate'][$cid] ?? null;
                if ($l) {
                    if ((int) $l['origine_versione'] >= (int) $cs['versione']) $stato = 'allineata';
                    elseif ($cs['kind'] === 'parking') $stato = 'indirizzo';
                    elseif (!empty($l['personalizzata_at'])) $stato = 'personalizzata';
                    else $stato = 'uguale';
                    $celle[(int) $g['id']] = ['stato' => $stato, 'sezione' => $l];
                } elseif ($x['dal'] !== '' && (string) $cs['created_at'] > $x['dal']) {
                    // Aggiunta alla campione dopo il collegamento. Se la gemella ha già una sezione di quel tipo (non multipla), è «personalizzata».
                    $stesso = !SectionCatalog::multipla($cs['kind']) ? ($x['perKind'][$cs['kind']][0] ?? null) : null;
                    $celle[(int) $g['id']] = $stesso ? ['stato' => $cs['kind'] === 'parking' ? 'indirizzo' : 'personalizzata', 'sezione' => $stesso] : ['stato' => 'manca', 'sezione' => null];
                } else $celle[(int) $g['id']] = ['stato' => 'allineata', 'sezione' => null];   // non copiata di proposito: niente da proporre
                if ($celle[(int) $g['id']]['stato'] !== 'allineata') { $daFare = true; $cont[(int) $g['id']]++; }
            }
            if (!$daFare) continue;
            $nuova = !array_filter($celle, fn($x) => $x['sezione'] && (int) ($x['sezione']['origine_sezione_id'] ?? 0) === $cid);
            $righe[] = ['chiave' => 'c' . $cid, 'tipo' => $nuova ? 'aggiunta' : 'modificata', 'kind' => $cs['kind'], 'titolo' => self::titolo($cs),
                        'quando' => (string) ($nuova ? $cs['created_at'] : ($cs['versione_at'] ?: $cs['created_at'])), 'campione' => $cs, 'celle' => $celle];
        }
        // Le sezioni eliminate nella campione che le gemelle hanno ancora.
        $eliminate = [];
        foreach ($gemelle as $g) foreach ($perG[(int) $g['id']]['collegate'] as $oid => $l) if (!isset($sezC[$oid])) $eliminate[$oid][(int) $g['id']] = $l;
        foreach ($eliminate as $oid => $per) {
            $st = Db::one("SELECT * FROM sezioni_versioni WHERE section_id = ? AND motivo = 'eliminata_campione' ORDER BY id DESC", [$oid]);
            $prima = reset($per);
            $celle = [];
            foreach ($gemelle as $g) {
                $l = $per[(int) $g['id']] ?? null;
                $celle[(int) $g['id']] = $l ? ['stato' => 'presente', 'sezione' => $l] : ['stato' => 'assente', 'sezione' => null];
                if ($l) $cont[(int) $g['id']]++;
            }
            $righe[] = ['chiave' => 'e' . $oid, 'tipo' => 'eliminata', 'kind' => (string) $prima['kind'], 'titolo' => $st ? (string) $st['titolo'] : self::titolo($prima),
                        'quando' => (string) ($st['created_at'] ?? ''), 'campione' => null, 'celle' => $celle];
        }
        return ['campione' => $c, 'gemelle' => $gemelle, 'righe' => $righe, 'contatori' => $cont,
                'cambiate' => array_values(array_unique(array_map(fn($r) => $r['titolo'], $righe)))];
    }

    // ------------------------------------------------------------ il testo e le differenze

    /** Il testo di una sezione nella lingua principale, in chiaro (titolo, testi, righe e luoghi), per l'anteprima. */
    public static function testo(?array $s, string $loc): string
    {
        if (!$s) return '';
        $parti = [];
        $raccogli = function ($v) use (&$parti, &$raccogli) {
            if (is_array($v)) { foreach ($v as $x) $raccogli($x); return; }
            $t = trim(strip_tags((string) $v));
            if ($t !== '' && !is_numeric($t) && !preg_match('#^(https?://|\d{4}-\d{2}-\d{2})#', $t)) $parti[] = $t;
        };
        $t = Db::one('SELECT data FROM section_translations WHERE section_id = ? AND locale = ?', [(int) $s['id'], $loc]);
        $raccogli(json_decode((string) ($t['data'] ?? ''), true) ?: []);
        foreach (Db::all('SELECT p.name, pt.category, pt.description, pt.note FROM places p LEFT JOIN place_translations pt ON pt.place_id = p.id AND pt.locale = ? WHERE p.section_id = ? ORDER BY p.position, p.id', [$loc, (int) $s['id']]) as $pl) {
            $raccogli([$pl['name'], $pl['category'], $pl['description'], $pl['note']]);
        }
        return trim(implode(' ', array_filter($parti)));
    }

    /** Le differenze parola per parola: [['=', testo] | ['-', tolto] | ['+', aggiunto]] (al massimo 600 parole per lato). */
    public static function diff(string $prima, string $dopo): array
    {
        $a = array_slice(preg_split('/(\s+)/u', $prima, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 600);
        $b = array_slice(preg_split('/(\s+)/u', $dopo, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 600);
        $n = count($a); $m = count($b);
        $l = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) for ($j = $m - 1; $j >= 0; $j--) $l[$i][$j] = $a[$i] === $b[$j] ? $l[$i + 1][$j + 1] + 1 : max($l[$i + 1][$j], $l[$i][$j + 1]);
        $out = []; $i = 0; $j = 0;
        $metti = function (string $t, string $w) use (&$out) { if ($out && $out[count($out) - 1][0] === $t) $out[count($out) - 1][1] .= ' ' . $w; else $out[] = [$t, $w]; };
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) { $metti('=', $a[$i]); $i++; $j++; }
            elseif ($l[$i + 1][$j] >= $l[$i][$j + 1]) { $metti('-', $a[$i]); $i++; }
            else { $metti('+', $b[$j]); $j++; }
        }
        while ($i < $n) $metti('-', $a[$i++]);
        while ($j < $m) $metti('+', $b[$j++]);
        return $out;
    }

    // ------------------------------------------------------------ l'applicazione

    /** Una sezione intera nello storico (30 giorni): dati, traduzioni, luoghi. */
    private static function storico(array $s, string $motivo): void
    {
        $tr = Db::all('SELECT * FROM section_translations WHERE section_id = ?', [(int) $s['id']]);
        $titolo = '';
        foreach ($tr as $t) if (trim((string) $t['title']) !== '') { $titolo = (string) $t['title']; break; }
        $pl = [];
        foreach (Db::all('SELECT * FROM places WHERE section_id = ?', [(int) $s['id']]) as $p) $pl[] = $p + ['traduzioni' => Db::all('SELECT * FROM place_translations WHERE place_id = ?', [(int) $p['id']])];
        Db::insert('sezioni_versioni', ['section_id' => (int) $s['id'], 'property_id' => (int) $s['property_id'], 'kind' => (string) $s['kind'],
                                        'titolo' => mb_substr($titolo !== '' ? $titolo : SectionCatalog::title((string) $s['kind'], 'it'), 0, 200),
                                        'versione' => (int) ($s['origine_versione'] ?? $s['versione'] ?? 1), 'motivo' => $motivo,
                                        'testo' => json_encode(['sezione' => $s, 'traduzioni' => $tr, 'luoghi' => $pl], JSON_UNESCAPED_UNICODE), 'created_at' => Support::now()]);
    }

    /**
     * Applica le caselle spuntate: [chiave riga => [id gemella => 1]]. Aggiorna (sovrascrive, con la copia nello storico),
     * aggiunge, toglie (solo con $confermaRimozioni). Poi ripubblica le gemelle online.
     * @return array{aggiornate:int,aggiunte:int,tolte:int,guide:int}
     */
    public static function applica(int $accountId, array $scelte, bool $confermaRimozioni): array
    {
        if (!self::disponibile($accountId)) throw new \RuntimeException(TestiSito::testoSemplice('attivazione.spiegone'));
        $d = self::differenze($accountId);
        if (!$d['campione']) throw new \RuntimeException('Non c\'è una scheda campione.');
        $esito = ['aggiornate' => 0, 'aggiunte' => 0, 'tolte' => 0, 'guide' => 0];
        $scritti = []; $daLiberare = []; $toccate = [];
        $dup = function (?int $mid, int $pid) use ($accountId, &$scritti): ?int { return $mid ? Media::duplicate($mid, $accountId, $pid, $scritti) : null; };
        try {
            Db::tx(function () use ($d, $scelte, $confermaRimozioni, $dup, &$esito, &$daLiberare, &$toccate) {
                foreach ($d['righe'] as $r) {
                    foreach ($r['celle'] as $gid => $cella) {
                        if (empty($scelte[$r['chiave']][$gid]) || in_array($cella['stato'], ['allineata', 'assente'], true)) continue;
                        $l = $cella['sezione'];
                        if ($r['tipo'] === 'eliminata') {
                            if (!$confermaRimozioni || !$l) continue;   // la rimozione non parte mai senza la conferma
                            self::storico($l, 'tolta');
                            $daLiberare = array_merge($daLiberare, Copia::mediaDellaSezione($l));
                            Db::run('DELETE FROM sections WHERE id = ?', [(int) $l['id']]);
                            $esito['tolte']++; $toccate[$gid] = true;
                            continue;
                        }
                        $cs = Db::one('SELECT * FROM sections WHERE id = ?', [(int) $r['campione']['id']]);   // la riga vera, senza il titolo unito
                        if (!$cs) continue;
                        $d1 = fn(?int $mid) => $dup($mid, $gid);
                        if ($l) {
                            self::storico($l, 'sovrascritta');
                            $daLiberare = array_merge($daLiberare, Copia::mediaDellaSezione($l));
                            Copia::sezione($cs, $gid, (int) $l['position'], $d1, $l);
                            $esito['aggiornate']++;
                        } else {
                            $pos = (int) Db::val('SELECT COALESCE(MAX(position), 0) + 1 FROM sections WHERE property_id = ?', [$gid], 1);
                            Copia::sezione($cs, $gid, $pos, $d1);
                            $esito['aggiunte']++;
                        }
                        $toccate[$gid] = true;
                    }
                }
            });
        } catch (\Throwable $e) {
            foreach ($scritti as [$driver, $key]) { try { Storages::for($driver)->delete($key); } catch (\Throwable) {} }
            throw $e;
        }
        foreach (array_unique($daLiberare) as $mid) Media::rilascia((int) $mid, $accountId);
        foreach (array_keys($toccate) as $gid) {
            $esito['guide']++;
            $g = Db::one('SELECT id, status FROM properties WHERE id = ?', [$gid]);
            if ($g && $g['status'] === 'published' && Subscriptions::active($accountId)) { try { Guide::publish((int) $gid); } catch (\Throwable $e) { Log::exception($e, 'campione: ripubblicazione'); } }
        }
        Auth::audit('campione.applica', null, ['account_id' => $accountId, 'campione' => (int) $d['campione']['id']] + $esito);
        return $esito;
    }

    /** Lo storico oltre i 30 giorni si cancella (dai Richiami). */
    public static function pulisci(): int
    {
        if (!Migrator::tableExists('sezioni_versioni')) return 0;
        return Db::run('DELETE FROM sezioni_versioni WHERE created_at < ?', [gmdate('Y-m-d\TH:i:s\Z', time() - self::GIORNI_STORICO * 86400)]);
    }
}
